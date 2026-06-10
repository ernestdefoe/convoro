<?php

namespace Convoro\Ext\Horizon;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Convoro wrapper around Laravel Horizon. Horizon itself ships with Convoro
 * core; this extension is the installable, discoverable representation of it —
 * it authorizes the /horizon dashboard for Convoro admins and links to it from
 * the Marketplace (manifest admin_url). On a VPS you run `php artisan horizon`
 * (e.g. a systemd service) and point your queue at Redis.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Authorize the Horizon dashboard for Convoro admins while enabled.
        Gate::define('viewHorizon', fn ($user = null) => (bool) optional($user)->is_admin);
    }
}
