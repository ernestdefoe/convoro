<?php

namespace App\Jobs;

use App\Support\Settings;
use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Translate all missing UI strings for a locale in the background. Writes
 * progress to Settings so the admin Languages page can poll it.
 */
class TranslateLocaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public string $locale) {}

    public function handle(): void
    {
        Settings::set('i18n.translating.'.$this->locale, true);
        try {
            $filled = Translator::translateMissing($this->locale);
            Settings::set('i18n.last_translated.'.$this->locale, [
                'filled' => $filled,
                'remaining' => count(Translator::missing($this->locale)),
            ]);
        } finally {
            Settings::set('i18n.translating.'.$this->locale, false);
        }
    }
}
