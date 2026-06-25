<?php

/**
 * Convoro setup preflight — run by `composer setup` before migrate/build.
 *
 * Makes a first install fail *clearly* instead of with a cryptic stack trace:
 *  - creates .env from .env.example when missing,
 *  - generates APP_KEY only when it's empty (never clobbers an existing key,
 *    and uses --force so it doesn't stall on the production confirmation),
 *  - stops with guidance when the database isn't configured yet,
 *  - stops with guidance when Node.js / npm (needed to build the frontend)
 *    isn't installed.
 *
 * It deliberately avoids booting the framework except for `key:generate`, so it
 * still works before the app is runnable.
 */

chdir(dirname(__DIR__));

$tty = function_exists('stream_isatty') && @stream_isatty(STDERR);
$c = fn (string $code, string $s) => $tty ? "\033[{$code}m{$s}\033[0m" : $s;

function line(string $s): void
{
    fwrite(STDOUT, $s."\n");
}
$ok = fn (string $s) => line('  '.$c('32', '✓').'  '.$s);
$fail = function (string $s) use ($c): void {
    fwrite(STDERR, "\n  ".$c('31', '✗').'  '.$s."\n\n");
    exit(1);
};

// 1. Ensure .env exists.
if (! file_exists('.env')) {
    if (! file_exists('.env.example')) {
        $fail('No .env or .env.example found — are you in the project root?');
    }
    copy('.env.example', '.env');
    $ok('Created .env from .env.example');
}

$env = (string) file_get_contents('.env');
$val = function (string $key) use ($env): string {
    return preg_match('/^'.preg_quote($key, '/').'=("?)(.*?)\1\s*$/m', $env, $m) ? trim($m[2]) : '';
};

// 2. APP_KEY — generate only when missing (re-running setup must not change it).
if ($val('APP_KEY') === '') {
    passthru(escapeshellarg(PHP_BINARY).' artisan key:generate --force --ansi', $code);
    if ($code !== 0) {
        $fail('Could not generate the app key. Run: php artisan key:generate --force');
    }
} else {
    $ok('APP_KEY already set');
}

// 3. Database configured? Stop here (clear message) rather than at the SQL layer.
if ($val('DB_CONNECTION') !== 'sqlite' && ($val('DB_DATABASE') === '' || $val('DB_USERNAME') === '')) {
    $fail("Your database isn't configured yet.\n".
        '      Set '.$c('1', 'DB_DATABASE').', '.$c('1', 'DB_USERNAME').' and '.$c('1', 'DB_PASSWORD').' in .env, then re-run:'."\n".
        '      '.$c('1', 'composer setup'));
}
$ok('Database configured');

// 4. Node.js / npm present? The build steps need it.
$npm = trim((string) @shell_exec('command -v npm 2>/dev/null'));
if ($npm === '') {
    $npm = trim((string) @shell_exec('where npm 2>NUL')); // Windows fallback
}
if ($npm === '') {
    $fail("Node.js / npm not found — it's needed to build the frontend.\n".
        '      Install '.$c('1', 'Node 18+').' (https://nodejs.org or your package manager), then re-run:'."\n".
        '      '.$c('1', 'composer setup'));
}
$ok('Node.js / npm found');

line('  '.$c('32', '✓').'  Preflight passed — migrating and building…');
