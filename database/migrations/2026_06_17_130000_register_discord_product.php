<?php

use App\Models\Product;
use App\Support\CoverImage;
use App\Support\ExtensionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Directory listing for the bundled first-party Discord extension: a free,
 * published Product so it shows on /extensions → Marketplace. Bundled (ships
 * with the app), so source = 'bundled' — no repo download. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('products')) {
            return;
        }
        ExtensionManager::flushCache();
        $m = ExtensionManager::all()['convoro-discord'] ?? null;
        if (! $m) {
            return; // extension files not present on this install
        }

        $p = Product::firstOrNew(['package' => 'convoro-discord']);
        if (! $p->exists) {
            $p->fill([
                'slug' => 'convoro-discord',
                'name' => $m['name'] ?? 'Discord',
                'type' => 'extension',
                'tagline' => 'Discord login, two-way role sync, and per-category channel announcements.',
                'description' => $m['description'] ?? '',
                'version' => $m['version'] ?? '1.0.0',
                'source' => 'bundled',
            ]);
            $p->price_cents = 0;
            $p->published = true;
            $p->status = 'approved';
            $p->save();
        }

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
