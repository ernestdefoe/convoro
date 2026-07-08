<?php

namespace App\Support;

/**
 * Asks the host to restart this install's long-lived processes — the Octane
 * worker(s) and the SSR sidecar — after a change that swaps code out from under
 * them: a self-update, or an extension enable/install/uninstall. Those processes
 * hold the OLD code (routes, providers, compiled Vue) in memory, and the web
 * user can't `systemctl restart` them, so an update or Marketplace install would
 * silently "not take" until someone restarted them by hand.
 *
 * We drop a marker file a root-owned systemd **path unit** watches (see
 * deploy/octane-restart/), which then restarts that install's `*-octane` /
 * `*-ssr` units and removes the marker. No-op-safe everywhere: on installs
 * without the watcher — shared hosting / plain PHP-FPM, where `opcache_reset()`
 * already reloads code and there's no long-lived worker — the marker is simply
 * never consumed (harmless single file in storage/).
 */
class ServiceRestart
{
    /** Path (under the protected storage/ tree) the host watcher looks for. */
    public const FLAG = 'app/services-restart.flag';

    public static function request(): void
    {
        try {
            @file_put_contents(storage_path(self::FLAG), (string) time());
        } catch (\Throwable) {
            // Best-effort: a restart request must never break the operation that
            // asked for it.
        }
    }
}
