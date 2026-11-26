<?php

namespace App\Workstations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function show(Request $r)
    {
        if ($r->session()->get('wsm_admin')) {
            return redirect()->route('wsm.index');
        }
        return view('workstations::login', ['configured' => (string)config('workstations.admin_pass') !== '']);
    }

    public function login(Request $r)
    {
        $pass = (string)config('workstations.admin_pass');
        $ok = $pass !== ''
            && hash_equals((string)config('workstations.admin_user'), (string)$r->input('username'))
            && hash_equals($pass, (string)$r->input('password'));
        if (!$ok) {
            return back()->withInput($r->only('username'))->withErrors(['login' => 'Username ya password galat hai.']);
        }
        $r->session()->regenerate();
        $r->session()->put('wsm_admin', true);
        return redirect()->intended(route('wsm.index'));
    }

    public function logout(Request $r)
    {
        $r->session()->forget('wsm_admin');
        $r->session()->regenerateToken();
        return redirect()->route('wsm.login')->with('ok', 'Aap logout ho gaye.');
    }
}
