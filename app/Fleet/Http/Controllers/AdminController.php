<?php

namespace App\Fleet\Http\Controllers;

use App\Fleet\Models\{Command, EnvEntry, Event, Release, Workstation};
use App\Fleet\Support\FleetCore;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    private function latest(): ?Release
    {
        return Release::where('is_latest', true)->where('yanked', false)->first();
    }

    public function index(Request $r)
    {
        $latest = $this->latest();
        $rels = Release::all()->keyBy('id');
        $rows = Workstation::orderBy('warehouse_code')->orderBy('workstation_id')->get()->map(function ($w) use ($latest, $rels) {
            $target = $w->target_release_id ? ($rels[$w->target_release_id] ?? null) : null;
            $w->vstate = FleetCore::versionState($w->current_version, $latest?->toArray(), $target?->toArray(), (bool)$w->auto_update);
            $w->online = $w->last_seen_at && now()->diffInSeconds($w->last_seen_at, true) < config('fleet.offline_after');
            $w->target_version = $target?->version;
            return $w;
        });
        if ($f = $r->query('wh')) {
            $rows = $rows->where('warehouse_code', $f);
        }
        $stats = ['total' => $rows->count(), 'latest' => $rows->where('vstate', 'latest')->count(),
            'outdated' => $rows->where('vstate', 'outdated')->count(), 'pending' => $rows->where('status', 'pending')->count(),
            'offline' => $rows->where('online', false)->count()];
        return view('fleet::dashboard', compact('rows', 'latest', 'stats') + ['wh' => $f ?? '']);
    }

    public function show(string $wid)
    {
        $w = Workstation::where('workstation_id', $wid)->firstOrFail();
        $env = EnvEntry::where('scope', 'workstation')->where('scope_ref', $wid)->orderBy('key')->get();
        $eff = FleetCore::effectiveEnv(
            EnvEntry::where('scope', 'global')->pluck('value', 'key')->all(),
            $w->warehouse_code ? EnvEntry::where('scope', 'warehouse')->where('scope_ref', $w->warehouse_code)->pluck('value', 'key')->all() : [],
            $env->pluck('value', 'key')->all());
        return view('fleet::workstation', [
            'w' => $w, 'env' => $env, 'eff' => $eff, 'inSync' => FleetCore::envInSync($w->env_applied_hash, $eff),
            'releases' => Release::orderByDesc('id')->get(),
            'events' => Event::where('workstation_id', $wid)->orderByDesc('id')->limit(50)->get(),
            'commands' => Command::where('workstation_id', $wid)->orderByDesc('id')->limit(15)->get(),
            'cmds' => FleetCore::ALLOWED_COMMANDS,
        ]);
    }

    public function update(Request $r, string $wid)
    {
        $w = Workstation::where('workstation_id', $wid)->firstOrFail();
        $act = $r->input('action');
        if ($act === 'approve') {
            $w->update(['status' => 'approved']);
        } elseif ($act === 'block') {
            $w->update(['status' => 'blocked']);
        } elseif ($act === 'reset_token') {
            $w->update(['token_hash' => null]);
        } elseif ($act === 'save') {
            $w->update(['label' => $r->input('label') ?: null, 'warehouse_code' => trim((string)$r->input('warehouse_code')) ?: null,
                'auto_update' => $r->boolean('auto_update'), 'target_release_id' => $r->input('target_release_id') ?: null]);
        } elseif ($act === 'command' && in_array($r->input('command'), FleetCore::ALLOWED_COMMANDS, true)) {
            Command::create(['workstation_id' => $wid, 'command' => $r->input('command')]);
        } elseif ($act === 'delete') {
            $w->delete();
            return redirect()->route('fleet.index');
        }
        Event::create(['workstation_id' => $wid, 'type' => 'admin_' . $act, 'message' => 'by admin', 'created_at' => now()]);
        return back()->with('ok', 'Done');
    }

    public function bulkApprove(Request $r)
    {
        Workstation::whereIn('workstation_id', (array)$r->input('ids'))->where('status', 'pending')->update(['status' => 'approved']);
        return back()->with('ok', 'Approved');
    }

    // ── releases ──────────────────────────────────────────────────────────
    public function releases()
    {
        $counts = Workstation::selectRaw('current_version v, count(*) c')->groupBy('current_version')->pluck('c', 'v');
        return view('fleet::releases', ['releases' => Release::orderByDesc('id')->get(), 'counts' => $counts,
            'pubkey' => config('fleet.public_key')]);
    }

    public function storeRelease(Request $r)
    {
        $d = $r->validate(['version' => ['required', 'regex:/^\d+\.\d+\.\d+(-[A-Za-z0-9.]+)?$/', 'unique:fleet_releases,version'],
            'binary' => 'required|file', 'signature' => 'nullable|string|max:128', 'notes' => 'nullable|string|max:2000']);
        $file = $r->file('binary');
        $sha = hash_file('sha256', $file->getRealPath());
        if ($d['signature'] ?? null) {
            $pk = (string)config('fleet.public_key');
            if ($pk === '' || !FleetCore::verifySignature($pk, $d['version'], $sha, $d['signature'])) {
                return back()->withErrors(['signature' => 'Signature does not verify against FLEET_PUBLIC_KEY for this file+version.'])->withInput();
            }
        } elseif (config('fleet.public_key')) {
            return back()->withErrors(['signature' => 'Signature required (FLEET_PUBLIC_KEY is set).'])->withInput();
        }
        $path = Storage::disk(config('fleet.disk'))->putFileAs('fleet/releases', $file, $d['version'] . '-' . substr($sha, 0, 12));
        $rel = Release::create(['version' => $d['version'], 'file_path' => $path, 'sha256' => $sha,
            'signature' => $d['signature'] ?? null, 'size' => $file->getSize(), 'notes' => $d['notes'] ?? null]);
        if ($r->boolean('make_latest')) {
            Release::where('id', '!=', $rel->id)->update(['is_latest' => false]);
            $rel->update(['is_latest' => true]);
        }
        return redirect()->route('fleet.releases')->with('ok', 'Release ' . $rel->version . ' uploaded');
    }

    public function releaseAction(Request $r, Release $release)
    {
        if ($r->input('action') === 'latest') {
            Release::query()->update(['is_latest' => false]);
            $release->update(['is_latest' => true, 'yanked' => false]);
        } elseif ($r->input('action') === 'yank') {
            $release->update(['yanked' => true, 'is_latest' => false]);
        } elseif ($r->input('action') === 'unyank') {
            $release->update(['yanked' => false]);
        }
        return back()->with('ok', 'Done');
    }

    // ── env ───────────────────────────────────────────────────────────────
    public function env(Request $r)
    {
        $scope = in_array($r->query('scope'), ['global', 'warehouse'], true) ? $r->query('scope') : 'global';
        $ref = $scope === 'global' ? '' : (string)$r->query('ref', '');
        $codes = Workstation::whereNotNull('warehouse_code')->distinct()->pluck('warehouse_code');
        return view('fleet::env', ['scope' => $scope, 'ref' => $ref, 'codes' => $codes,
            'entries' => EnvEntry::where('scope', $scope)->where('scope_ref', $ref)->orderBy('key')->get()]);
    }

    public function envSave(Request $r)
    {
        $scope = $r->input('scope');
        $ref = (string)$r->input('ref', '');
        if (!in_array($scope, ['global', 'warehouse', 'workstation'], true) || ($scope !== 'global' && $ref === '')) {
            return back()->withErrors(['key' => 'bad scope']);
        }
        $key = strtoupper(trim((string)$r->input('key')));
        if ($r->input('action') === 'delete') {
            EnvEntry::where(['scope' => $scope, 'scope_ref' => $scope === 'global' ? '' : $ref, 'key' => $key])->delete();
            return back()->with('ok', "$key removed");
        }
        $val = trim((string)$r->input('value'));
        if ($err = FleetCore::validateEnvEntry($key, $val)) {
            return back()->withErrors(['key' => $err])->withInput();
        }
        EnvEntry::updateOrCreate(['scope' => $scope, 'scope_ref' => $scope === 'global' ? '' : $ref, 'key' => $key], ['value' => $val]);
        return back()->with('ok', "$key saved");
    }
}
