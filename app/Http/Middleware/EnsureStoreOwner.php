<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the seller/store admin (Stripe, products, licenses, catalog linking) to
 * the central Convoro storefront only. Ordinary installs leave
 * CONVORO_STORE_OWNER off and never see it — they only consume the catalog
 * through the extension Marketplace.
 */
class EnsureStoreOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('convoro.store_owner'), 404);

        return $next($request);
    }
}
