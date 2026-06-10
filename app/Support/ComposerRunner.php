<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs Composer in-process so the Marketplace can install/remove extensions on
 * hosts that have Composer but no SSH (control-panel task runners, etc.) — the
 * way Flarum's Extension Manager works. This is the companion to the
 * zip-upload path; neither requires a terminal.
 */
class ComposerRunner
{
    /** Locate a runnable Composer command, or null if none is available. */
    public static function command(): ?array
    {
        // A bare `composer` on PATH.
        $finder = new ExecutableFinder;
        if ($bin = $finder->find('composer')) {
            return [$bin];
        }

        // A composer.phar shipped beside the app or in common spots.
        foreach ([base_path('composer.phar'), '/usr/local/bin/composer', '/usr/bin/composer'] as $phar) {
            if (is_file($phar)) {
                return str_ends_with($phar, '.phar') ? [PHP_BINARY, $phar] : [$phar];
            }
        }

        return null;
    }

    public static function available(): bool
    {
        return self::command() !== null;
    }

    /**
     * Run a composer subcommand in the app root. Returns [ok, output].
     *
     * @param  string[]  $args  e.g. ['require', 'vendor/pkg', '--no-interaction']
     */
    public static function run(array $args, ?callable $onOutput = null, int $timeout = 600): array
    {
        $cmd = self::command();
        if (! $cmd) {
            return [false, 'Composer is not available on this server.'];
        }

        $process = new Process(
            array_merge($cmd, $args),
            base_path(),
            [
                // Composer needs a writable HOME; keep it inside storage so it
                // works under php-fpm where $HOME may be unset/unwritable.
                'COMPOSER_HOME' => storage_path('app/composer'),
                'COMPOSER_NO_INTERACTION' => '1',
                'COMPOSER_MEMORY_LIMIT' => '-1',
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            ],
            null,
            $timeout,
        );

        @mkdir(storage_path('app/composer'), 0775, true);

        $buffer = '';
        $process->run(function ($type, $chunk) use (&$buffer, $onOutput) {
            $buffer .= $chunk;
            if ($onOutput) {
                $onOutput($chunk);
            }
        });

        return [$process->isSuccessful(), trim($buffer)];
    }

    public static function version(): ?string
    {
        if (! self::available()) {
            return null;
        }
        [$ok, $out] = self::run(['--version'], null, 30);

        return $ok && preg_match('/Composer(?: version)? (\S+)/', $out, $m) ? $m[1] : ($ok ? trim($out) : null);
    }
}
