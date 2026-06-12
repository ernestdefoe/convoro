<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered UI translation. The English source catalog (I18n::catalog()) is the
 * authoritative list of strings; this service fills lang/{locale}.json with
 * machine translations for any keys a locale is missing, in batches, via the
 * configured LLM. Admins can then edit any string by hand.
 *
 * English is the source language and needs no file (key === value).
 */
class Translator
{
    /** How many strings to translate per LLM request. Kept small so each batch
     *  completes fast and well within the HTTP timeout even on slow models
     *  (e.g. opus) — large batches were timing out and leaving locales stuck. */
    public const BATCH = 10;

    /** Max per-string retries per call — bounds run length on slow models. */
    private const RETRY_CAP = 4;

    /** Read a locale's current dictionary. @return array<string,string> */
    public static function dictionary(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }
        $path = self::path($locale);
        if (! File::exists($path)) {
            return [];
        }
        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? $data : [];
    }

    /** Write a locale's dictionary, sorted by key for stable diffs. */
    public static function write(string $locale, array $dict): void
    {
        ksort($dict, SORT_NATURAL | SORT_FLAG_CASE);
        File::put(
            self::path($locale),
            json_encode($dict, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /**
     * Coverage report for every available (and known-but-absent) locale.
     *
     * @return array<int,array{code:string,label:string,translated:int,total:int,percent:int,missing:int,rtl:bool,exists:bool}>
     */
    public static function coverage(): array
    {
        $catalog = I18n::catalog();
        $total = count($catalog);
        $rows = [];

        foreach (I18n::available() as $code => $label) {
            if ($code === 'en') {
                $rows[] = [
                    'code' => 'en', 'label' => $label, 'translated' => $total, 'total' => $total,
                    'percent' => 100, 'missing' => 0, 'rtl' => false, 'exists' => true, 'source' => true,
                ];

                continue;
            }
            $dict = self::dictionary($code);
            $translated = 0;
            foreach ($catalog as $key) {
                if (isset($dict[$key]) && trim((string) $dict[$key]) !== '') {
                    $translated++;
                }
            }
            $rows[] = [
                'code' => $code,
                'label' => $label,
                'translated' => $translated,
                'total' => $total,
                'percent' => $total > 0 ? (int) floor($translated / $total * 100) : 100,
                'missing' => $total - $translated,
                'rtl' => I18n::isRtl($code),
                'exists' => true,
                'source' => false,
            ];
        }

        return $rows;
    }

    /** Catalog keys a locale has not yet translated. @return list<string> */
    public static function missing(string $locale): array
    {
        $dict = self::dictionary($locale);

        return array_values(array_filter(I18n::catalog(), function ($key) use ($dict) {
            return ! isset($dict[$key]) || trim((string) $dict[$key]) === '';
        }));
    }

    /**
     * Translate up to $max missing strings for a locale via the LLM and merge
     * them into the locale file. Returns the number of strings newly filled.
     */
    public static function translateMissing(string $locale, ?int $max = null): int
    {
        if ($locale === 'en' || ! Llm::configured()) {
            return 0;
        }

        $missing = self::missing($locale);
        if ($max !== null) {
            $missing = array_slice($missing, 0, $max);
        }
        if (empty($missing)) {
            return 0;
        }

        $dict = self::dictionary($locale);
        $filled = 0;

        foreach (array_chunk($missing, self::BATCH) as $chunk) {
            $translations = self::translateBatch($locale, $chunk);
            $unfilled = [];
            foreach ($chunk as $key) {
                $value = $translations[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $dict[$key] = $value;
                    $filled++;
                } else {
                    $unfilled[] = $key;
                }
            }

            // A placeholder-heavy string (e.g. "{n} results for "{q}"") can break a
            // whole batch's JSON, leaving the locale stuck near 100%. Retry those
            // one at a time — a single-item request has trivial, reliable JSON.
            // Bounded per call so a queued run stays well under its timeout.
            foreach (array_slice($unfilled, 0, self::RETRY_CAP) as $key) {
                $one = self::translateBatch($locale, [$key]);
                $value = $one[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $dict[$key] = $value;
                    $filled++;
                }
            }

            // Persist after each batch so a long run is crash-safe / resumable.
            self::write($locale, $dict);
        }

        return $filled;
    }

    /**
     * Ask the LLM to translate one batch. Returns a map of English source =>
     * translated string. Robust to the model wrapping JSON in prose/fences.
     *
     * @param  list<string>  $keys
     * @return array<string,string>
     */
    private static function translateBatch(string $locale, array $keys): array
    {
        $language = self::languageName($locale);

        // Number the items so the model can't drop/merge them; we map back by index.
        $items = [];
        foreach (array_values($keys) as $i => $key) {
            $items[(string) $i] = $key;
        }

        $system = "You are a professional software localizer translating a forum platform's UI into {$language}. "
            ."Rules: (1) Translate the VALUE of each numbered string into natural, concise {$language} suitable for buttons, labels and short UI text. "
            ."(2) Preserve every placeholder token EXACTLY as written — both {curly} tokens and :colon tokens (e.g. {name}, :count) must stay byte-for-byte, untranslated. "
            ."(3) Preserve leading/trailing punctuation and ellipses (…). "
            ."(4) Do NOT translate the product name 'Convoro'. "
            ."(5) Keep translations short — UI space is limited. "
            .'Respond with ONLY a JSON object mapping each item number (as a string) to its translation. No commentary, no code fences.';

        $user = "Translate these UI strings to {$language}:\n".json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $raw = Llm::chat($system, $user, 4000, 'ui-translate');
        } catch (\Throwable $e) {
            Log::warning('Translator batch failed: '.$e->getMessage());

            return [];
        }

        $decoded = self::extractJson($raw);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($items as $i => $sourceKey) {
            if (isset($decoded[$i]) && is_string($decoded[$i])) {
                $out[$sourceKey] = trim($decoded[$i]);
            }
        }

        return $out;
    }

    /** Pull the first JSON object out of an LLM response (tolerates fences/prose). */
    private static function extractJson(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw;
        $decoded = json_decode(trim($raw), true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Fallback: grab from first { to last }.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** Human language name for the LLM prompt (more descriptive than the label). */
    private static function languageName(string $locale): string
    {
        return [
            'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese',
            'it' => 'Italian', 'nl' => 'Dutch', 'tr' => 'Turkish', 'ar' => 'Arabic',
            'ru' => 'Russian', 'ja' => 'Japanese', 'zh' => 'Chinese', 'ko' => 'Korean',
            'hi' => 'Hindi', 'zh-Hans' => 'Simplified Chinese', 'zh-Hant' => 'Traditional Chinese',
        ][$locale] ?? I18n::label($locale);
    }

    private static function path(string $locale): string
    {
        return base_path('lang/'.$locale.'.json');
    }
}
