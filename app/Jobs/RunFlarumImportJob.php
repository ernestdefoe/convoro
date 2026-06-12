<?php

namespace App\Jobs;

use App\Support\FlarumImporter;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a Flarum → Convoro import in the background (CLI context, no web
 * timeout). Progress is published to Settings so the wizard can poll it.
 */
class RunFlarumImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public array $cfg, public array $opts) {}

    public function handle(): void
    {
        $progress = function (string $message, int $percent, array $summary = []) {
            Settings::setMany([
                'import.running' => $percent < 100,
                'import.status' => $message,
                'import.percent' => $percent,
                'import.summary' => $summary,
            ]);
        };

        try {
            $summary = FlarumImporter::run($this->cfg, $this->opts, $progress);
            Settings::setMany([
                'import.running' => false,
                'import.percent' => 100,
                'import.status' => 'Import complete.',
                'import.summary' => $summary,
                'import.last_status' => 'Imported '.($summary['topics'] ?? 0).' topics, '
                    .($summary['posts'] ?? 0).' posts, '.($summary['users'] ?? 0).' members, '
                    .($summary['categories'] ?? 0).' categories.',
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function failed(\Throwable $e): void
    {
        Settings::setMany([
            'import.running' => false,
            'import.status' => 'Import failed: '.$e->getMessage(),
            'import.last_status' => 'Import failed: '.$e->getMessage(),
        ]);
    }
}
