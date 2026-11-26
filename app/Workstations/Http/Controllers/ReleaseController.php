<?php

namespace App\Workstations\Http\Controllers;

use App\Workstations\Models\{Release, Workstation};
use App\Workstations\Support\{AgentProtocol, ReleasePublisher};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class ReleaseController extends Controller
{
    public function index()
    {
        return view('workstations::releases', [
            'releases' => Release::orderByDesc('id')->get(),
            'counts' => Workstation::selectRaw('current_version v, count(*) c')->groupBy('current_version')->pluck('c', 'v'),
            'keyReady' => AgentProtocol::validPublicKey((string)config('workstations.public_key')),
            'actions' => array_keys((array)config('workstations.post_update_actions')),
        ]);
    }

    public function store(Request $r)
    {
        $r->validate(['binary' => 'required|file', 'version' => 'required|string|max:32',
            'signature' => 'required|string|max:128', 'type' => 'required|in:binary,package',
            'post_update' => 'nullable|string|max:64', 'notes' => 'nullable|string|max:2000'],
            ['binary.required' => 'Choose a file.', 'signature.required' => 'Signature is required.']);
        try {
            $rel = ReleasePublisher::publish($r->input('version'), $r->file('binary')->getRealPath(), $r->input('signature'),
                $r->input('type'), $r->input('post_update'), $r->input('notes'), $r->boolean('make_latest'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['binary' => $e->getMessage()])->withInput();
        }
        return redirect()->route('wsm.releases')->with('ok', "v{$rel->version} uploaded" .
            ($rel->is_latest ? ' and set as latest. Workstations with auto-update on will install it.' : '.'));
    }

    public function action(Request $r, Release $release)
    {
        switch ($r->input('action')) {
            case 'latest':
                try {
                    ReleasePublisher::makeLatest($release);
                } catch (InvalidArgumentException $e) {
                    return back()->with('error', $e->getMessage());
                }
                return back()->with('ok', "v{$release->version} is now the latest.");
            case 'yank':
                ReleasePublisher::yank($release);
                return back()->with('ok', "v{$release->version} yanked — no workstation will install it.");
            case 'unyank':
                ReleasePublisher::yank($release, false);
                return back()->with('ok', "v{$release->version} is available again.");
        }
        return back()->with('error', 'Unknown action.');
    }
}
