<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * POC — managed-hosting provisioner.
 *
 * Stands up a brand-new, fully-isolated Convoro community from a single click
 * and makes the Convoro Account that requested it the Admin of that community.
 * This is the same sequence the web installer runs (migrate → seed base content
 * → create admin), but targeted at a per-tenant database instead of mutating
 * the host app. Locally each tenant is its own sqlite file, so isolation is
 * physical and obvious; in production this would be a database-per-tenant on
 * MySQL plus a deploy + DNS step.
 */
class TenantProvisioner
{
    public static function provision(User $owner, string $name): Tenant
    {
        $name = trim($name) ?: 'My Community';
        $slug = self::uniqueSlug($name);
        $dbPath = database_path('tenants/'.$slug.'.sqlite');

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'database' => $dbPath,
            'owner_user_id' => $owner->id,
            'status' => 'provisioning',
        ]);

        // The default connection name (sqlite locally) and its current (central)
        // database. We temporarily repoint THIS connection at the tenant's own
        // database so every migrate/seed/Eloquent op — which all resolve the
        // default connection — runs inside the tenant DB, exactly like the
        // installer does. Restored in `finally` no matter what.
        $conn = config('database.default');
        $centralDb = config("database.connections.{$conn}.database");

        try {
            // 1. Create the empty, isolated tenant database (its own sqlite file).
            File::ensureDirectoryExists(dirname($dbPath));
            if (! is_file($dbPath)) {
                touch($dbPath);
            }

            // 2. Point the default connection at the tenant DB.
            config(["database.connections.{$conn}.database" => $dbPath]);
            DB::purge($conn);

            // 3. Build the full forum schema + seed base content + owner-as-admin,
            //    all inside the tenant DB.
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => \Database\Seeders\InstallSeeder::class, '--force' => true]);

            User::on($conn)->updateOrCreate(
                ['email' => $owner->email],
                [
                    'name' => $owner->name,
                    'password' => $owner->password,   // already hashed — same credentials as their Convoro Account
                    'is_admin' => true,               // ← auto-set as Admin of their own community
                    'email_verified_at' => now(),
                ],
            );

            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
            $error = Str::limit($e->getMessage(), 500);
        } finally {
            // 4. Restore the default connection to the central database.
            config(["database.connections.{$conn}.database" => $centralDb]);
            DB::purge($conn);
        }

        $tenant->update($ok
            ? ['status' => 'active', 'error' => null]
            : ['status' => 'failed', 'error' => $error ?? 'Unknown error']);

        return $tenant->refresh();
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'community';
        $slug = $base;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
