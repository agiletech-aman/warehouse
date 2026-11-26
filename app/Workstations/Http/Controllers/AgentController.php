<?php

namespace App\Workstations\Http\Controllers;

use App\Workstations\Models\{Command, EnvEntry, Event, InstallToken, Release, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB, Storage};

class AgentController extends Controller
{
    /** Enrollment credential from headers only (X-API-Key or Authorization: Bearer). Never from the URL. */
    private function enrollKey(Request $r): string
    {
        return (string)($r->header('X-API-Key') ?: $r->bearerToken());
    }

    /**
     * Valid enrollment? Returns ['ok'=>bool, 'token'=>?InstallToken].
     * Accepts the shared WSM_BOOTSTRAP_KEY or a non-expired one-time install token with uses left.
     */
    private function enrollment(string $key): array
    {
        if ($key === '') {
            return ['ok' => false, 'token' => null];
        }
        $master = (string)config('workstations.bootstrap_key');
        if ($master !== '' && hash_equals($master, $key)) {
            return ['ok' => true, 'token' => null];
        }
        $t = InstallToken::where('token_hash', AgentProtocol::hashToken($key))->first();
        if ($t && $t->expires_at->isFuture() && $t->uses < $t->max_uses) {
            return ['ok' => true, 'token' => $t];
        }
        return ['ok' => false, 'token' => null];
    }

    private function envFor(?string $wh, string $wid): array
    {
        $rows = EnvEntry::all();
        $pick = fn($scope, $ref) => $rows->where('scope', $scope)->where('scope_ref', $ref)->pluck('value', 'key')->all();
        return AgentProtocol::effectiveEnv($pick('global', ''), $wh ? $pick('warehouse', $wh) : [], $pick('workstation', $wid));
    }

    public function checkin(Request $r)
    {
        $in = $r->json()->all();
        $wid = (string)($in['workstation_id'] ?? '');
        $row = Workstation::where('workstation_id', $wid)->first();
        $latest = Release::where('is_latest', true)->where('yanked', false)->first();
        $target = $row && $row->target_release_id ? Release::where('id', $row->target_release_id)->where('yanked', false)->first() : null;
        $env = $row ? $this->envFor($row->warehouse_code ?: ($in['warehouse_code'] ?? null), $wid) : [];
        $cmds = $row ? Command::where('workstation_id', $wid)->where('status', 'pending')->get(['id', 'command'])->toArray() : [];

        // A known workstation with a token authenticates with Bearer; enrollment only matters before that.
        $needsEnroll = !$row || empty($row->token_hash);
        $enroll = $needsEnroll ? $this->enrollment($this->enrollKey($r))
            : ['ok' => false, 'token' => null];

        $res = AgentProtocol::decide($row ? $row->getAttributes() : null, $in, [
            'now' => now()->toDateTimeString(), 'request_ip' => $r->ip(),
            'enroll_ok' => $enroll['ok'], 'enroll_warehouse' => $enroll['token']?->warehouse_code,
            'bearer' => $r->bearerToken(), 'env' => $env,
            'latest' => $latest?->toArray(), 'target' => $target?->toArray(), 'commands' => $cmds,
            'interval' => (int)config('workstations.check_interval'),
            'public_key' => (string)config('workstations.public_key'),
            'post_update_actions' => (array)config('workstations.post_update_actions'),
        ]);
        if ($res['http'] !== 200) {
            return response()->json($res['body'], $res['http']);
        }

        DB::transaction(function () use ($res, $wid, $enroll) {
            $attrs = $res['ws'];
            unset($attrs['id']);
            Workstation::updateOrCreate(['workstation_id' => $wid], $attrs);
            if ($res['created'] && $enroll['token']) {
                $enroll['token']->increment('uses');
            }
            foreach ($res['events'] as $e) {
                Event::create($e + ['workstation_id' => $wid]);
            }
            foreach ($res['acks'] as $a) {
                Command::where('id', $a['id'])->where('workstation_id', $wid)
                    ->update(['status' => $a['status'], 'result' => $a['result']]);
            }
            if ($res['send_command_ids']) {
                Command::whereIn('id', $res['send_command_ids'])->where('workstation_id', $wid)->update(['status' => 'sent']);
            }
        });
        return response()->json($res['body']);
    }

    public function download(Request $r, Release $release)
    {
        $bearer = (string)$r->bearerToken();
        $ok = $bearer !== '' && Workstation::where('token_hash', AgentProtocol::hashToken($bearer))
                ->where('status', 'approved')->exists();
        if (!$ok) {
            $ok = $this->enrollment($this->enrollKey($r))['ok'];   // installer, before the workstation exists
        }
        $signed = AgentProtocol::verifySignature((string)config('workstations.public_key'), $release->version,
            $release->sha256, $release->signature);
        if (!$ok || $release->yanked || !$signed) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        return Storage::disk(config('workstations.disk'))->download($release->file_path,
            $release->type === 'package' ? 'gasmonitor.tar.gz' : 'gasmonitor', [
                'Content-Type' => 'application/octet-stream', 'X-Sha256' => $release->sha256,
                'X-Signature' => $release->signature]);
    }

    /** Installer script for one warehouse. Credential comes in the Authorization header. */
    public function bootstrap(Request $r, string $code)
    {
        $key = $this->enrollKey($r);
        $enroll = $this->enrollment($key);
        if (!$enroll['ok']) {
            return response("echo 'Install link galat hai ya expire ho gaya. Admin se naya command lein.' >&2; exit 1\n", 401,
                ['Content-Type' => 'text/x-shellscript']);
        }
        $code = preg_replace('/[^A-Za-z0-9_.-]/', '', $code);
        if ($enroll['token'] && $enroll['token']->warehouse_code !== $code) {
            return response("echo 'Ye install command dusre warehouse ka hai.' >&2; exit 1\n", 403,
                ['Content-Type' => 'text/x-shellscript']);
        }
        $pk = (string)config('workstations.public_key');
        $rel = Release::where('is_latest', true)->where('yanked', false)->first();
        if (!$rel || !AgentProtocol::verifySignature($pk, $rel->version, $rel->sha256, $rel->signature)) {
            return response("echo 'Abhi koi signed release publish nahi hua hai.' >&2; exit 1\n", 409,
                ['Content-Type' => 'text/x-shellscript']);
        }

        $base = rtrim($r->getSchemeAndHttpHost(), '/') . '/api/agent';
        $post = $rel->type === 'package' && $rel->post_update
            ? (array)(config('workstations.post_update_actions')[$rel->post_update] ?? []) : [];
        $postSh = implode("\n", array_map(fn($argv) => implode(' ', array_map('escapeshellarg', $argv)), $post));

        $q = fn($v) => str_replace("'", '', (string)$v);   // values are placed inside single quotes
        $out = strtr(file_get_contents(__DIR__ . '/../../stubs/install.sh.stub'), [
            '{{AGENT_API}}' => $q($base), '{{ENROLL_KEY}}' => $q($key), '{{WAREHOUSE_CODE}}' => $code,
            '{{RELEASE_ID}}' => (string)$rel->id, '{{VERSION}}' => $q($rel->version), '{{SHA256}}' => $rel->sha256,
            '{{SIGNATURE}}' => $q($rel->signature), '{{TYPE}}' => $rel->type ?: 'binary',
            '{{PUBLIC_KEY}}' => $q($pk), '{{POST_UPDATE}}' => $postSh,
            '{{CLOUD_API}}' => $q(rtrim($r->getSchemeAndHttpHost(), '/') . '/api/readings'),
            '{{RUN_SH}}' => rtrim(file_get_contents(__DIR__ . '/../../stubs/run.sh')),
        ]);
        return response($out, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
