<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * On-demand demos. Stands up a fully-functional, isolated Convoro demo for a
 * requester, seeded with content, with a personal admin account whose
 * credentials are emailed to them. Demos self-destruct after TTL_DAYS.
 *
 * Each demo's forum data lives in its own sqlite file, served from its own
 * subdomain (try-xxxx.convoro.co) by the HostTenant middleware, with mail
 * disabled inside it.
 *
 * Flow: reserve() runs synchronously (reserves a slot), then build() does the
 * heavy migrate+seed in a queued job. build() shells out to `artisan migrate`
 * in a fresh process with the connection overridden via env — a clean boot
 * binds the Schema builder to the demo's sqlite file from the start, which an
 * in-process connection swap cannot do (the db.schema/migrator bind to the
 * original default connection and ignore a later switch).
 */
class DemoProvisioner
{
    public const TTL_DAYS = 3;

    public const MAX_ACTIVE = 5;

    /** Reserve a demo slot synchronously: create the central record only. */
    public static function reserve(string $email): Tenant
    {
        $slug = self::uniqueSlug();

        return Tenant::create([
            'name' => 'Demo · '.$email,
            'slug' => $slug,
            'database' => database_path('tenants/'.$slug.'.sqlite'),
            'owner_user_id' => 0,        // demos have no central owner
            'kind' => 'demo',
            'email' => $email,
            'status' => 'provisioning',
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);
    }

    /**
     * Build out a reserved demo: schema + seed content + a personal admin login.
     * Sets the tenant status to active/failed. Returns the credentials.
     *
     * @return array{login: string, password: string, url: string, ok: bool}
     */
    public static function build(Tenant $tenant): array
    {
        $dbPath = $tenant->database;
        $email = $tenant->email;
        $password = Str::password(12, true, true, false); // letters+numbers, no symbols
        $ok = false;

        try {
            File::ensureDirectoryExists(dirname($dbPath));
            if (! is_file($dbPath)) {
                touch($dbPath);
            }

            // Fresh subprocess, default connection forced to this demo's sqlite.
            // APP_CONFIG_CACHE points at a non-existent path so the child ignores
            // the production *cached* config (which pins DB_CONNECTION=mysql) and
            // instead reads .env + these overrides — otherwise migrate/seed would
            // run against the central database, not the demo file.
            $php = (new PhpExecutableFinder)->find() ?: PHP_BINARY;
            $env = [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $dbPath,
                'APP_CONFIG_CACHE' => base_path('bootstrap/cache/.demo-no-config-'.bin2hex(random_bytes(6)).'.php'),
            ];

            self::artisan($php, $env, ['migrate', '--force']);       // full schema
            // Safety gate: the demo file MUST now hold the schema. If it's empty,
            // migrate hit the wrong database — abort BEFORE seeding, so demo
            // content can never be written into the central DB.
            self::assertSqliteReady($dbPath);
            self::artisan($php, $env, ['db:seed', '--force']);       // ForumSeeder content

            // The personal admin login — written via the dedicated demo
            // connection (direct queries honour the named connection fine).
            config(['database.connections.demo.database' => $dbPath]);
            DB::purge('demo');
            User::on('demo')->updateOrCreate(
                ['email' => $email],
                ['name' => 'Demo Admin', 'password' => Hash::make($password), 'is_admin' => true, 'email_verified_at' => now()],
            );
            DB::purge('demo');

            // Stand up the subdomain (nginx vhost + per-demo TLS cert). On prod
            // this must succeed or the emailed link would 404/fail TLS — so a
            // failure here fails the whole build. No-op locally (hook unset).
            self::site('add', $tenant->slug);
            $ok = true;
        } catch (\Throwable $e) {
            report($e);
        }

        $tenant->update(['status' => $ok ? 'active' : 'failed']);

        return [
            'login' => $email,
            'password' => $password,
            'url' => self::url($tenant->refresh()),
            'ok' => $ok,
        ];
    }

    /** Synchronous reserve + build (tests / CLI). */
    public static function provision(string $email): array
    {
        $tenant = self::reserve($email);
        $creds = self::build($tenant);

        return ['tenant' => $tenant->refresh(), ...$creds];
    }

    /** Assert the freshly-migrated demo sqlite file actually has its schema. */
    private static function assertSqliteReady(string $path): void
    {
        $pdo = new \PDO('sqlite:'.$path);
        $hasUsers = (int) $pdo->query(
            "SELECT count(*) FROM sqlite_master WHERE type='table' AND name='users'"
        )->fetchColumn();
        if ($hasUsers < 1) {
            throw new \RuntimeException("demo migrate did not populate {$path} — aborting before seed");
        }
    }

    /** Run an artisan subcommand in a fresh process, throwing on failure. */
    private static function artisan(string $php, array $env, array $args): void
    {
        $result = Process::path(base_path())->env($env)->timeout(180)
            ->run([$php, 'artisan', ...$args]);

        if (! $result->successful()) {
            throw new \RuntimeException(
                'artisan '.implode(' ', $args).' failed: '.trim($result->errorOutput() ?: $result->output()),
            );
        }
    }

    /** Public URL of a demo (its own subdomain). */
    public static function url(Tenant $tenant): string
    {
        return 'https://'.$tenant->slug.'.convoro.co';
    }

    /** Tear a demo down — drop its isolated database and the record. */
    public static function destroy(Tenant $tenant): void
    {
        // Remove the subdomain vhost + cert first (best-effort).
        try {
            self::site('remove', $tenant->slug);
        } catch (\Throwable $e) {
            report($e);
        }

        // Release any open handle to the file before unlinking (matters on
        // Windows; harmless on Linux).
        DB::purge('demo');
        if ($tenant->database && is_file($tenant->database)) {
            @unlink($tenant->database);
        }
        $tenant->delete();
    }

    /**
     * Invoke the privileged subdomain helper (sudo) to add/remove a demo's
     * nginx vhost + Let's Encrypt cert. No-op when the hook is unconfigured.
     */
    private static function site(string $action, string $slug): void
    {
        $hook = config('convoro.demo_site_hook');
        if (! $hook) {
            return; // local dev — no subdomain serving
        }

        $result = Process::timeout(120)->run(['sudo', $hook, $action, $slug]);
        if (! $result->successful()) {
            throw new \RuntimeException(
                "demo site hook ({$action} {$slug}) failed: ".trim($result->errorOutput() ?: $result->output()),
            );
        }
    }

    public static function activeCount(): int
    {
        return Tenant::query()->activeDemos()->count();
    }

    public static function hasFreeSlot(): bool
    {
        return self::activeCount() < self::MAX_ACTIVE;
    }

    private static function uniqueSlug(): string
    {
        do {
            $slug = 'try-'.Str::lower(Str::random(6));
        } while (Tenant::where('slug', $slug)->exists());

        return $slug;
    }
}
