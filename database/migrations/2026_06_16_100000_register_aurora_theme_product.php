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
        if (DB::table('products')->where('slug', 'aurora')->exists()) {
            return;
        }

        DB::table('products')->insert([
            'name' => 'Aurora',
            'slug' => 'aurora',
            'type' => 'theme',
            'tagline' => 'A cosmic dark theme with an animated aurora backdrop.',
            'description' => "Aurora gives your community a deep night-sky look: a slowly drifting aurora-borealis backdrop over a starfield, frosted-glass surfaces, soft cyan/pink glow, and a gradient that runs through buttons, badges and the scrollbar.\n\nSix switchable palettes (Aurora, Sunset, Ocean, Forest, Nebula, Ember) live in the header and remember each visitor's choice. Light mode becomes a soft lavender \"Dawn\" variant. Enable it from the Marketplace — no build step, restyles the whole forum instantly.",
            'version' => '1.0.0',
            'price_cents' => 0,
            'published' => true,
            'featured' => true,
            'source' => 'manual',
            'package' => 'convoro-aurora',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('products')) {
            DB::table('products')->where('slug', 'aurora')->delete();
        }
    }
};
