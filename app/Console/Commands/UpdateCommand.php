<?php

namespace App\Console\Commands;

use App\Support\Settings;
use App\Support\Updater;
use Illuminate\Console\Command;

/**
 * Apply a Convoro update from the terminal — the reliable fallback when the
 * in-app updater can't run automatically (no queue worker, exec disabled, etc.),
 * and the command the in-app updater spawns as a detached background process so
 * an update never depends on a long-running worker being present.
 *
 *   php artisan convoro:update          # download + install the latest release
 *   php artisan convoro:update --reset  # clear a stuck "updating" flag
 */
class UpdateCommand extends Command
{
    protected $signature = 'convoro:update {--reset : Clear a stuck "update in progress" flag instead of updating}';

    protected $description = 'Download and apply the latest Convoro release (works without a queue worker).';

    public function handle(): int
    {
        if ($this->option('reset')) {
            Settings::setMany(['update.running' => false, 'update.last_status' => __('Update flag reset.')]);
            $this->info('Cleared the "update in progress" flag — you can try again.');

            return self::SUCCESS;
        }

        Settings::setMany(['update.running' => true, 'update.last_status' => __('Update running…')]);
        $this->info('Applying update — this can take a minute, do not interrupt it…');

        try {
            $result = Updater::apply();
        } catch (\Throwable $e) {
            Settings::setMany(['update.running' => false, 'update.last_status' => 'Update failed: '.$e->getMessage()]);
            $this->error('Update failed: '.$e->getMessage());

            return self::FAILURE;
        }

        Settings::setMany([
            'update.running' => false,
            'update.last_status' => $result['message'] ?? __('Update finished.'),
        ]);

        if ($result['ok'] ?? false) {
            $this->info($result['message'] ?? 'Done.');

            return self::SUCCESS;
        }

        $this->error($result['message'] ?? 'Update did not complete.');

        return self::FAILURE;
    }
}
