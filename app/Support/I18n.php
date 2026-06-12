<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * UI internationalization. English is the source language (keys = English text),
 * so any string not yet translated simply falls back to English. Translations
 * live in lang/{locale}.json and can be added by the community over time.
 */
class I18n
{
    /** Human labels for known locale codes (native names). */
    private const LABELS = [
        'en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
        'pt' => 'Português', 'pt-BR' => 'Português (Brasil)', 'it' => 'Italiano',
        'nl' => 'Nederlands', 'tr' => 'Türkçe', 'pl' => 'Polski', 'uk' => 'Українська',
        'ru' => 'Русский', 'ja' => '日本語', 'ko' => '한국어', 'zh' => '中文',
        'zh-Hans' => '简体中文', 'zh-Hant' => '繁體中文', 'ar' => 'العربية', 'he' => 'עברית',
        'fa' => 'فارسی', 'ur' => 'اردو', 'hi' => 'हिन्दी', 'bn' => 'বাংলা', 'id' => 'Bahasa Indonesia',
        'ms' => 'Bahasa Melayu', 'vi' => 'Tiếng Việt', 'th' => 'ไทย', 'fil' => 'Filipino',
        'sv' => 'Svenska', 'da' => 'Dansk', 'nb' => 'Norsk', 'fi' => 'Suomi', 'cs' => 'Čeština',
        'sk' => 'Slovenčina', 'ro' => 'Română', 'hu' => 'Magyar', 'el' => 'Ελληνικά',
        'bg' => 'Български', 'sr' => 'Српски', 'hr' => 'Hrvatski', 'ca' => 'Català',
        'sw' => 'Kiswahili', 'ta' => 'தமிழ்', 'te' => 'తెలుగు', 'mr' => 'मराठी', 'gu' => 'ગુજરાતી',
    ];

    /** English language names for AI/translation prompts, keyed by locale code. */
    private const ENGLISH_NAMES = [
        'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese',
        'pt-BR' => 'Brazilian Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'tr' => 'Turkish',
        'pl' => 'Polish', 'uk' => 'Ukrainian', 'ru' => 'Russian', 'ja' => 'Japanese',
        'ko' => 'Korean', 'zh' => 'Chinese', 'zh-Hans' => 'Simplified Chinese',
        'zh-Hant' => 'Traditional Chinese', 'ar' => 'Arabic', 'he' => 'Hebrew', 'fa' => 'Persian',
        'ur' => 'Urdu', 'hi' => 'Hindi', 'bn' => 'Bengali', 'id' => 'Indonesian', 'ms' => 'Malay',
        'vi' => 'Vietnamese', 'th' => 'Thai', 'fil' => 'Filipino', 'sv' => 'Swedish', 'da' => 'Danish',
        'nb' => 'Norwegian', 'fi' => 'Finnish', 'cs' => 'Czech', 'sk' => 'Slovak', 'ro' => 'Romanian',
        'hu' => 'Hungarian', 'el' => 'Greek', 'bg' => 'Bulgarian', 'sr' => 'Serbian', 'hr' => 'Croatian',
        'ca' => 'Catalan', 'sw' => 'Swahili', 'ta' => 'Tamil', 'te' => 'Telugu', 'mr' => 'Marathi',
        'gu' => 'Gujarati', 'en' => 'English',
    ];

    /** English name of a language, for LLM prompts. Falls back to the native label. */
    public static function languageName(string $code): string
    {
        return self::ENGLISH_NAMES[$code] ?? self::ENGLISH_NAMES[explode('-', $code)[0]] ?? self::label($code);
    }

    /** The base language subtag, e.g. "zh-Hant" → "zh". */
    public static function baseLanguage(string $code): string
    {
        return strtolower(explode('-', $code)[0]);
    }

    private const RTL = ['ar', 'he', 'fa', 'ur'];

    /** Available locales: English plus any lang/{code}.json present. @return array<string,string> code=>label */
    public static function available(): array
    {
        $locales = ['en' => self::LABELS['en']];
        foreach (glob(base_path('lang/*.json')) ?: [] as $path) {
            $code = pathinfo($path, PATHINFO_FILENAME);
            // Files prefixed with "_" are infrastructure (e.g. _catalog.json), not locales.
            if (str_starts_with($code, '_')) {
                continue;
            }
            $locales[$code] = self::LABELS[$code] ?? strtoupper($code);
        }

        return $locales;
    }

    /** A human label for a locale code (falls back to the upper-cased code). */
    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? strtoupper($code);
    }

    /** Every locale code we know a label for — the menu for "add a language". @return array<string,string> */
    public static function known(): array
    {
        return self::LABELS;
    }

    /**
     * The master string catalog: every English source string the UI uses, as
     * produced by `php artisan convoro:i18n-scan`. This is the authoritative
     * list of keys a locale must translate for full coverage.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        $path = base_path('lang/_catalog.json');
        if (! File::exists($path)) {
            return [];
        }
        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? array_values($data) : [];
    }

    /** Resolve the request locale: user pref → session → site default → app default. */
    public static function resolve(?string $userLocale, ?string $sessionLocale): string
    {
        $available = array_keys(self::available());
        foreach ([$userLocale, $sessionLocale, Settings::get('site.locale'), config('app.locale', 'en')] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return 'en';
    }

    /** The translation dictionary for a locale (empty for English source). */
    public static function messages(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }
        $path = base_path('lang/'.$locale.'.json');
        if (! File::exists($path)) {
            return [];
        }
        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? $data : [];
    }

    public static function isRtl(string $locale): bool
    {
        return in_array($locale, self::RTL, true);
    }
}
