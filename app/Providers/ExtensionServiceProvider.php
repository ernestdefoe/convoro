<?php

namespace App\Providers;

use App\Support\ExtensionManager;
use Illuminate\Support\ServiceProvider;

class ExtensionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Filesystem-only: make extension classes loadable without Composer.
        // Safe this early (no cache/DB touched).
        ExtensionManager::registerAutoloader();
    }

    public function boot(): void
    {
        // Booting reads the enabled set from settings (cache/DB), which is only
        // available by boot(). Registering enabled providers here still runs
        // their register()+boot() in order.
        ExtensionManager::bootEnabled($this->app);
    }
}
