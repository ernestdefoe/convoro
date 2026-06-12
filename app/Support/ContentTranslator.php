<?php

namespace App\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * On-the-fly translation of post CONTENT (as opposed to the UI chrome, which
 * Translator/I18n handle). Detects a post's language and translates its HTML
 * body into a reader's language via the LLM, caching the result per
 * (post, locale) keyed on a hash of the source so edits invalidate it.
 *
 * This is what lets a member post in Arabic and have an English-speaking reader
 * see it in English — and vice-versa — automatically.
 */
class ContentTranslator
{
    public static function enabled(): bool
    {
        return Llm::configured() && (bool) Settings::get('translate.posts', true);
    }

    /**
     * Detect the dominant language of an HTML body. Returns an ISO 639-1 code
     * (e.g. "en", "ar", "zh") or null if undetermined.
     */
    public static function detect(string $html): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if (mb_strlen($text) < 8 || ! Llm::configured()) {
            return null;
        }
        $sample = mb_substr($text, 0, 500);

        try {
            $raw = Llm::chat(
                'You are a language detector. Reply with ONLY the ISO 639-1 two-letter code '
                .'of the dominant language of the text (e.g. en, es, ar, zh, ru, ja). '
                .'If you cannot tell, reply "und". No other words.',
                $sample,
                8,
                'detect'
            );
        } catch (\Throwable $e) {
            return null;
        }

        $code = strtolower(trim(preg_replace('/[^a-zA-Z]/', '', $raw) ?? ''));
        $code = substr($code, 0, 2);

        return ($code === '' || $code === 'un') ? null : $code;
    }

    /**
     * Translate a post into $targetLocale. Returns:
     *   ['locale' => code, 'html' => translated|original, 'translated' => bool, 'source' => code|null]
     * If the post is already in the target language, returns the original with translated=false.
     */
    public static function translatePost(Post $post, string $targetLocale): array
    {
        $source = $post->detected_locale;
        $base = I18n::baseLanguage($targetLocale);

        // Already in the reader's language → nothing to do.
        if ($source && I18n::baseLanguage($source) === $base) {
            return ['locale' => $targetLocale, 'html' => $post->body_html, 'translated' => false, 'source' => $source];
        }

        $hash = sha1((string) $post->body_html);

        // Cache hit?
        $cached = DB::table('content_translations')
            ->where('post_id', $post->id)->where('locale', $targetLocale)->where('source_hash', $hash)
            ->value('body_html');
        if ($cached !== null) {
            return ['locale' => $targetLocale, 'html' => $cached, 'translated' => true, 'source' => $source];
        }

        $clean = self::llmTranslate((string) $post->body_html, $targetLocale);
        if ($clean === null) {
            return ['locale' => $targetLocale, 'html' => $post->body_html, 'translated' => false, 'source' => $source];
        }

        DB::table('content_translations')->updateOrInsert(
            ['post_id' => $post->id, 'locale' => $targetLocale],
            ['message_id' => null, 'body_html' => $clean, 'source_hash' => $hash, 'updated_at' => now(), 'created_at' => now()]
        );

        return ['locale' => $targetLocale, 'html' => $clean, 'translated' => true, 'source' => $source];
    }

    /**
     * Translate a direct message into $targetLocale (cached per message+locale),
     * so a member reading in one language sees DMs written in another translated.
     * Same shape/contract as translatePost.
     */
    public static function translateMessage(\App\Models\Message $message, string $targetLocale): array
    {
        $source = $message->detected_locale;
        $base = I18n::baseLanguage($targetLocale);

        if ($source && I18n::baseLanguage($source) === $base) {
            return ['locale' => $targetLocale, 'html' => $message->body_html, 'translated' => false, 'source' => $source];
        }

        $hash = sha1((string) $message->body_html);

        $cached = DB::table('content_translations')
            ->where('message_id', $message->id)->where('locale', $targetLocale)->where('source_hash', $hash)
            ->value('body_html');
        if ($cached !== null) {
            return ['locale' => $targetLocale, 'html' => $cached, 'translated' => true, 'source' => $source];
        }

        $clean = self::llmTranslate((string) $message->body_html, $targetLocale);
        if ($clean === null) {
            return ['locale' => $targetLocale, 'html' => $message->body_html, 'translated' => false, 'source' => $source];
        }

        DB::table('content_translations')->updateOrInsert(
            ['message_id' => $message->id, 'locale' => $targetLocale],
            ['post_id' => null, 'body_html' => $clean, 'source_hash' => $hash, 'updated_at' => now(), 'created_at' => now()]
        );

        return ['locale' => $targetLocale, 'html' => $clean, 'translated' => true, 'source' => $source];
    }

    /**
     * Translate a batch of short plain-text strings (topic titles, category
     * names, excerpts) into $targetLocale, cached forever per (text, locale) so
     * the AI is only hit on the first miss. Returns the array in the same order;
     * any string that can't be translated stays as-is. One LLM call per page of
     * misses keeps this cheap. Used to translate the forum LIST for readers with
     * auto-translate on (post bodies are handled by translatePost on topic pages).
     *
     * @param  string[]  $texts
     * @return string[]
     */
    public static function translateTexts(array $texts, string $targetLocale): array
    {
        $out = array_map(fn ($t) => (string) $t, $texts);
        if (! self::enabled()) {
            return $out;
        }

        $missIdx = [];
        $missTexts = [];
        foreach ($out as $i => $t) {
            if (trim($t) === '') {
                continue;
            }
            $hit = Cache::get(self::textKey($t, $targetLocale));
            if (is_string($hit)) {
                $out[$i] = $hit;
            } else {
                $missIdx[] = $i;
                $missTexts[] = $t;
            }
        }

        if ($missTexts) {
            $translated = self::llmTranslateBatch($missTexts, $targetLocale);
            if (is_array($translated)) {
                foreach ($missIdx as $k => $i) {
                    $tr = $translated[$k] ?? null;
                    if (is_string($tr) && trim($tr) !== '') {
                        Cache::forever(self::textKey($out[$i], $targetLocale), $tr);
                        $out[$i] = $tr;
                    }
                }
            }
        }

        return $out;
    }

    private static function textKey(string $text, string $locale): string
    {
        return 'ctt:'.$locale.':'.sha1($text);
    }

    /** @param string[] $texts @return string[]|null */
    private static function llmTranslateBatch(array $texts, string $targetLocale): ?array
    {
        if (! Llm::configured() || ! $texts) {
            return null;
        }
        $language = I18n::languageName($targetLocale);
        $system = "Translate each string in the given JSON array into {$language}. "
            .'Return ONLY a JSON array of the same length and order, each element the translated string — no keys, no notes, no code fences. '
            .'Keep it natural and concise. Do NOT translate URLs, @mentions, #hashtags, code, or the word "Convoro". Keep emoji as-is.';
        $input = json_encode(array_values($texts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $raw = Llm::chat($system, (string) $input, min(3000, max(400, (int) (mb_strlen((string) $input) / 1.5))), 'translate');
        } catch (\Throwable $e) {
            Log::warning('Batch text translation failed: '.$e->getMessage());

            return null;
        }

        $raw = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($raw)) ?? $raw);
        $arr = json_decode($raw, true);

        return is_array($arr) ? array_map(fn ($x) => is_string($x) ? $x : '', $arr) : null;
    }

    /**
     * Translate an HTML body into $targetLocale via the LLM. Returns sanitized
     * translated HTML, or null if translation is unavailable/failed/empty.
     */
    private static function llmTranslate(string $html, string $targetLocale): ?string
    {
        if (! Llm::configured()) {
            return null;
        }

        $language = I18n::languageName($targetLocale);
        $system = "You are a translator embedded in a forum. Translate the text content of the user's HTML into {$language}. "
            .'STRICT RULES: (1) Output ONLY the translated HTML — same tags, attributes, and structure as the input. '
            .'(2) Do NOT translate text inside <code> or <pre> elements, URLs, @mentions, #hashtags, or the word "Convoro". '
            .'(3) Do not add explanations, notes, or code fences. (4) Keep emoji as-is.';

        $maxTokens = min(4000, max(600, (int) (mb_strlen($html) / 2)));

        try {
            $out = Llm::chat($system, $html, $maxTokens, 'translate');
        } catch (\Throwable $e) {
            Log::warning('Content translation failed: '.$e->getMessage());

            return null;
        }

        $out = trim(preg_replace('/^```(?:html)?|```$/m', '', trim($out)) ?? $out);
        $clean = Content::clean($out);

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }
}
