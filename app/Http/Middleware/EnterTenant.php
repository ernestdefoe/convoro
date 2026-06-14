<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * POC — when the signed-in owner has "entered" one of their hosted communities
 * (set from the panel), serve the WHOLE forum from that community's own
 * database for the request and act as its admin. The control-plane panel itself
 * always stays on the central database. Local convoro.test uses file sessions +
 * cache, so swapping the default DB connection here is side-effect-free.
 */
class EnterTenant
{
    public function handle(Request $request, Closure $next)
    {
        // POC only — inert unless the managed-hosting panel is enabled.
        if (! config('convoro.hosting')) {
            return $next($request);
        }

        // The panel (and its actions) are the control plane — always central.
        if ($request->is('panel') || $request->is('panel/*')) {
            return $next($request);
        }

        $id = $request->session()->get('tenant_id');
        if (! $id) {
            return $next($request);
        }

        $tenant = Tenant::find($id); // central connection (not yet swapped)
        if (! $tenant || $tenant->status !== 'active') {
            $request->session()->forget(['tenant_id', 'tenant_email']);

            return $next($request);
        }

        // Point the default connection at the tenant's own database.
        $conn = config('database.default');
        config(["database.connections.{$conn}.database" => $tenant->database]);
        DB::purge($conn);

        // Act as the matching user inside the tenant (POC: matched by email — in
        // production this is the OIDC "Sign in with Convoro" identity).
        $email = $request->session()->get('tenant_email');
        $user = $email ? User::where('email', $email)->first() : null;
        if ($user) {
            Auth::setUser($user);
        }

        // Expose to the layout (banner) without re-querying the central DB.
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
