<?php

namespace App\Workstations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/** HTTPS + optional IP allowlist for every admin portal page (including the login page). */
class PortalGuard
{
    public function handle(Request $r, Closure $next)
    {
        $allowed = (array)config('workstations.allowed_ips');
        if ($allowed && !IpUtils::checkIp((string)$r->ip(), $allowed)) {
            abort(403, 'Is IP se Workstation Manager allowed nahi hai.');
        }
        if (config('workstations.require_https') && !$r->secure()) {
            return redirect()->secure($r->getRequestUri(), 301);
        }
        return $next($r);
    }
}
