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
        $msg = 'Save ho gaya.';

        switch ($act) {
            case 'approve':
                $w->update(['status' => 'approved']);
                $msg = 'Workstation approve ho gaya. Agle check-in par isse token mil jayega.';
                break;
            case 'block':
                $w->update(['status' => 'blocked']);
                $msg = 'Workstation block ho gaya. Ab ye server se baat nahi kar payega.';
                break;
            case 'reset_token':
                $w->update(['token_hash' => null]);
                $msg = 'Token reset ho gaya. Workstation ko naya token tabhi milega jab wo valid install key ke saath check-in kare (zarurat ho to Add Workstation se naya command chalayein).';
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
                    return back()->with('error', 'Ye command allowed nahi hai.');
                }
                if ($w->status !== 'approved') {
                    return back()->with('error', 'Pehle workstation approve karein, phir command bhejein.');
                }
                Command::create(['workstation_id' => $wid, 'command' => $cmd]);
                $msg = Timeline::commandName($cmd) . ' command queue ho gaya — agle check-in par chalega.';
                break;
            case 'delete':
                Command::where('workstation_id', $wid)->delete();
                EnvEntry::where('scope', 'workstation')->where('scope_ref', $wid)->delete();
                $w->delete();
                return redirect()->route('wsm.index')->with('ok', "$wid hata diya gaya.");
            default:
                return back()->with('error', 'Anjaan action.');
        }
        Event::create(['workstation_id' => $wid, 'type' => 'admin_' . $act,
            'message' => $act === 'command' ? (string)$r->input('command') : null, 'created_at' => now()]);
        return back()->with('ok', $msg);
    }
}
