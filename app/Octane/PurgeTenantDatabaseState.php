<?php

namespace App\Octane;

/**
 * Octane safety net for the tenant middleware (EnterTenant / HostTenant).
 *
 * Config mutations are already per-request-safe under Octane (the config
 * repository is sandboxed per request), but the DatabaseManager is shared by
 * the whole worker and its connection objects OUTLIVE the request. A tenant
 * request re-points the default (or `demo`) connection at the tenant's
 * database; without this listener the NEXT request on the same worker would
 * happily keep reading the tenant's data over that stale connection.
 *
 * After any request that entered a tenant, purge the connections it may have
 * re-pointed — the next request then reconnects from its own pristine
 * sandboxed config. Costs one reconnect after tenant requests, nothing else.
 *
 * (Prod defense-in-depth: demo vhosts are routed to PHP-FPM, not Octane, so
 * this listener is the belt, not the suspenders.)
 */
class PurgeTenantDatabaseState
{
    public function handle($event): void
    {
        // Both middleware bind currentTenant when (and only when) they swap DBs.
        if (! $event->sandbox->bound('currentTenant')) {
            return;
        }

        $db = $event->sandbox->make('db');
        // $event->app is the worker's BASE container — its config is pristine,
        // so this is the true central connection name even though the sandbox
        // config was mutated (e.g. database.default => 'demo').
        $central = (string) $event->app->make('config')->get('database.default');

        foreach (array_unique(array_filter(['demo', $central])) as $name) {
            try {
                $db->purge($name);
            } catch (\Throwable) {
                // A connection that never opened has nothing to purge.
            }
        }
    }
}
