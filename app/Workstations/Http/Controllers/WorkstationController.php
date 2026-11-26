<?php

namespace App\Workstations\Http\Controllers;

use App\Workstations\Models\{Command, EnvEntry, Event, Release, Workstation};
use App\Workstations\Support\{AgentProtocol, Timeline};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WorkstationController extends Controller
{
    public function show(string $wid)
    {
        $w = DashboardController::decorate(collect([Workstation::where('workstation_id', $wid)->firstOrFail()]))->first();
        $own = EnvEntry::where('scope', 'workstation')->where('scope_ref', $wid)->orderBy('key')->get();
        $eff = AgentProtocol::effectiveEnv(
            EnvEntry::where('scope', 'global')->pluck('value', 'key')->all(),
            $w->warehouse_code ? EnvEntry::where('scope', 'warehouse')->where('scope_ref', $w->warehouse_code)->pluck('value', 'key')->all() : [],
            $own->pluck('value', 'key')->all());

        $events = Event::where('workstation_id', $wid)->orderByDesc('id')->limit(50)->get()
            ->map(fn($e) => ['at' => $e->created_at, 'text' => Timeline::event($e->type, $e->version, $e->message),
                'tone' => Timeline::tone($e->type), 'kind' => 'event']);
        $commands = Command::where('workstation_id', $wid)->orderByDesc('id')->limit(20)->get()
            ->map(fn($c) => ['at' => $c->updated_at, 'text' => Timeline::command($c->command, $c->status, $c->result),
                'tone' => ['failed' => 'bad', 'done' => 'good'][$c->status] ?? 'info', 'kind' => 'command']);

        return view('workstations::workstation', [
            'w' => $w, 'env' => $own, 'eff' => $eff, 'inSync' => AgentProtocol::envInSync($w->env_applied_hash, $eff),
            'releases' => Release::where('yanked', false)->orderByDesc('id')->get(),
            'timeline' => $events->concat($commands)->sortByDesc('at')->values(),
            'cmds' => AgentProtocol::ALLOWED_COMMANDS,
        ]);
    }

    public function update(Request $r, string $wid)
    {
        $w = Workstation::where('workstation_id', $wid)->firstOrFail();
        $act = (string)$r->input('action');
        $msg = 'Saved.';

        switch ($act) {
            case 'approve':
                $w->update(['status' => 'approved']);
                $msg = 'Workstation approved. It will receive its token on the next check-in.';
                break;
            case 'block':
                $w->update(['status' => 'blocked']);
                $msg = 'Workstation blocked. It can no longer talk to the server.';
                break;
            case 'reset_token':
                $w->update(['token_hash' => null]);
                $msg = 'Token reset. The workstation gets a new token only after it checks in with a valid install key (run a new command from Add Workstation if needed).';
                break;
            case 'save':
                $d = $r->validate([
                    'label' => 'nullable|string|max:120',
                    'warehouse_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]*$/'],
                    'target_release_id' => 'nullable|integer|exists:wsm_releases,id',
                ]);
                $w->update(['label' => $d['label'] ?? null, 'warehouse_code' => trim((string)($d['warehouse_code'] ?? '')) ?: null,
                    'auto_update' => $r->boolean('auto_update'), 'target_release_id' => $d['target_release_id'] ?? null]);
                break;
            case 'command':
                $cmd = (string)$r->input('command');
                if (!in_array($cmd, AgentProtocol::ALLOWED_COMMANDS, true)) {
                    return back()->with('error', 'This command is not allowed.');
                }
                if ($w->status !== 'approved') {
                    return back()->with('error', 'Approve the workstation before sending commands.');
                }
                Command::create(['workstation_id' => $wid, 'command' => $cmd]);
                $msg = Timeline::commandName($cmd) . ' command queued — it will run on the next check-in.';
                break;
            case 'delete':
                Command::where('workstation_id', $wid)->delete();
                EnvEntry::where('scope', 'workstation')->where('scope_ref', $wid)->delete();
                $w->delete();
                return redirect()->route('wsm.index')->with('ok', "$wid removed.");
            default:
                return back()->with('error', 'Unknown action.');
        }
        Event::create(['workstation_id' => $wid, 'type' => 'admin_' . $act,
            'message' => $act === 'command' ? (string)$r->input('command') : null, 'created_at' => now()]);
        return back()->with('ok', $msg);
    }
}
