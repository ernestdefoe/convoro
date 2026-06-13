<?php

namespace App\Console\Commands;

use App\Support\Backup;
use App\Support\Settings;
use Illuminate\Console\Command;

class RunBackup extends Command
{
    protected $signature = 'convoro:backup {--prune : prune old backups after creating} {--force : run even if scheduled backups are disabled}';

    protected $description = 'Create a database backup (and optionally prune old ones)';

    public function handle(): int
    {
        if (! $this->option('force') && ! Settings::get('backups.enabled', false)) {
            $this->info('Scheduled backups are disabled in admin settings.');

            return self::SUCCESS;
        }
        if (! Backup::available()) {
            $this->error(Backup::unavailableReason() ?? 'Backups are unavailable on this host.');

            return self::FAILURE;
        }

        $meta = Backup::create('scheduled');
        $this->info('Created '.$meta['name'].' ('.number_format($meta['size'] / 1024, 1).' KB)'
            .($meta['offsite'] ? ' + offsite' : ''));

        if ($this->option('prune')) {
            Backup::prune();
            $this->info('Pruned old backups (retention: '.Settings::get('backups.retention', 7).').');
        }

        return self::SUCCESS;
    }
}
