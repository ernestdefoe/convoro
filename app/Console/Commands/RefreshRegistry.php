<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\GitHubRegistry;
use Illuminate\Console\Command;

/**
 * Polls every GitHub-linked product that tracks "latest" (i.e. is NOT pinned)
 * and re-syncs it from the repo — so a newly published release auto-appears in
 * the directory, Packagist-style. Pinned products are left frozen.
 */
class RefreshRegistry extends Command
{
    protected $signature = 'convoro:refresh-registry {--all : Also re-sync pinned products}';

    protected $description = 'Refresh GitHub-linked extensions from their repos (auto-update on new releases)';

    public function handle(): int
    {
        $query = Product::where('source', 'github')->whereNotNull('repo');
        if (! $this->option('all')) {
            $query->whereNull('pinned_version');
        }
        $products = $query->get();

        $updated = 0;
        $checked = 0;
        foreach ($products as $product) {
            $checked++;
            try {
                if (GitHubRegistry::syncProduct($product)) {
                    $updated++;
                    $this->info("Updated {$product->slug} → v{$product->version}");
                }
            } catch (\Throwable $e) {
                $this->warn("Skipped {$product->slug}: {$e->getMessage()}");
            }
        }

        $this->info("Registry refresh complete — {$updated} updated of {$checked} checked.");

        return self::SUCCESS;
    }
}
