<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * One place to invalidate every server-side cache after code changes, so the
 * new code is actually served.
 *
 * This matters because PHP **OPcache is enabled in production** (Convoro runs
 * its own FPM pool with opcache on — see deploy/DEPLOYMENT.md). OPcache keeps a
 * compiled-bytecode copy of every PHP file in shared memory; when files change
 * underneath it, FPM workers keep executing the OLD bytecode until OPcache
 * notices. We therefore reset it explicitly whenever code is added or replaced
 * (extension install/enable/disable/uninstall, in-app core update).
 *
 * `opcache_reset()` only affects the OPcache of the SAPI it runs in:
 *   - Called from a web request (FPM)  → resets the production OPcache. ✅
 *   - Called from CLI / a queue worker → resets the (disabled) CLI OPcache,
 *     i.e. a no-op for FPM. In that case FPM still self-heals within
 *     `opcache.revalidate_freq` seconds because `opcache.validate_timestamps`
 *     is on; for an immediate reset from the shell, reload php-fpm instead
 *     (`systemctl reload php8.5-fpm` — what scripts/deploy.sh does).
 */
class Caches
{
    /**
     * Clear app + extension caches and reset OPcache. Call this after any
     * change to PHP code, routes, config, views, or the set of enabled
     * extensions. Safe to call from a web request or the CLI.
     */
    public static function bust(): void
    {
        ExtensionManager::flushCache();

        try {
            // Clears compiled config/route/view/event caches so changed
            // providers, routes and Blade views are picked up.
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // Caching may not be configured (e.g. local dev) — ignore.
        }

        self::resetOpcache();

        // opcache_reset() reloads bytecode for PHP-FPM, but long-lived Octane
        // workers + the SSR sidecar keep the OLD code (routes, providers, Vue) in
        // memory. Ask the host to restart them so an extension enable/install/
        // uninstall actually takes effect (no-op where there's no watcher).
        ServiceRestart::request();
    }

    /**
     * Reset the PHP OPcache for the current worker pool. No-op when OPcache is
     * disabled (e.g. the CLI SAPI) or the api is restricted.
     */
    public static function resetOpcache(): void
    {
        if (function_exists('opcache_reset') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)) {
            @opcache_reset();
        }
    }
}
