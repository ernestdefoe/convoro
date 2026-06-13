<?php

namespace App\Console\Commands;

use App\Support\KnowledgeBase;
use Illuminate\Console\Command;

class ReindexKnowledge extends Command
{
    protected $signature = 'convoro:reindex-knowledge';

    protected $description = 'Embed the docs + changelog into the AI knowledge base (for grounded Ask answers)';

    public function handle(): int
    {
        $count = KnowledgeBase::reindex(function (string $msg, int $pct) {
            $this->line("[{$pct}%] {$msg}");
        });
        $this->info("Knowledge base ready: {$count} sources embedded.");

        return self::SUCCESS;
    }
}
