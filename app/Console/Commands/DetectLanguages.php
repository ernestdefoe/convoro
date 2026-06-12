<?php

namespace App\Console\Commands;

use App\Jobs\DetectPostLanguageJob;
use App\Models\Post;
use App\Support\ContentTranslator;
use Illuminate\Console\Command;

/**
 * Backfill source-language detection for existing posts (e.g. after an import
 * or after first enabling translation) so they can be auto-translated for
 * readers. Queues one detection job per post missing a detected_locale.
 */
class DetectLanguages extends Command
{
    protected $signature = 'convoro:detect-languages {--sync : Run inline instead of queueing}';

    protected $description = 'Detect the source language of posts that don\'t have one yet';

    public function handle(): int
    {
        if (! ContentTranslator::enabled()) {
            $this->error('Translation is not enabled (configure an AI provider in Admin → AI).');

            return self::FAILURE;
        }

        $query = Post::whereNull('detected_locale')->where('is_ai', false);
        $total = $query->count();
        if ($total === 0) {
            $this->info('All posts already have a detected language.');

            return self::SUCCESS;
        }

        $this->info("Detecting language for {$total} posts…");
        $bar = $this->output->createProgressBar($total);

        $query->select('id')->chunkById(200, function ($posts) use ($bar) {
            foreach ($posts as $post) {
                if ($this->option('sync')) {
                    (new DetectPostLanguageJob($post->id))->handle();
                } else {
                    DetectPostLanguageJob::dispatch($post->id);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info($this->option('sync') ? 'Done.' : 'Queued — run a worker to process the jobs.');

        return self::SUCCESS;
    }
}
