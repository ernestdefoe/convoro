<?php

namespace App\Support\Importers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Shared helpers for the source-DB importers (connection, colour, dates, passwords). */
class Src
{
    public const CONN = 'import_src';

    /** Build a runtime read connection to the source database (MySQL or, for Discourse, PostgreSQL). */
    public static function connect(array $cfg)
    {
        $driver = ($cfg['driver'] ?? 'mysql') === 'pgsql' ? 'pgsql' : 'mysql';
        $conf = [
            'driver' => $driver,
            'host' => $cfg['host'] ?: '127.0.0.1',
            'port' => (int) ($cfg['port'] ?: ($driver === 'pgsql' ? 5432 : 3306)),
            'database' => $cfg['database'] ?? '',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
            'prefix' => '',
            'options' => [\PDO::ATTR_TIMEOUT => 8],
        ];
        if ($driver === 'pgsql') {
            $conf['charset'] = 'utf8';
            $conf['search_path'] = 'public';
            $conf['sslmode'] = 'prefer';
        } else {
            $conf['charset'] = 'utf8mb4';
            $conf['collation'] = 'utf8mb4_unicode_ci';
        }
        config(['database.connections.'.self::CONN => $conf]);
        DB::purge(self::CONN);

        return DB::connection(self::CONN);
    }

    public static function color(?string $c): string
    {
        $c = trim((string) $c);

        return preg_match('/^#?[0-9a-fA-F]{6}$/', $c) ? (str_starts_with($c, '#') ? $c : '#'.$c) : '#5b5bd6';
    }

    /** Copy bcrypt hashes (work straight away); anything else → random (user resets). */
    public static function password(?string $hash): string
    {
        $hash = (string) $hash;

        return preg_match('#^\$2[aby]\$#', $hash) ? $hash : bcrypt(Str::random(24));
    }

    /** Unix timestamp or datetime string → Carbon. */
    public static function ts($v): Carbon
    {
        if ($v === null || $v === '') {
            return now();
        }
        if (is_numeric($v)) {
            return Carbon::createFromTimestamp((int) $v);
        }
        try {
            return Carbon::parse($v);
        } catch (\Throwable) {
            return now();
        }
    }

    /** Sanitise already-HTML content (e.g. Discourse `cooked`). */
    public static function sanitizeHtml(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<(iframe|object|embed|style|link|meta)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(iframe|object|embed|style|link|meta)\b[^>]*/?>#is', '', $html) ?? $html;
        $html = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $html) ?? $html;
        $html = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $html) ?? $html;
        $html = preg_replace('#(href|src)\s*=\s*"\s*javascript:[^"]*"#i', '$1="#"', $html) ?? $html;

        return trim($html);
    }

    /** Unique category slug from a name. */
    public static function catSlug(string $name, int $sourceId): string
    {
        return (Str::slug($name) ?: 'category').'-'.$sourceId;
    }
}
