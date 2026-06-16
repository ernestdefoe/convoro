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
        if (DB::table('products')->where('slug', 'respawn')->exists()) {
            return;
        }

        DB::table('products')->insert([
            'name' => 'Respawn',
            'slug' => 'respawn',
            'type' => 'theme',
            'tagline' => 'A neon gaming / esports theme with scanlines and sharp edges.',
            'description' => "Respawn gives your community a gaming/esports look: deep navy-black, neon cyan + magenta, sharp zero-radius corners, JetBrains Mono UI type, subtle CRT scanlines and a neon glow on buttons and headings. Light mode becomes a clean blue \"daylight\" variant and follows your light/dark toggle.\n\nInstalled on demand from the Marketplace — not bundled. No build step; restyles the whole forum instantly.",
            'version' => '1.0.0',
            'price_cents' => 0,
            'published' => true,
            'featured' => true,
            // Not bundled — installed on demand from its own repo via the
            // Marketplace (downloads into storage/app/extensions).
            'source' => 'github',
            'repo' => 'ernestdefoe/convoro-respawn',
            'package' => 'convoro-respawn',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('products')) {
            DB::table('products')->where('slug', 'respawn')->delete();
        }
    }
};
