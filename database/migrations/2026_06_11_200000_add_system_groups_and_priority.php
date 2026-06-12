<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make staff identity first-class:
 *  - `key`      marks seeded system groups (admin / moderator) so they show in
 *               the Groups manager (recolorable, renamable) but can't be deleted.
 *  - `priority` orders staff groups so the highest-ranked one wins the badge.
 * Seeds an "Admin" group whose color drives the admin staff badge, plus a
 * "Moderator" group ready to assign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'key')) {
                $table->string('key', 40)->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('groups', 'priority')) {
                $table->integer('priority')->default(0)->after('is_staff');
            }
        });

        $seed = [
            ['key' => 'admin', 'name' => 'Admin', 'color' => '#5b5bd6', 'priority' => 100],
            ['key' => 'moderator', 'name' => 'Moderator', 'color' => '#0ea5e9', 'priority' => 50],
        ];
        foreach ($seed as $g) {
            if (! DB::table('groups')->where('key', $g['key'])->exists()) {
                DB::table('groups')->insert([
                    'key' => $g['key'],
                    'name' => $g['name'],
                    'color' => $g['color'],
                    'is_staff' => true,
                    'priority' => $g['priority'],
                    'permissions' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('groups')->whereIn('key', ['admin', 'moderator'])->delete();
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'priority')) {
                $table->dropColumn('priority');
            }
            if (Schema::hasColumn('groups', 'key')) {
                $table->dropUnique(['key']);
                $table->dropColumn('key');
            }
        });
    }
};
