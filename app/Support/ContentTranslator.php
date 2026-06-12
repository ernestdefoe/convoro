<?php

namespace App\Support;

use App\Models\Post;
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

        if (! Llm::configured()) {
            return ['locale' => $targetLocale, 'html' => $post->body_html, 'translated' => false, 'source' => $source];
        }

        $language = I18n::languageName($targetLocale);
        $system = "You are a translator embedded in a forum. Translate the text content of the user's HTML into {$language}. "
            .'STRICT RULES: (1) Output ONLY the translated HTML — same tags, attributes, and structure as the input. '
            .'(2) Do NOT translate text inside <code> or <pre> elements, URLs, @mentions, #hashtags, or the word "Convoro". '
            .'(3) Do not add explanations, notes, or code fences. (4) Keep emoji as-is.';

        $maxTokens = min(4000, max(600, (int) (mb_strlen((string) $post->body_html) / 2)));

        try {
            $out = Llm::chat($system, (string) $post->body_html, $maxTokens, 'translate');
        } catch (\Throwable $e) {
            Log::warning('Post translation failed: '.$e->getMessage());

            return ['locale' => $targetLocale, 'html' => $post->body_html, 'translated' => false, 'source' => $source];
        }

        $out = trim(preg_replace('/^```(?:html)?|```$/m', '', trim($out)) ?? $out);
        $clean = Content::clean($out);

        if (trim(strip_tags($clean)) === '') {
            return ['locale' => $targetLocale, 'html' => $post->body_html, 'translated' => false, 'source' => $source];
        }

        // Upsert the cached translation.
        DB::table('content_translations')->updateOrInsert(
            ['post_id' => $post->id, 'locale' => $targetLocale],
            ['body_html' => $clean, 'source_hash' => $hash, 'updated_at' => now(), 'created_at' => now()]
        );

        return ['locale' => $targetLocale, 'html' => $clean, 'translated' => true, 'source' => $source];
    }
}
