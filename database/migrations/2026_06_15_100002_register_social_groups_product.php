<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('products')) {
            return;
        }

        $exists = DB::table('products')->where('slug', 'social-groups')->exists();
        if ($exists) {
            return;
        }

        DB::table('products')->insert([
            'name' => 'Social Groups',
            'slug' => 'social-groups',
            'type' => 'extension',
            'tagline' => 'Member-run groups with private, threaded discussions.',
            'description' => "Let members create their own groups — public or private — each with its own threaded discussions powered by the same composer, reactions and polls as the main forum. Open, request-to-join or invite-only membership, owner & moderator roles, banning, join-request approvals, a media gallery, an owner analytics dashboard, per-group RSS and a profile badge.\n\nBuilt into Convoro core: nothing to install.",
            'version' => '1.0.0',
            'price_cents' => 0,
            'published' => true,
            'featured' => true,
            'source' => 'manual',
            'package' => 'convoro-social-groups',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('products')) {
            DB::table('products')->where('slug', 'social-groups')->delete();
        }
    }
};
