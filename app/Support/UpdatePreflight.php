<?php

namespace App\Support;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Pre-flight checks for the in-app updater, plus the machinery to apply an update
 * WITHOUT a long-running queue worker. The old flow dispatched a queued job — if
 * nothing was consuming the queue (no queue:work / Horizon) the update silently
 * hung forever. Instead we run `php artisan convoro:update` as a detached
 * background process when the host allows it, and otherwise tell the admin the
 * exact command to run by hand.
 */
class UpdatePreflight
{
    private const NEED_BYTES = 600 * 1024 * 1024; // headroom for download + extract + copy

    /** Locate a PHP CLI binary to run artisan with (null if not found). */
    public static function phpBinary(): ?string
    {
        try {
            $finder = new PhpExecutableFinder;

            return $finder->find() ?: ($finder->find(false) ?: null);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Is exec() available (not disabled in php.ini)? */
    public static function execAvailable(): bool
    {
        if (! function_exists('exec') || ! function_exists('popen')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('exec', $disabled, true) && ! in_array('popen', $disabled, true);
    }

    /** Can we apply the update in the background ourselves (no worker needed)? */
    public static function canRunDetached(): bool
    {
        return self::execAvailable() && self::phpBinary() !== null;
    }

    /** Spawn `convoro:update` as a detached background process. Returns false if it couldn't. */
    public static function runDetached(): bool
    {
        if (! self::canRunDetached()) {
            return false;
        }

        $php = self::phpBinary();
        $artisan = base_path('artisan');
        $log = storage_path('logs/update.log');

        try {
            if (stripos(PHP_OS, 'WIN') === 0) {
                $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan).' convoro:update > '.escapeshellarg($log).' 2>&1';
                $h = popen($cmd, 'r');
                if ($h !== false) {
                    pclose($h);
                }
            } else {
                $cmd = 'nohup '.escapeshellarg($php).' '.escapeshellarg($artisan).' convoro:update > '.escapeshellarg($log).' 2>&1 &';
                @exec($cmd);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Full environment checklist for the admin UI.
     *
     * @return array<int, array{key:string, label:string, ok:bool, detail:string}>
     */
    public static function checks(): array
    {
        $base = base_path();
        $free = @disk_free_space($base) ?: 0;
        $freeGb = round($free / 1073741824, 1);
        $hasFeed = (bool) config('convoro.update_url');
        $hasZip = extension_loaded('zip');
        $writable = is_writable($base);
        $enoughDisk = $free >= self::NEED_BYTES;
        $auto = self::canRunDetached();

        return [
            ['key' => 'feed', 'label' => 'Update source configured', 'ok' => $hasFeed,
                'detail' => $hasFeed ? '' : 'Set CONVORO_UPDATE_URL in your .env file.'],
            ['key' => 'zip', 'label' => 'PHP zip extension', 'ok' => $hasZip,
                'detail' => $hasZip ? '' : 'Required to extract the release archive.'],
            ['key' => 'writable', 'label' => 'Application files writable', 'ok' => $writable,
                'detail' => $writable ? '' : 'The web user needs write access to the project directory.'],
            ['key' => 'disk', 'label' => 'Free disk space', 'ok' => $enoughDisk,
                'detail' => $enoughDisk ? $freeGb.' GB free' : 'Low disk space — only '.$freeGb.' GB free.'],
            ['key' => 'runner', 'label' => 'Automatic install', 'ok' => $auto,
                'detail' => $auto
                    ? 'Runs in the background — no queue worker needed.'
                    : 'This server can\'t self-run updates — use the terminal command below.'],
        ];
    }

    /** Hard blockers that must pass before an update can even be attempted. */
    public static function blockers(): array
    {
        return array_values(array_filter(
            self::checks(),
            fn ($c) => in_array($c['key'], ['feed', 'zip', 'writable', 'disk'], true) && ! $c['ok'],
        ));
    }
}
