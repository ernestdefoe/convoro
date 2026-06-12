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
        // Only answer if no one (human or AI) has replied yet.
        if ($topic->posts->where('is_first', false)->isNotEmpty()) {
            return;
        }

        $first = $topic->posts->firstWhere('is_first', true) ?? $topic->posts->first();
        $question = trim($topic->title."\n\n".strip_tags((string) ($first->body_html ?? '')));
        if ($question === '') {
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
