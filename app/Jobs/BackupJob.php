<?php

namespace App\Jobs;

use App\Support\Backup;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs a backup or restore off the web request so large databases don't hit a
 * PHP-FPM timeout. Progress + result are surfaced through Settings so the admin
 * page can poll. Restore always snapshots the live DB first (see Backup).
 */
class BackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    /** @param 'create'|'restore' $mode */
    public function __construct(public string $mode, public ?string $name = null) {}

    public function handle(): void
    {
        Settings::setMany([
            'backups.running' => true,
            'backups.status' => $this->mode === 'restore' ? __('Restoring…') : __('Backing up…'),
        ]);

        try {
            if ($this->mode === 'restore') {
                Backup::restore((string) $this->name);
                $result = ['ok' => true, 'mode' => 'restore', 'message' => __('Database restored.'), 'at' => now()->toIso8601String()];
            } else {
                $meta = Backup::create('manual');
                Backup::prune();
                $result = ['ok' => true, 'mode' => 'create', 'message' => __('Backup created.'), 'name' => $meta['name'], 'at' => now()->toIso8601String()];
            }
        } catch (\Throwable $e) {
            Log::error('BackupJob '.$this->mode.' failed: '.$e->getMessage());
            $result = ['ok' => false, 'mode' => $this->mode, 'message' => $e->getMessage(), 'at' => now()->toIso8601String()];
        }

        Settings::setMany([
            'backups.running' => false,
            'backups.status' => '',
            'backups.last' => $result,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Settings::setMany([
            'backups.running' => false,
            'backups.status' => '',
            'backups.last' => ['ok' => false, 'mode' => $this->mode, 'message' => $e->getMessage(), 'at' => now()->toIso8601String()],
        ]);
    }
}
