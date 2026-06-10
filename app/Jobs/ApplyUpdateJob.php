<?php

namespace App\Jobs;

use App\Support\Settings;
use App\Support\Updater;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs the software update in the background (queue worker = CLI context),
 * so it isn't bound by the web request's execution/read timeouts.
 */
class ApplyUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function handle(): void
    {
        $result = Updater::apply();

        Settings::setMany([
            'update.running' => false,
            'update.last_status' => $result['message'] ?? 'Update finished.',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Settings::setMany([
            'update.running' => false,
            'update.last_status' => 'Update failed: '.$e->getMessage(),
        ]);
    }
}
