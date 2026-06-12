<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;

/**
 * Browser-based first-run installer — no CLI, no Composer step needed by the
 * end user (the release archive already ships vendor/ + public/build/). Writes
 * .env, runs migrations, seeds base content, creates the admin, and locks
 * itself with a storage/installed flag.
 */
class Installer
{
    public static function flagPath(): string
    {
        return storage_path('installed');
    }

    /**
     * Installed if the flag file exists, OR a real schema is already present
     * (covers upgrades from a pre-installer deploy — we then write the flag so
     * later requests skip the DB probe).
     */
    public static function isInstalled(): bool
    {
        if (is_file(self::flagPath())) {
            return true;
        }

        try {
            if (Schema::hasTable('users')) {
                @file_put_contents(self::flagPath(), 'ok');

                return true;
            }
        } catch (\Throwable) {
            // No DB configured yet → not installed.
        }

        return false;
    }

    /**
     * Guarantee an APP_KEY exists. Called from a provider's boot() (before the
     * encrypter is resolved by cookie/session middleware), so a freshly
     * unzipped site can render the installer without a shell `key:generate`.
     * Persists to .env (creating it from .env.example) so it survives.
     */
    public static function ensureAppKey(): void
    {
        if (config('app.key')) {
            return;
        }

        $path = base_path('.env');
        if (! is_file($path)) {
            @copy(base_path('.env.example'), $path);
            if (! is_file($path)) {
                @file_put_contents($path, '');
            }
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        self::writeEnv(['APP_KEY' => $key]);
        config(['app.key' => $key]);
    }

    /** @return array{ok:bool, checks:array<int,array{label:string,pass:bool,hint?:string}>} */
    public static function requirements(): array
    {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
        $checks[] = ['label' => 'PHP ≥ 8.3 (you have '.PHP_VERSION.')', 'pass' => $phpOk];

        foreach (['pdo', 'mbstring', 'openssl', 'tokenizer', 'gd', 'fileinfo', 'ctype', 'json'] as $ext) {
            $checks[] = ['label' => "PHP extension: {$ext}", 'pass' => extension_loaded($ext)];
        }
        $checks[] = ['label' => 'PHP extension: zip (for installing add-ons)', 'pass' => extension_loaded('zip')];
        $checks[] = ['label' => 'A database driver (pdo_mysql or pdo_pgsql)', 'pass' => extension_loaded('pdo_mysql') || extension_loaded('pdo_pgsql')];

        $writable = [
            'storage/' => storage_path(),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
            '.env (project root must be writable)' => base_path(),
        ];
        foreach ($writable as $label => $path) {
            $checks[] = ['label' => "Writable: {$label}", 'pass' => is_writable($path), 'hint' => 'chmod 775'];
        }

        return [
            'ok' => collect($checks)->every(fn ($c) => $c['pass']),
            'checks' => $checks,
        ];
    }

    /**
     * Non-blocking, production-reliability recommendations. Shown in the
     * installer as "Recommended for production" but never block the install.
     * See deploy/DEPLOYMENT.md for the why behind each.
     */
    public static function advisories(): array
    {
        $out = [];

        // OPcache: large performance win in production.
        $opcache = function_exists('opcache_get_status');
        if ($opcache) {
            $status = @opcache_get_status(false);
            $opcache = is_array($status) ? ($status['opcache_enabled'] ?? false) : false;
        }
        $out[] = [
            'label' => 'PHP OPcache enabled (big performance win)',
            'pass' => (bool) $opcache,
            'hint' => 'Set opcache.enable=1 in php.ini',
        ];

        // Memory overcommit: required by Redis/Valkey to avoid background-save crashes.
        $oc = @file_get_contents('/proc/sys/vm/overcommit_memory');
        if ($oc !== false) {
            $out[] = [
                'label' => 'Memory overcommit on (vm.overcommit_memory=1) — keeps Redis/Valkey stable',
                'pass' => trim($oc) === '1',
                'hint' => 'See deploy/DEPLOYMENT.md §2',
            ];
        }

        return $out;
    }

    /** Supported DB engines for the installer dropdown. */
    public static function drivers(): array
    {
        $out = [];
        if (extension_loaded('pdo_mysql')) {
            $out[] = ['value' => 'mysql', 'label' => 'MySQL / MariaDB', 'port' => 3306];
        }
        if (extension_loaded('pdo_pgsql')) {
            $out[] = ['value' => 'pgsql', 'label' => 'PostgreSQL', 'port' => 5432];
        }

        return $out;
    }

    /**
     * Try to connect with the supplied credentials (no .env written yet).
     *
     * @throws \RuntimeException with a friendly message on failure
     */
    public static function testDatabase(array $db): void
    {
        $driver = $db['driver'] ?? 'mysql';
        $host = $db['host'] ?? '127.0.0.1';
        $port = (int) ($db['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
        $name = $db['database'] ?? '';
        $user = $db['username'] ?? '';
        $pass = $db['password'] ?? '';

        $dsn = $driver === 'pgsql'
            ? "pgsql:host={$host};port={$port};dbname={$name}"
            : "mysql:host={$host};port={$port};dbname={$name}";

        try {
            new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not connect: '.$e->getMessage());
        }
    }

    /**
     * Run the full install. $data = ['site'=>['name','url'], 'db'=>[...],
     * 'admin'=>['name','email','password']].
     */
    public static function run(array $data): void
    {
        $db = $data['db'];
        $site = $data['site'];
        $admin = $data['admin'];

        // 1. Validate the DB connection first.
        self::testDatabase($db);

        // 2. Apply DB config at runtime so migrate/seed in THIS request use it.
        $driver = $db['driver'] ?? 'mysql';
        config([
            'database.default' => $driver,
            "database.connections.{$driver}.host" => $db['host'] ?? '127.0.0.1',
            "database.connections.{$driver}.port" => (int) ($db['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)),
            "database.connections.{$driver}.database" => $db['database'],
            "database.connections.{$driver}.username" => $db['username'] ?? '',
            "database.connections.{$driver}.password" => $db['password'] ?? '',
        ]);
        DB::purge($driver);

        // 3. Ensure an APP_KEY exists for this request.
        $key = config('app.key');
        if (! $key) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            config(['app.key' => $key]);
        }

        // 4. Write .env (creating it from .env.example if absent).
        self::writeEnv([
            'APP_NAME' => '"'.str_replace('"', '', $site['name']).'"',
            'APP_ENV' => 'production',
            'APP_KEY' => $key,
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($site['url'], '/'),
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $db['host'] ?? '127.0.0.1',
            'DB_PORT' => (string) ($db['port'] ?? ($driver === 'pgsql' ? 5432 : 3306)),
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'] ?? '',
            'DB_PASSWORD' => $db['password'] ?? '',
            // Shared-hosting-safe defaults: no Redis required.
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ]);

        // 5. Migrate (core + any bundled extension paths) and seed base content.
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => \Database\Seeders\InstallSeeder::class, '--force' => true]);

        // 6. Create the admin account from the form.
        User::updateOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => Hash::make($admin['password']),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        // 7. Lock the installer.
        @file_put_contents(self::flagPath(), json_encode([
            'version' => config('convoro.version'),
            'installed_at' => now()->toIso8601String(),
        ]));

        // 8. Best-effort cache rebuild.
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
        }
    }

    /** Merge key=value pairs into .env (created from .env.example if missing). */
    private static function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            $example = base_path('.env.example');
            @copy($example, $path);
            if (! is_file($path)) {
                @file_put_contents($path, '');
            }
        }

        $contents = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
            } else {
                $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents);
    }

    /** Quote a value for .env if it contains spaces/specials and isn't already quoted. */
    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^".*"$/', $value)) {
            return $value;
        }
        if (preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }
}
