<?php

namespace Tests\Feature;

use App\Support\Backup;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    /** Backup + restore against a real file-based SQLite, end to end. */
    public function test_sqlite_backup_and_restore_round_trip(): void
    {
        // Prime the forever-cached settings against the migrated test DB so the
        // Backup service's Settings reads don't hit the temp connection below.
        config(['cache.default' => 'array']);
        Settings::all();

        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cvbk_'.bin2hex(random_bytes(4)).'.sqlite';
        file_put_contents($file, '');
        config(['database.connections.cvbk' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
        config(['database.default' => 'cvbk']);
        DB::purge('cvbk');
        DB::connection('cvbk')->statement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        DB::connection('cvbk')->table('t')->insert(['v' => 'keep']);

        $this->assertTrue(Backup::available());

        $meta = Backup::create('phpunit');
        $this->assertStringEndsWith('.sqlite.gz', $meta['name']);
        $this->assertGreaterThan(0, $meta['size']);

        // Mutate after the backup; a restore must roll this back.
        DB::connection('cvbk')->table('t')->insert(['v' => 'gone']);
        $this->assertSame(2, DB::connection('cvbk')->table('t')->count());

        Backup::restore($meta['name']);   // snapshots first, then replaces the file
        DB::purge('cvbk');

        $this->assertSame(1, DB::connection('cvbk')->table('t')->count());
        $this->assertSame('keep', DB::connection('cvbk')->table('t')->value('v'));

        // A pre-restore safety snapshot should now exist.
        $kinds = array_column(Backup::list(), 'kind');
        $this->assertContains('safety', $kinds);

        // Cleanup the files this test created.
        foreach (Backup::list() as $b) {
            if (str_contains($b['name'], 'phpunit') || $b['kind'] === 'safety') {
                Backup::delete($b['name']);
            }
        }
        DB::disconnect('cvbk');
        @unlink($file);
        config(['database.default' => 'sqlite']);
    }

    public function test_restore_endpoint_requires_typed_confirmation(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        // Missing confirm => validation error, no job dispatched.
        $this->actingAs($admin)
            ->post('/admin/backups/restore', ['name' => 'whatever.sql.gz'])
            ->assertSessionHasErrors('confirm');
    }

    public function test_settings_endpoint_validates_and_saves(): void
    {
        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/admin/backups/settings', ['enabled' => true, 'frequency' => 'weekly', 'retention' => 14, 'offsite' => false])
            ->assertRedirect();

        $this->assertTrue((bool) Settings::get('backups.enabled'));
        $this->assertSame('weekly', Settings::get('backups.frequency'));
        $this->assertSame(14, (int) Settings::get('backups.retention'));

        $this->actingAs($admin)
            ->post('/admin/backups/settings', ['enabled' => true, 'frequency' => 'monthly', 'retention' => 14, 'offsite' => false])
            ->assertSessionHasErrors('frequency');
    }
}
