<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Serves on-demand demos from their own subdomain. When the request host is
 * try-xxxx.convoro.co, the whole forum is served from that demo's isolated
 * sqlite database, with a host-only session cookie (so it never shares the
 * community SSO session) and outbound mail forced off.
 *
 * Runs before StartSession so the per-demo session cookie + DB swap take effect
 * before anything reads the session or queries the database.
 */
class HostTenant
{
    public function handle(Request $request, Closure $next)
    {
        if (! preg_match('/^(try-[a-z0-9]+)\.convoro\.co$/i', $request->getHost(), $m)) {
            return $next($request);
        }
        $slug = strtolower($m[1]);

        // Resolve on the central connection (default not yet swapped).
        $tenant = Tenant::where('slug', $slug)->where('kind', 'demo')->first();
        if (! $tenant || $tenant->status !== 'active'
            || ($tenant->expires_at && $tenant->expires_at->isPast())) {
            // Unknown / expired demo → send them to request a fresh one.
            return redirect()->away('https://'.config('convoro.community_domain').'/request-demo');
        }

        // Isolate this demo's session: its own cookie, host-only (the community
        // SSO cookie is scoped to .convoro.co and must not leak in or out).
        config([
            'session.cookie' => 'demo_'.$slug,
            'session.domain' => null,
        ]);

        // Serve the whole app from the demo's own sqlite database.
        config(['database.connections.demo.database' => $tenant->database]);
        config(['database.default' => 'demo']);
        DB::purge('demo');
        DB::setDefaultConnection('demo');

        // A demo can never send real mail.
        config(['mail.default' => 'array']);

        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
