<?php

namespace App\Providers;

use App\Models\Post;
use App\Observers\PostObserver;
use App\Support\MailConfig;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Cache the (expensive) Inertia SSR render of the static-for-everyone
        // marketing pages. Overrides Inertia's default Gateway binding; the
        // kill-switch lets it be disabled without a code change.
        $this->app->bind(\Inertia\Ssr\Gateway::class, function () {
            $http = new \Inertia\Ssr\HttpGateway;

            return config('convoro.ssr_cache', true)
                ? new \App\Support\CachingSsrGateway($http)
                : $http;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Admin-rotated VAPID keys (if any) override the .env defaults.
        \App\Support\WebPush::apply();

        // Keep the "Ask Convoro" semantic index fresh as posts change.
        Post::observe(PostObserver::class);

        // Apply admin-configured mail settings over .env (no-op until configured).
        // Guarded: a not-yet-installed box has no DB, so reading settings here
        // would otherwise fatal before the installer can run.
        try {
            MailConfig::apply();
        } catch (\Throwable) {
            // ignore — fresh install / DB unavailable
        }
    }
}
