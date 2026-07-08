<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use ZipArchive;

/**
 * Applies a software update from a pre-built release archive (.zip) advertised by
 * the update feed. Downloads → extracts → copies app files over (preserving .env,
 * storage, uploads) → migrates → rebuilds caches. Works without Composer/Node on
 * the server (the archive bundles vendor/ + built assets) — the shared-hosting path.
 */
class Updater
{
    /** Paths (relative to base) never overwritten by an update. */
    private const PROTECTED = ['.env', 'storage', 'public/storage', 'public/releases', 'public/update-feed.json', 'bootstrap/cache'];

    /** Detail of the last copy failure, surfaced in the user-facing message. */
    private static ?string $copyError = null;

    /**
     * @return array{ok:bool, message:string, version?:string}
     */
    public static function apply(): array
    {
        @set_time_limit(0);

        if (! class_exists(ZipArchive::class)) {
            return ['ok' => false, 'message' => 'The PHP zip extension is required to apply updates.'];
        }

        $feedUrl = config('convoro.update_url');
        if (! $feedUrl) {
            return ['ok' => false, 'message' => 'No update feed configured (CONVORO_UPDATE_URL).'];
        }

        // 1. Read the feed for the download URL + version.
        try {
            $feed = Http::timeout(15)->acceptJson()->get($feedUrl)->throw()->json();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reach the update feed.'];
        }
        $download = $feed['download'] ?? null;
        $version = (string) ($feed['version'] ?? '');
        if (! $download) {
            return ['ok' => false, 'message' => 'The update feed has no download URL.'];
        }
        if ($version !== '' && ! version_compare($version, (string) config('convoro.version'), '>')) {
            return ['ok' => false, 'message' => 'Already up to date.'];
        }

        $work = storage_path('app/updates');
        File::ensureDirectoryExists($work);
        $zipPath = $work.'/release.zip';
        $extract = $work.'/extract';
        File::deleteDirectory($extract);
        File::ensureDirectoryExists($extract);

        // 2. Download the archive to disk (streamed).
        try {
            Http::timeout(300)->sink($zipPath)->get($download)->throw();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Failed to download the update archive.'];
        }

        // 3. Extract.
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'message' => 'The downloaded archive is not a valid zip.'];
        }
        $zip->extractTo($extract);
        $zip->close();

        // Some archives wrap everything in a single top-level folder.
        $root = $extract;
        $entries = array_values(array_diff(scandir($extract) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($extract.'/'.$entries[0]) && ! file_exists($extract.'/artisan')) {
            $root = $extract.'/'.$entries[0];
        }
        if (! file_exists($root.'/artisan')) {
            return ['ok' => false, 'message' => 'Archive does not look like a Convoro release (no artisan).'];
        }

        // 4. Strip protected paths from the extracted copy so they are never overwritten.
        foreach (self::PROTECTED as $p) {
            File::exists($root.'/'.$p) && (is_dir($root.'/'.$p) ? File::deleteDirectory($root.'/'.$p) : File::delete($root.'/'.$p));
        }

        // 5. Copy the new files over the live install.
        if (! self::copyOver($root, base_path())) {
            return ['ok' => false, 'message' => self::copyFailureMessage(base_path())];
        }

        // 6. Post-update: migrate + rebuild caches + bust opcache.
        File::deleteDirectory($work);
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            // NOTE: intentionally NOT route:cache — enabled extensions register
            // closure-based routes, which can't be safely route-cached
            // (serializable-closure errors at render time). Routes stay uncached.

            // Tell long-running queue workers to reload — otherwise they keep
            // executing the OLD code (including this updater) until restarted.
            Artisan::call('queue:restart');
        } catch (\Throwable $e) {
            // non-fatal; files are in place
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // Long-lived Octane workers + the SSR sidecar still hold the pre-update
        // code (and Vue tree) in memory — ask the host to restart them so the new
        // version is actually served (no-op where there's no watcher).
        ServiceRestart::request();

        Settings::setMany([
            'update.available' => false,
            'update.latest' => $version ?: config('convoro.version'),
            'update.checked_at' => now()->toDateTimeString(),
        ]);

        return ['ok' => true, 'message' => 'Updated to '.($version ?: 'the latest version').'.', 'version' => $version];
    }

    /** Copy src contents into dest. Prefer fast native copy; fall back to pure PHP. */
    private static function copyOver(string $src, string $dest): bool
    {
        self::$copyError = null;

        if (function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            $out = [];
            $code = 0;
            exec('cp -a '.escapeshellarg($src).'/. '.escapeshellarg($dest).'/ 2>&1', $out, $code);
            if ($code === 0) {
                return true;
            }
            // Keep the last couple of lines of cp's own error for the message.
            self::$copyError = trim(implode(' | ', array_slice(array_values(array_filter($out)), -2)));
        }

        // Pure-PHP recursive copy (shared-hosting fallback, no shell).
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $ok = true;
        foreach ($iter as $item) {
            $target = $dest.DIRECTORY_SEPARATOR.$iter->getSubPathname();
            if ($item->isDir()) {
                File::ensureDirectoryExists($target);
            } elseif (! self::put($item->getPathname(), $target)) {
                $ok = false;
                self::$copyError ??= 'could not write '.$iter->getSubPathname();
            }
        }

        return $ok;
    }

    /**
     * A clear, actionable message when the copy fails — almost always a
     * permissions problem: some files (typically vendor/) are owned by a
     * different user than the web server (e.g. from a `sudo composer install`),
     * so the web user can't replace them. Names the culprit + the exact fix.
     */
    private static function copyFailureMessage(string $base): string
    {
        $user = self::webUser();
        $culprit = self::firstUnwritable($base);
        $msg = 'Could not replace the application files';
        if ($culprit !== null) {
            $msg .= " — “{$culprit}” isn’t writable by the web user ({$user})";
        }
        $msg .= '. This usually means some files are owned by a different user (often root, from running composer with sudo). '
              .'Give the web user ownership and update again:  chown -R '.$user.' '.$base;
        if (self::$copyError) {
            $msg .= '  ['.self::$copyError.']';
        }

        return $msg;
    }

    /** Best-effort OS user the web process runs as, for chown guidance. */
    private static function webUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $u = @posix_getpwuid(posix_geteuid());
            if (! empty($u['name'])) {
                return $u['name'];
            }
        }

        return get_current_user() ?: 'the web user';
    }

    /**
     * First path (relative to base) an update must overwrite but the web user
     * can't — the dirs a release ships, then a shallow scan of vendor/ (the usual
     * root-owned culprit). Null when everything is writable. Used both to explain
     * a failure and as a pre-flight check so the problem is caught up front.
     */
    public static function firstUnwritable(string $base): ?string
    {
        foreach (['vendor', 'app', 'config', 'public', 'bootstrap', 'resources', 'routes'] as $d) {
            $p = $base.'/'.$d;
            if (is_dir($p) && ! is_writable($p)) {
                return $d;
            }
        }
        foreach (glob($base.'/vendor/*/*', GLOB_ONLYDIR) ?: [] as $p) {
            if (! is_writable($p)) {
                return 'vendor/'.basename(dirname($p)).'/'.basename($p);
            }
        }

        return null;
    }

    /**
     * Copy one file, recovering from an existing dest that isn't writable
     * (e.g. left with foreign ownership/permissions) by chmod-ing then
     * removing it before the final attempt. Prevents one stubborn file from
     * aborting the whole update.
     */
    private static function put(string $src, string $target): bool
    {
        if (@copy($src, $target)) {
            return true;
        }
        @chmod($target, 0664);
        if (@copy($src, $target)) {
            return true;
        }
        @unlink($target);

        return @copy($src, $target);
    }
}
