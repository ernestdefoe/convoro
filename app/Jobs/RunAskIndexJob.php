<?php

namespace App\Jobs;

use App\Support\AskIndex;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Builds the "Ask Convoro" semantic index in the background, with progress. */
class RunAskIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function handle(): void
    {
        $progress = function (string $message, int $percent, int $count) {
            Settings::setMany([
                'ask_index.running' => $percent < 100,
                'ask_index.status' => $message,
                'ask_index.percent' => $percent,
                'ask_index.count' => $count,
            ]);
        };

        try {
            $count = AskIndex::rebuild($progress);
            Settings::setMany([
                'ask_index.running' => false,
                'ask_index.percent' => 100,
                'ask_index.count' => $count,
                'ask_index.status' => 'Indexed '.$count.' posts.',
                'ask_index.built_at' => now()->toDateTimeString(),
            ]);
        } catch (\Throwable $e) {
            Settings::setMany(['ask_index.running' => false, 'ask_index.status' => 'Index failed: '.$e->getMessage()]);
        }
    }
}
