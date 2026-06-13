<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Self-serve database backup + restore for the admin.
 *
 * Driver-aware: MySQL/MariaDB (and wire-compatible servers like Percona) are
 * dumped with mariadb-dump/mysqldump streamed straight into a gzip file — no
 * shell pipe, no temp .sql, so it stays within memory on large databases.
 * SQLite is backed up by gzipping a copy of the database file, which also makes
 * the whole create→restore cycle testable on the local dev box.
 *
 * Backups live on the private 'local' disk under storage/app/backups and, when
 * offsite is enabled and an S3-compatible bucket is configured, are mirrored to
 * the 's3' disk. RESTORE is destructive: it always takes a fresh safety
 * snapshot of the current database first, so a bad restore is reversible.
 */
class Backup
{
    /** Subdirectory (relative to the local disk root) where backups are stored. */
    public const DIR = 'backups';

    /** Prefix used for the automatic snapshot taken right before a restore. */
    public const SAFETY_PREFIX = 'pre-restore';

    /** Where offsite copies live on the s3 disk. */
    public const OFFSITE_DIR = 'convoro-backups';

    // ---- Environment / capability ----

    /** The default connection name (e.g. 'mysql', 'sqlite'). */
    public static function connectionName(): string
    {
        return (string) config('database.default');
    }

    public static function driver(): string
    {
        return (string) config('database.connections.'.self::connectionName().'.driver');
    }

    /** Can a backup actually run here? (sqlite always; mysql needs shell + a dump binary). */
    public static function available(): bool
    {
        $driver = self::driver();
        if ($driver === 'sqlite') {
            return true;
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return self::shellEnabled() && self::dumpBinary() !== null && self::clientBinary() !== null;
        }

        return false;
    }

    /** A human reason when unavailable, for the admin UI. */
    public static function unavailableReason(): ?string
    {
        if (self::available()) {
            return null;
        }
        $driver = self::driver();
        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            return 'Backups support SQLite and MySQL/MariaDB databases; this install uses "'.$driver.'".';
        }
        if (! self::shellEnabled()) {
            return 'This host disables PHP shell execution (exec/proc_open), which is required to dump a MySQL database.';
        }
        if (self::dumpBinary() === null) {
            return 'Could not find mariadb-dump or mysqldump on this host.';
        }
        if (self::clientBinary() === null) {
            return 'Could not find the mariadb/mysql client on this host (needed for restore).';
        }

        return 'Backups are unavailable on this host.';
    }

    private static function shellEnabled(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }

    private static function dumpBinary(): ?string
    {
        return (new ExecutableFinder)->find('mariadb-dump')
            ?? (new ExecutableFinder)->find('mysqldump');
    }

    private static function clientBinary(): ?string
    {
        return (new ExecutableFinder)->find('mariadb')
            ?? (new ExecutableFinder)->find('mysql');
    }

    // ---- Offsite ----

    public static function offsiteConfigured(): bool
    {
        return (bool) config('filesystems.disks.s3.bucket');
    }

    public static function offsiteEnabled(): bool
    {
        return self::offsiteConfigured() && (bool) Settings::get('backups.offsite', false);
    }

    // ---- Create ----

    /**
     * Create a backup. Returns metadata for the stored file.
     *
     * @return array{name:string,size:int,offsite:bool}
     */
    public static function create(string $prefix = 'manual'): array
    {
        if (! self::available()) {
            throw new \RuntimeException(self::unavailableReason() ?? 'Backups are unavailable on this host.');
        }

        self::ensureDir();
        $stamp = now()->format('Y-m-d_His');
        $driver = self::driver();
        $ext = $driver === 'sqlite' ? 'sqlite.gz' : 'sql.gz';
        $name = self::sanitize($prefix).'-'.$stamp.'.'.$ext;
        $abs = self::absolute($name);

        try {
            if ($driver === 'sqlite') {
                self::dumpSqlite($abs);
            } else {
                self::dumpMysql($abs);
            }
        } catch (\Throwable $e) {
            @unlink($abs);
            throw $e;
        }

        $size = (int) (@filesize($abs) ?: 0);

        $offsite = false;
        if (self::offsiteEnabled()) {
            $offsite = self::pushOffsite($name, $abs);
        }

        return ['name' => $name, 'size' => $size, 'offsite' => $offsite];
    }

    private static function dumpSqlite(string $dest): void
    {
        $db = (string) config('database.connections.'.self::connectionName().'.database');
        if ($db === '' || ! is_file($db)) {
            throw new \RuntimeException('SQLite database file not found.');
        }
        // Quiesce writers so the copy is consistent.
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
        $in = fopen($db, 'rb');
        $out = gzopen($dest, 'wb9');
        if (! $in || ! $out) {
            throw new \RuntimeException('Could not open files for the SQLite backup.');
        }
        try {
            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 1 << 20));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    private static function dumpMysql(string $dest): void
    {
        $cfg = config('database.connections.'.self::connectionName());
        $bin = self::dumpBinary();
        $args = [
            $bin,
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--routines',
            '--events',
            '--host='.($cfg['host'] ?? '127.0.0.1'),
            '--port='.($cfg['port'] ?? 3306),
            '--user='.($cfg['username'] ?? 'root'),
            $cfg['database'],
        ];
        if (! empty($cfg['unix_socket'])) {
            $args[] = '--socket='.$cfg['unix_socket'];
        }

        $out = gzopen($dest, 'wb6');
        if (! $out) {
            throw new \RuntimeException('Could not open the backup file for writing.');
        }
        // Password via MYSQL_PWD so it never appears in the process list.
        $process = new Process($args, base_path(), ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
        $process->setTimeout(900);
        $err = '';
        try {
            $process->run(function ($type, $buffer) use ($out, &$err) {
                if ($type === Process::OUT) {
                    gzwrite($out, $buffer);
                } else {
                    $err .= $buffer;
                }
            });
        } catch (ProcessTimedOutException $e) {
            gzclose($out);
            throw new \RuntimeException('The database dump timed out.');
        }
        gzclose($out);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump failed: '.trim($err !== '' ? $err : $process->getErrorOutput()));
        }
    }

    private static function pushOffsite(string $name, string $abs): bool
    {
        try {
            $stream = fopen($abs, 'rb');
            Storage::disk('s3')->writeStream(self::OFFSITE_DIR.'/'.$name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Backup offsite push failed for '.$name.': '.$e->getMessage());

            return false;
        }
    }

    // ---- Restore ----

    /**
     * Restore from a stored backup name. Takes a safety snapshot of the current
     * database FIRST so the operation is reversible.
     */
    public static function restore(string $name): void
    {
        $name = basename($name);
        $abs = self::absolute($name);
        if (! is_file($abs)) {
            // Maybe it only exists offsite — pull it down first.
            if (self::offsiteEnabled() && Storage::disk('s3')->exists(self::OFFSITE_DIR.'/'.$name)) {
                self::ensureDir();
                file_put_contents($abs, Storage::disk('s3')->get(self::OFFSITE_DIR.'/'.$name));
            } else {
                throw new \RuntimeException('Backup not found: '.$name);
            }
        }
        if (! self::available()) {
            throw new \RuntimeException(self::unavailableReason() ?? 'Restore is unavailable on this host.');
        }

        // Safety net: snapshot the live DB before we overwrite it.
        self::create(self::SAFETY_PREFIX);

        if (self::driver() === 'sqlite') {
            self::restoreSqlite($abs);
        } else {
            self::restoreMysql($abs);
        }
    }

    private static function restoreSqlite(string $abs): void
    {
        $db = (string) config('database.connections.'.self::connectionName().'.database');
        $tmp = $db.'.restore-'.bin2hex(random_bytes(4));
        $in = gzopen($abs, 'rb');
        $out = fopen($tmp, 'wb');
        if (! $in || ! $out) {
            throw new \RuntimeException('Could not open files for the SQLite restore.');
        }
        try {
            while (! gzeof($in)) {
                fwrite($out, (string) gzread($in, 1 << 20));
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
        DB::disconnect();
        // Drop the WAL/SHM sidecars from the OLD database — otherwise SQLite may
        // replay stale journal frames against the swapped-in file and corrupt it.
        @unlink($db.'-wal');
        @unlink($db.'-shm');
        if (! @rename($tmp, $db)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not replace the SQLite database file.');
        }
    }

    private static function restoreMysql(string $abs): void
    {
        $cfg = config('database.connections.'.self::connectionName());
        $bin = self::clientBinary();
        $args = [
            $bin,
            '--host='.($cfg['host'] ?? '127.0.0.1'),
            '--port='.($cfg['port'] ?? 3306),
            '--user='.($cfg['username'] ?? 'root'),
            '--default-character-set=utf8mb4',
            $cfg['database'],
        ];
        if (! empty($cfg['unix_socket'])) {
            $args[] = '--socket='.$cfg['unix_socket'];
        }

        // Stream the gunzipped SQL into the client's stdin, chunk by chunk.
        $input = (function () use ($abs) {
            $in = gzopen($abs, 'rb');
            try {
                while (! gzeof($in)) {
                    yield (string) gzread($in, 1 << 20);
                }
            } finally {
                gzclose($in);
            }
        })();

        $process = new Process($args, base_path(), ['MYSQL_PWD' => (string) ($cfg['password'] ?? '')]);
        $process->setTimeout(1800);
        $process->setInput($input);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Restore failed: '.trim($process->getErrorOutput()));
        }
        DB::reconnect();
    }

    // ---- Listing / retention / download ----

    /**
     * @return list<array{name:string,size:int,created_at:string,kind:string,offsite:bool}>
     */
    public static function list(): array
    {
        self::ensureDir();
        $offsiteNames = self::offsiteEnabled() ? self::offsiteNames() : [];
        $items = [];
        foreach (glob(self::absolute('*.gz')) ?: [] as $path) {
            $name = basename($path);
            $items[] = [
                'name' => $name,
                'size' => (int) (@filesize($path) ?: 0),
                'created_at' => date('c', (int) (@filemtime($path) ?: time())),
                'kind' => str_starts_with($name, self::SAFETY_PREFIX) ? 'safety' : 'backup',
                'offsite' => in_array($name, $offsiteNames, true),
            ];
        }
        // Newest first.
        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $items;
    }

    /** @return list<string> */
    private static function offsiteNames(): array
    {
        try {
            return array_map('basename', Storage::disk('s3')->files(self::OFFSITE_DIR));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function delete(string $name): void
    {
        $name = basename($name);
        @unlink(self::absolute($name));
        if (self::offsiteConfigured()) {
            try {
                Storage::disk('s3')->delete(self::OFFSITE_DIR.'/'.$name);
            } catch (\Throwable $e) {
                // best-effort
            }
        }
    }

    /** Keep the newest $keep real backups (safety snapshots are pruned to 3). */
    public static function prune(?int $keep = null): void
    {
        $keep = $keep ?? (int) Settings::get('backups.retention', 7);
        $keep = max(1, $keep);
        $all = self::list();
        $backups = array_values(array_filter($all, fn ($b) => $b['kind'] === 'backup'));
        $safety = array_values(array_filter($all, fn ($b) => $b['kind'] === 'safety'));
        foreach (array_slice($backups, $keep) as $old) {
            self::delete($old['name']);
        }
        foreach (array_slice($safety, 3) as $old) {
            self::delete($old['name']);
        }
    }

    public static function absolutePath(string $name): ?string
    {
        $abs = self::absolute(basename($name));

        return is_file($abs) ? $abs : null;
    }

    // ---- helpers ----

    private static function dir(): string
    {
        return storage_path('app/'.self::DIR);
    }

    private static function absolute(string $name): string
    {
        return self::dir().DIRECTORY_SEPARATOR.$name;
    }

    private static function ensureDir(): void
    {
        $dir = self::dir();
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    private static function sanitize(string $s): string
    {
        $s = preg_replace('/[^A-Za-z0-9_-]+/', '-', $s) ?: 'backup';

        return trim($s, '-') ?: 'backup';
    }
}
