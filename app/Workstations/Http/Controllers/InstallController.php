<?php

namespace App\Workstations\Http\Controllers;

use App\Models\Warehouse;
use App\Workstations\Models\{InstallToken, Release, Workstation};
use App\Workstations\Support\AgentProtocol;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/** "Add Workstation" wizard: pick a warehouse, get a copy-paste install command with a one-time token. */
class InstallController extends Controller
{
    private function warehouseCodes()
    {
        try {
            $known = Warehouse::query()->orderBy('warehouse_code')->get(['warehouse_code', 'warehouse_name'])
                ->mapWithKeys(fn($w) => [$w->warehouse_code => $w->warehouse_name]);
        } catch (Throwable) {
            $known = collect();
        }
        foreach (Workstation::whereNotNull('warehouse_code')->distinct()->pluck('warehouse_code') as $c) {
            $known[$c] ??= $c;
        }
        return $known->filter(fn($n, $c) => $c !== '' && $c !== null)->sortKeys();
    }

    public function show()
    {
        return view('workstations::install', [
            'codes' => $this->warehouseCodes(),
            'latest' => Release::where('is_latest', true)->where('yanked', false)->first(),
            'keyReady' => AgentProtocol::validPublicKey((string)config('workstations.public_key')),
            'command' => session('install_command'),
            'forCode' => session('install_code'),
            'expires' => session('install_expires'),
            'uses' => session('install_uses'),
        ]);
    }

    public function create(Request $r)
    {
        $d = $r->validate([
            'warehouse_code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'uses' => 'nullable|integer|min:1|max:50',
        ], ['warehouse_code.required' => 'Warehouse chunein.', 'warehouse_code.regex' => 'Warehouse code mein sirf A-Z, 0-9, - _ . allowed hain.']);

        $plain = AgentProtocol::newToken('wsi_');
        $ttl = (int)config('workstations.install_token_ttl');
        InstallToken::create(['token_hash' => AgentProtocol::hashToken($plain), 'warehouse_code' => $d['warehouse_code'],
            'max_uses' => (int)($d['uses'] ?? 1), 'expires_at' => now()->addMinutes($ttl)]);

        $url = rtrim($r->getSchemeAndHttpHost(), '/') . '/api/agent/bootstrap/' . $d['warehouse_code'];
        $cmd = 'curl -fsSL -H "Authorization: Bearer ' . $plain . '" ' . $url . ' | sudo bash';

        // Plain token is shown exactly once (flashed for the next page only) and never stored.
        return redirect()->route('wsm.add')->with([
            'install_command' => $cmd, 'install_code' => $d['warehouse_code'],
            'install_expires' => now()->addMinutes($ttl)->format('d M Y, h:i A'), 'install_uses' => (int)($d['uses'] ?? 1),
            'ok' => 'Install command taiyaar hai. Ise abhi copy kar lein — ye dobara nahi dikhega.',
        ]);
    }
}
