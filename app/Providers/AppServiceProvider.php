<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

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
