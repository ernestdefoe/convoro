<?php

namespace App\Console\Commands;

use App\Jobs\TranslateLocaleJob;
use App\Support\I18n;
use App\Support\Llm;
use App\Support\Settings;
use App\Support\Translator;
use Illuminate\Console\Command;

/**
 * Queue AI translation of any UI strings that are in the catalog but missing
 * from an active locale. Run after convoro:i18n-scan (e.g. on deploy) so newly
 * added strings get translated instead of silently falling back to English.
 * TranslateLocaleJob self-chains until each locale is fully covered.
 */
class I18nTranslate extends Command
{
    protected $signature = 'convoro:i18n-translate {--locale= : Only this locale (default: all active non-English locales)}';

    protected $description = 'Queue AI translation of UI strings missing from each active locale';

    public function handle(): int
    {
        if (! Llm::configured()) {
            $this->warn('No AI provider configured — skipping UI translation.');

            return self::SUCCESS;
        }

        $only = $this->option('locale');
        $locales = $only ? [$only] : array_keys(I18n::available());

        $dispatched = 0;
        foreach ($locales as $locale) {
            if ($locale === 'en') {
                continue;
            }
            if (! array_key_exists($locale, I18n::available())) {
                $this->warn("Skipping unknown locale: {$locale}");

                continue;
            }
            // Already self-chaining a run, or nothing missing — leave it alone.
            if (Settings::get('i18n.translating.'.$locale, false)) {
                continue;
            }
            if (count(Translator::missing($locale)) === 0) {
                continue;
            }
            TranslateLocaleJob::dispatch($locale);
            $dispatched++;
            $this->info("Queued translation for {$locale}.");
        }

        $this->info("Dispatched {$dispatched} locale(s).");

        return self::SUCCESS;
    }
}
