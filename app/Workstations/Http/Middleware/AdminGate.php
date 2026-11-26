<?php

namespace App\Workstations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminGate
{
    public function handle(Request $r, Closure $next)
    {
        if (!$r->session()->get('wsm_admin')) {
            return redirect()->guest(route('wsm.login'));
        }
        return $next($r);
    }
}
