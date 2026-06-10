<?php

namespace App\Providers;

use App\Support\Installer;
use Illuminate\Support\ServiceProvider;

/**
 * Runs first. Ensures a freshly unzipped Convoro can serve the web installer:
 * generates an APP_KEY before the encrypter is resolved (cookie/session
 * middleware) so the install page renders without any shell command.
 */
class InstallServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            Installer::ensureAppKey();
        } catch (\Throwable) {
            // Non-writable root → requirements step will flag it.
        }
    }
}
