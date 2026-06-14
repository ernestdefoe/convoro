<?php

namespace App\Console\Commands;

use App\Support\Caches;
use Illuminate\Console\Command;

class CacheBust extends Command
{
    protected $signature = 'convoro:cache-bust';

    protected $description = 'Clear app + extension caches and reset OPcache so newly-deployed code is served';

    public function handle(): int
    {
        Caches::bust();

        $opcache = function_exists('opcache_reset') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL);
        $this->info('Cleared app + extension caches.');

        if ($opcache) {
            $this->info('Reset OPcache (this SAPI).');
        } else {
            $this->warn('OPcache not active in this SAPI (CLI). To reset the FPM OPcache from the shell,');
            $this->warn('reload php-fpm instead:  sudo systemctl reload php8.5-fpm');
        }

        return self::SUCCESS;
    }
}
