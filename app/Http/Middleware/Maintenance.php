<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintenance mode. When enabled (admin setting), every visitor sees a
 * maintenance page EXCEPT admins (who keep full access so they can work + turn
 * it back off) and a small allow-list so staff can still authenticate and
 * static assets keep loading. Runs in the web group after the session starts,
 * so $request->user() and the CSRF cookie are available.
 */
class Maintenance
{
    /** Paths that stay reachable while in maintenance (so an admin can log in). */
    private const ALLOW = [
        'login', 'logout', 'register', 'forgot-password', 'reset-password', 'up', 'sw.js', 'favicon.ico',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Settings::get('maintenance.enabled', false)) {
            return $next($request);
        }

        // Admins bypass entirely.
        if ($request->user()?->is_admin) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');
        if ($path === '' ? false : (
            in_array($path, self::ALLOW, true)
            || str_starts_with($path, 'build/')
            || str_starts_with($path, 'assets/')
            || str_starts_with($path, 'storage/')
            || str_starts_with($path, 'auth/')
            || str_starts_with($path, '.well-known/')
        )) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => (string) Settings::get('maintenance.message', ''),
            'site' => (string) Settings::get('site.name', 'The forum'),
            'logo' => (string) Settings::get('site.logo', ''),
        ], 503)->header('Retry-After', '3600');
    }
}
