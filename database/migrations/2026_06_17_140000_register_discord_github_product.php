<?php

use App\Models\Product;
use App\Support\CoverImage;
use Illuminate\Database\Migrations\Migration;

/**
 * Directory listing for the Discord extension as a NON-bundled, on-demand
 * extension installed from its own GitHub repo (like the first-party themes).
 * Upserts by package, so it also converts an earlier bundled listing to a
 * github-sourced one. Free + published; idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('products')) {
            return;
        }

        $p = Product::firstOrNew(['package' => 'convoro-discord']);
        $p->fill([
            'slug' => 'convoro-discord',
            'name' => 'Discord',
            'type' => 'extension',
            'tagline' => 'Discord login, two-way role sync, and per-category channel announcements.',
            'description' => "Connect your community to Discord. Members sign in with Discord, their roles sync both ways (Discord roles ⇄ Convoro groups), and new topics announce to Discord channels through per-category webhooks — so you choose exactly which categories post. Dependency-free. Configure your Discord application, bot, role map and channel webhooks from the admin settings page.\n\nInstalled on demand from the Marketplace — not bundled. Free and open-source (MIT).",
            'version' => '1.0.0',
            'source' => 'github',
            'repo' => 'ernestdefoe/convoro-discord',
            'download_url' => 'https://github.com/ernestdefoe/convoro-discord/archive/refs/tags/v1.0.0.zip',
            'ref' => 'v1.0.0',
        ]);
        $p->price_cents = 0;
        $p->published = true;
        $p->status = 'approved';
        $p->save();

        if (empty($p->image)) {
            try {
                CoverImage::generate($p);
            } catch (\Throwable) {
                // best-effort
            }
        }
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
            Product::where('package', 'convoro-discord')->delete();
        }
    }
};
