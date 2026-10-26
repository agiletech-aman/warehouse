<?php

namespace App\Fleet\Http;

use Closure;
use Illuminate\Http\Request;

/** Minimal Basic-auth gate. Swap for the portal's own auth middleware in config/routes if it has one. */
class AdminAuth
{
    public function handle(Request $r, Closure $next)
    {
        $pass = (string)config('fleet.admin_pass');
        if ($pass === '' || !hash_equals(config('fleet.admin_user'), (string)$r->getUser()) || !hash_equals($pass, (string)$r->getPassword())) {
            return response('Fleet login required', 401, ['WWW-Authenticate' => 'Basic realm="Fleet"']);
        }
        return $next($r);
    }
}
