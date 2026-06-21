<?php

namespace App\Jobs;

use App\Models\Topic;
use App\Support\AiBot;
use App\Support\Ask;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "No question goes unanswered": when a new topic still has no human reply,
 * the assistant posts a citation-backed draft answer the community can improve.
 * Makes even a brand-new community feel alive and useful from day one.
 */
class AutoAnswerTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $topicId) {}

    public function handle(): void
    {
        if (! Ask::enabled() || ! Settings::get('ai.autoanswer_enabled', false)) {
            return;
        }

        $topic = Topic::with('posts')->find($this->topicId);
        if (! $topic) {
            return;
        }
        // Category gate: when categories are configured, only answer topics that
        // live in one of them. This is the reliable "only auto-reply in certain
        // categories" control (the keyword gate alone proved too loose).
        if (! self::passesCategoryGate($topic->category_id)) {
            return;
        }
        // Only answer if no one (human or AI) has replied yet.
        if ($topic->posts->where('is_first', false)->isNotEmpty()) {
            return;
        }

        $first = $topic->posts->firstWhere('is_first', true) ?? $topic->posts->first();
        $question = trim($topic->title."\n\n".strip_tags((string) ($first->body_html ?? '')));
        if ($question === '') {
            return;
        }

        // Keyword gate: when keywords are configured, only answer topics that
        // mention at least one of them — so the bot doesn't reply on every topic.
        if (! self::passesKeywordGate($question)) {
            return;
        }

        $res = Ask::answer($question);
        if (empty($res['answer']) || ! empty($res['error'])) {
            return;
        }

        $topic->posts()->create([
            'user_id' => AiBot::userId(),
            'body_html' => self::format($res),
            'is_first' => false,
            'is_ai' => true,
        ]);
        $topic->update([
            'last_post_at' => now(),
            'reply_count' => $topic->posts()->where('is_first', false)->count(),
        ]);
    }

    /**
     * Whether a topic clears the keyword gate. With no keywords configured the
     * bot answers everything (back-compat); with keywords it only answers topics
     * mentioning at least one.
     */
    public static function passesKeywordGate(string $text): bool
    {
        $raw = (string) Settings::get('ai.autoanswer_keywords', '');
        $keywords = collect(preg_split('/[\n,]+/', $raw))
            ->map(fn ($k) => mb_strtolower(trim($k)))->filter()->unique();
        if ($keywords->isEmpty()) {
            return true;
        }
        $haystack = mb_strtolower($text);

        return $keywords->contains(fn ($k) => str_contains($haystack, $k));
    }

    /**
     * Whether a topic clears the category gate. With no categories configured
     * the bot may answer in any category (back-compat); with categories set it
     * only answers topics that belong to one of them.
     */
    public static function passesCategoryGate(?int $categoryId): bool
    {
        $ids = Settings::get('ai.autoanswer_categories', []);
        $ids = is_array($ids) ? array_map('intval', $ids) : [];
        if ($ids === []) {
            return true;
        }

        return $categoryId !== null && in_array((int) $categoryId, $ids, true);
    }

    /** Build the assistant's reply HTML (answer + sources + disclaimer). */
    private static function format(array $res): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $html = '<p><em>Here’s a quick answer while you wait for the community — please confirm or improve it.</em></p>';
        $html .= '<p>'.nl2br($e($res['answer'])).'</p>';

        $cites = $res['citations'] ?? [];
        if ($cites) {
            $html .= '<p><strong>Sources</strong></p><ul>';
            foreach ($cites as $c) {
                $html .= '<li><a href="'.$e($c['url']).'">['.(int) $c['n'].'] '.$e($c['title']).'</a></li>';
            }
            $html .= '</ul>';
        }

        return $html.'<p><small>🤖 AI-generated draft — a community member can confirm, improve, or replace this answer.</small></p>';
    }
}
