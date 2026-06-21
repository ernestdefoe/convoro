<?php

namespace App\Console\Commands;

use App\Support\Search\TypesenseSync;
use Illuminate\Console\Command;

/**
 * Rebuilds the Typesense collection from scratch — run once after enabling the
 * convoro-typesense extension and pointing it at a server, then it stays in
 * sync automatically via SyncTopicTypesenseJob on post/topic changes.
 */
class TypesenseReindex extends Command
{
    protected $signature = 'convoro:typesense-reindex';

    protected $description = 'Index every topic into the Typesense search collection.';

    public function handle(): int
    {
        if (! TypesenseSync::active()) {
            $this->error('The convoro-typesense extension is not enabled.');

            return self::FAILURE;
        }

        try {
            $this->info('Indexing topics into Typesense…');
            $n = TypesenseSync::reindexAll(function ($n) {
                if ($n % 200 === 0) {
                    $this->line("  {$n} indexed…");
                }
            });
            $this->info("Done — {$n} topic(s) indexed.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
