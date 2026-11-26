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
            ['binary.required' => 'File chunein.', 'signature.required' => 'Signature zaroori hai.']);
        try {
            $rel = ReleasePublisher::publish($r->input('version'), $r->file('binary')->getRealPath(), $r->input('signature'),
                $r->input('type'), $r->input('post_update'), $r->input('notes'), $r->boolean('make_latest'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['binary' => $e->getMessage()])->withInput();
        }
        return redirect()->route('wsm.releases')->with('ok', "v{$rel->version} upload ho gaya" .
            ($rel->is_latest ? ' aur latest set ho gaya. Auto-update wale workstations ise install kar lenge.' : '.'));
    }

    public function action(Request $r, Release $release)
    {
        switch ($r->input('action')) {
            case 'latest':
                if (!AgentProtocol::verifySignature((string)config('workstations.public_key'), $release->version, $release->sha256, $release->signature)) {
                    return back()->with('error', 'Is release ka signature current key se match nahi karta — latest nahi bana sakte.');
                }
                ReleasePublisher::makeLatest($release);
                return back()->with('ok', "v{$release->version} ab latest hai.");
            case 'yank':
                ReleasePublisher::yank($release);
                return back()->with('ok', "v{$release->version} yank ho gaya — ab koi ise install nahi karega.");
            case 'unyank':
                ReleasePublisher::yank($release, false);
                return back()->with('ok', "v{$release->version} wapas available hai.");
        }
        return back()->with('error', 'Anjaan action.');
    }
}
