<?php

use App\Models\Product;
use App\Support\CoverImage;
use App\Support\ExtensionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Give the bundled core widgets the same store/directory treatment as every
 * other extension: a Product listing with an auto-generated branded cover.
 * Creates a (free, approved, published) listing for any core widget that lacks
 * one, and generates a cover for any core-widget product missing an image.
 * Existing GitHub-linked listings (e.g. trending, online-now) are left intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        ExtensionManager::flushCache();

        foreach (ExtensionManager::all() as $m) {
            if (empty($m['core'])) {
                continue;
            }

            $p = Product::firstOrNew(['package' => $m['id']]);
            if (! $p->exists) {
                $p->fill([
                    'slug' => Str::slug($m['id']),
                    'name' => $m['name'],
                    'type' => 'extension',
                    'tagline' => Str::limit(strip_tags($m['description'] ?? ''), 180),
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
                    // covers are best-effort; never block a migration on them
                }
            }
        }
    }

    public function down(): void
    {
        // Listings/covers are harmless to keep; no teardown.
    }
};
