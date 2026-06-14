<?php

namespace App\Jobs;

use App\Support\Llm;
use App\Support\Settings;
use App\Support\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Translate a locale's missing UI strings in the background.
 *
 * Runs in SHORT, self-chaining slices instead of one long pass: each run
 * translates batches until a soft time budget, persists after every batch
 * (Translator::translateMissing writes incrementally), then re-dispatches
 * itself to continue the remainder. This keeps every run well under the queue
 * worker timeout — fixing the old "single 30-min job killed at ~96% then
 * MaxAttemptsExceededException" failure. Stops cleanly when done or when a whole
 * run can make no further progress (e.g. budget reached / persistently
 * untranslatable strings), so the admin page never hangs forever.
 */
class TranslateLocaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Per-run wall-clock budget (seconds) — stays under the worker timeout. */
    private const RUN_SECONDS = 30;

    public int $timeout = 110;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function __construct(public string $locale) {}

    public function handle(): void
    {
        if ($this->locale === 'en' || ! Llm::configured()) {
            Settings::set('i18n.translating.'.$this->locale, false);

            return;
        }

        Settings::set('i18n.translating.'.$this->locale, true);

        $deadline = microtime(true) + self::RUN_SECONDS;
        // Clear any prior error from a previous run.
        Settings::set('i18n.error.'.$this->locale, null);

        $runFilled = 0;
        $remaining = count(Translator::missing($this->locale));
        $stalled = false;

        // Translate one batch at a time until we run out of time or strings, or a
        // batch makes no progress (LLM/budget failure — don't spin).
        do {
            $before = $remaining;
            $filled = Translator::translateMissing($this->locale, Translator::BATCH);
            $runFilled += $filled;
            $remaining = count(Translator::missing($this->locale));

            // Strings were translated but the missing count didn't drop — they
            // aren't persisting (e.g. an unwritable lang/ directory). Stop now
            // instead of re-dispatching forever and burning LLM calls.
            if ($filled > 0 && $remaining >= $before) {
                $stalled = true;
                break;
            }
        } while ($filled > 0 && $remaining > 0 && microtime(true) < $deadline);

        Settings::set('i18n.last_translated.'.$this->locale, [
            'filled' => $runFilled,
            'remaining' => $remaining,
        ]);

        if ($stalled) {
            Settings::set('i18n.error.'.$this->locale,
                'Translations could not be saved — the lang/ directory is not writable by the web server.');
            Settings::set('i18n.translating.'.$this->locale, false);

            return;
        }

        if ($remaining > 0 && $runFilled > 0) {
            // Progress made but more to do → continue in a fresh, short job.
            self::dispatch($this->locale)->delay(now()->addSeconds(2));
        } else {
            // Complete, or stalled (no batch could fill anything) — stop here.
            Settings::set('i18n.translating.'.$this->locale, false);
        }
    }

    /** Never leave the "translating" flag stuck on if the job dies. */
    public function failed(\Throwable $e): void
    {
        Settings::set('i18n.translating.'.$this->locale, false);
    }
}
