<?php

namespace App\Console\Commands;

use App\Support\IconGenerator;
use Illuminate\Console\Command;

class GenerateIcons extends Command
{
    protected $signature = 'convoro:icons {source? : path to a source image; omit for the default mark}';

    protected $description = 'Generate the full PWA/favicon icon set (one source → every size)';

    public function handle(): int
    {
        $source = $this->argument('source');
        $binary = null;

        if ($source) {
            if (! is_file($source)) {
                $this->error("Source not found: {$source}");

                return self::FAILURE;
            }
            $binary = (string) file_get_contents($source);
        }

        $urls = IconGenerator::generate($binary);
        foreach ($urls as $name => $url) {
            $this->line("  ✓ {$url}");
        }
        $this->info('Generated '.count($urls).' icons'.($source ? " from {$source}" : ' (default Convoro mark)').'.');

        return self::SUCCESS;
    }
}
