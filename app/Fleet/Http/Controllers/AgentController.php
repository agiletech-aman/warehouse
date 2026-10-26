<?php

namespace App\Fleet\Http\Controllers;

use App\Fleet\Models\{Command, EnvEntry, Event, Release, Workstation};
use App\Fleet\Support\FleetCore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB, Storage};

class AgentController extends Controller
{
    private function bootstrapOk(Request $r): bool
    {
        $k = (string)config('fleet.bootstrap_key');
        $given = (string)($r->header('X-API-Key') ?: $r->input('key', ''));
        return $k !== '' && hash_equals($k, $given);
    }

    private function envFor(?string $wh, string $wid): array
    {
        $rows = EnvEntry::all();
        $pick = fn($scope, $ref) => $rows->where('scope', $scope)->where('scope_ref', $ref)->pluck('value', 'key')->all();
        return FleetCore::effectiveEnv($pick('global', ''), $wh ? $pick('warehouse', $wh) : [], $pick('workstation', $wid));
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

        $res = FleetCore::decide($row ? $row->getAttributes() : null, $in, [
            'now' => now()->toDateTimeString(), 'request_ip' => $r->ip(), 'bootstrap_ok' => $this->bootstrapOk($r),
            'bearer' => $r->bearerToken(), 'env' => $env, 'latest' => $latest ? $latest->toArray() : null,
            'target' => $target ? $target->toArray() : null, 'commands' => $cmds,
            'interval' => (int)config('fleet.check_interval'),
        ]);
        if ($res['http'] !== 200) {
            return response()->json($res['body'], $res['http']);
        }

        DB::transaction(function () use ($res, $wid, $row) {
            $attrs = $res['ws'];
            unset($attrs['id']);
            Workstation::updateOrCreate(['workstation_id' => $wid], $attrs);
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
        // workstation token, or bootstrap key (installer)
        $ok = $this->bootstrapOk($r);
        if (!$ok && ($b = $r->bearerToken())) {
            $ok = Workstation::where('token_hash', FleetCore::hashToken($b))->where('status', 'approved')->exists();
        }
        if (!$ok || $release->yanked) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        return Storage::disk(config('fleet.disk'))->download($release->file_path, 'gasmonitor', [
            'Content-Type' => 'application/octet-stream', 'X-Sha256' => $release->sha256]);
    }

    /** Installer script, rendered for one warehouse. Needs bootstrap key. */
    public function bootstrap(Request $r, string $code)
    {
        if (!$this->bootstrapOk($r)) {
            return response('invalid key', 401);
        }
        $rel = Release::where('is_latest', true)->where('yanked', false)->first();
        if (!$rel) {
            return response('no release published', 409);
        }
        $tpl = file_get_contents(__DIR__ . '/../../stubs/install.sh.stub');
        $base = rtrim($r->getSchemeAndHttpHost() . '/api/fleet', '/');
        $out = strtr($tpl, [
            '{{FLEET_API}}' => $base, '{{API_KEY}}' => (string)config('fleet.bootstrap_key'),
            '{{WAREHOUSE_CODE}}' => preg_replace('/[^A-Za-z0-9_.-]/', '', $code),
            '{{RELEASE_ID}}' => (string)$rel->id, '{{VERSION}}' => $rel->version, '{{SHA256}}' => $rel->sha256,
            '{{RUN_SH}}' => rtrim(file_get_contents(__DIR__ . '/../../stubs/run.sh')),
            '{{PUBLIC_KEY}}' => (string)config('fleet.public_key'),
            '{{CLOUD_API}}' => $base === '' ? '' : preg_replace('#/fleet$#', '/readings', $base),
        ]);
        return response($out, 200, ['Content-Type' => 'text/x-shellscript']);
    }
}
