<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\CoverImage;
use Illuminate\Console\Command;

/**
 * Regenerate every auto-generated listing cover (e.g. after a cover-style
 * change). Skips products with a custom uploaded image unless --all is given.
 */
class RegenerateCovers extends Command
{
    protected $signature = 'convoro:covers {--all : Also overwrite custom (non-generated) images}';

    protected $description = 'Regenerate store/directory cover images in the current style';

    public function handle(): int
    {
        $query = Product::query();
        if (! $this->option('all')) {
            // Only the ones we generated (stored under /storage/covers/…).
            $query->where(function ($q) {
                $q->whereNull('image')->orWhere('image', 'like', '%/covers/%');
            });
        }

        $products = $query->get();
        if ($products->isEmpty()) {
            $this->info('No covers to regenerate.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($products->count());
        foreach ($products as $product) {
            try {
                CoverImage::generate($product);
            } catch (\Throwable $e) {
                $this->warn("\nFailed for {$product->slug}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Regenerated '.$products->count().' cover(s).');

        return self::SUCCESS;
    }
}
