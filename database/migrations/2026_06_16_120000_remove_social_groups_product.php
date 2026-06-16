<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Social Groups ships as a CORE feature, not an installable extension, so it
 * should not appear in the Marketplace directory. Remove the catalog row that
 * the earlier (now-deleted) register migration created on existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getSchemaBuilder()->hasTable('products')) {
            DB::table('products')->where('slug', 'social-groups')->delete();
        }
    }

    public function down(): void
    {
        // No rollback — Social Groups is core and intentionally unlisted.
    }
};
