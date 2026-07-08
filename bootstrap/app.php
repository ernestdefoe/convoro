<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust reverse proxies (Caddy, nginx, FrankenPHP, Cloudflare, a load
        // balancer …) so X-Forwarded-Proto/Host are honoured. Without this, a
        // TLS terminator in front of the app makes Laravel emit http:// asset
        // URLs on an https page → mixed-content block → blank screen. Defaults
        // to all (a self-hosted forum is almost always behind a proxy); set
        // TRUSTED_PROXIES to a comma-separated IP/CIDR list to restrict.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*') === '*' ? '*' : array_map('trim', explode(',', (string) env('TRUSTED_PROXIES'))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(prepend: [
            \App\Http\Middleware\EnsureInstalled::class,
            // Must run before StartSession so a demo subdomain gets its own
            // session cookie + database before either is touched.
            \App\Http\Middleware\HostTenant::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnterTenant::class,
            \App\Http\Middleware\BlockBanned::class,
            \App\Http\Middleware\Maintenance::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\TrackLastSeen::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'store.owner' => \App\Http\Middleware\EnsureStoreOwner::class,
        ]);

        // Server-to-server endpoints (Stripe webhook, remote license checks,
        // ActivityPub inbox) carry no CSRF token.
        $middleware->validateCsrfTokens(except: [
            'store/webhook',
            'api/licenses/*',
            'api/registry/github',
            'federation/inbox',
            'u/*/inbox',
            'mail/inbound',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
