<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Scan the front-end for translatable strings and write the master catalog
 * (lang/_catalog.json). English is the source language, so the catalog is the
 * set of English strings passed to t()/tr() — every locale translates these.
 *
 * Matches literal first arguments of t('…') and the `t as tr` alias tr('…'):
 *   t('Save')                         → "Save"
 *   t('Ask {site}', { site })         → "Ask {site}"
 *   tr('No topics yet')               → "No topics yet"
 * Dynamic keys (variables) are skipped — only string literals are extractable.
 */
class I18nScan extends Command
{
    protected $signature = 'convoro:i18n-scan {--check : Exit non-zero if the catalog would change (CI guard)}';

    protected $description = 'Scan resources/js for t()/tr() strings and rebuild lang/_catalog.json';

    public function handle(): int
    {
        $keys = [];

        // Frontend: t('…') / tr('…') (the `t as tr` alias). \b avoids matching e.g. "format(".
        $jsPattern = '/\b(?:t|tr)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
        foreach ($this->files(resource_path('js'), ['vue', 'ts', 'js']) as $file) {
            $this->harvest(File::get($file), $jsPattern, $keys);
        }

        // Server: __('…'), trans('…'), @lang('…') in PHP and Blade — these resolve
        // from the SAME lang/{locale}.json files, so they share the catalog.
        $phpPattern = '/(?:\b__|\btrans|@lang)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/';
        foreach ([app_path(), base_path('routes'), base_path('database'), resource_path('views')] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach ($this->files($dir, ['php']) as $file) {
                $this->harvest(File::get($file), $phpPattern, $keys);
            }
        }

        $catalog = array_keys($keys);
        sort($catalog, SORT_NATURAL | SORT_FLAG_CASE);

        $path = base_path('lang/_catalog.json');
        $json = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

        if ($this->option('check')) {
            $current = File::exists($path) ? File::get($path) : '';
            if (trim($current) !== trim($json)) {
                $this->error('Catalog is out of date — run `php artisan convoro:i18n-scan`.');

                return self::FAILURE;
            }
            $this->info('Catalog is up to date ('.count($catalog).' strings).');

            return self::SUCCESS;
        }

        File::put($path, $json);
        $this->info('Wrote '.count($catalog).' strings to lang/_catalog.json');

        return self::SUCCESS;
    }

    /** Extract every quoted first-arg matched by $pattern into the $keys set. */
    private function harvest(string $code, string $pattern, array &$keys): void
    {
        if (! preg_match_all($pattern, $code, $m, PREG_SET_ORDER)) {
            return;
        }
        foreach ($m as $match) {
            $raw = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');
            if ($raw === '') {
                continue;
            }
            // Unescape \' \" \\ \n so the catalog holds the real string.
            $key = strtr($raw, ['\\\'' => "'", '\\"' => '"', '\\\\' => '\\', '\\n' => "\n"]);
            $keys[$key] = true;
        }
    }

    /** @return iterable<string> absolute paths to source files with the given extensions */
    private function files(string $root, array $exts): iterable
    {
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            /** @var \SplFileInfo $file */
            if (in_array($file->getExtension(), $exts, true)) {
                yield $file->getPathname();
            }
        }
    }
}
