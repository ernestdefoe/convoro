<?php

namespace App\Http\Middleware;

use App\Models\IpBan;
use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests from banned IP addresses, and logs out / 403s banned accounts.
 * Read-only browsing is still blocked for banned IPs so they can't lurk + re-register.
 */
class BlockBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        // Before the site is installed there is no configured database (and no
        // bans to enforce). Skip the ip_bans lookup so a freshly-unzipped site
        // can render the web installer instead of 500ing on it.
        if (! Installer::isInstalled()) {
            return $next($request);
        }

        if (IpBan::isBanned($request->ip())) {
            abort(403, __('Access from your network has been restricted.'));
        }

        $user = $request->user();
        if ($user && $user->isBanned()) {
            // Let them sign out, but nothing else.
            if (! $request->routeIs('logout') && ! $request->is('logout')) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                abort(403, __('Your account has been suspended.'));
            }
        }

        return $next($request);
    }
}
