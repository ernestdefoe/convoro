<?php

namespace App\Jobs;

use App\Support\ComposerRunner;
use App\Support\ExtensionManager;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs `composer require|remove <package>` off the web request (no timeout),
 * then refreshes the autoloader so a newly installed convoro-extension is
 * discoverable. Status is written to settings for the Marketplace to display.
 */
class ComposerTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public string $action, // 'require' | 'remove'
        public string $package,
    ) {}

    public function handle(): void
    {
        self::status(['state' => 'running', 'action' => $this->action, 'package' => $this->package, 'output' => '', 'finishedAt' => null]);

        $args = $this->action === 'remove'
            ? ['remove', $this->package, '--no-interaction', '--no-progress']
            : ['require', $this->package, '--no-interaction', '--no-progress', '--with-all-dependencies'];

        [$ok, $output] = ComposerRunner::run($args);

        if ($ok) {
            // Make sure new classes are autoloadable + extensions re-scanned.
            ComposerRunner::run(['dump-autoload', '--optimize', '--no-interaction'], null, 300);
            ExtensionManager::flushCache();
            try {
                Artisan::call('optimize:clear');
            } catch (\Throwable) {
            }
        }

        self::status([
            'state' => $ok ? 'done' : 'failed',
            'action' => $this->action,
            'package' => $this->package,
            'output' => mb_substr($output, -4000),
            'finishedAt' => now()->toIso8601String(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        self::status(['state' => 'failed', 'action' => $this->action, 'package' => $this->package, 'output' => $e->getMessage(), 'finishedAt' => now()->toIso8601String()]);
    }

    public static function status(?array $set = null): array
    {
        if ($set !== null) {
            Settings::set('composer.task', $set);

            return $set;
        }

        return (array) Settings::get('composer.task', ['state' => 'idle']);
    }
}
