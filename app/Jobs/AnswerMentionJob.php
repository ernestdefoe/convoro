<?php

namespace App\Jobs;

use App\Models\Post;
use App\Support\AiBot;
use App\Support\Ask;
use App\Support\Present;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reply when a member @mentions the assistant in a post. Unlike the new-topic
 * auto-answer, this is an explicit ask — it isn't keyword-gated, and it grounds
 * the answer in the knowledge base (docs/changelog/curated KB) like Ask Convoro.
 */
class AnswerMentionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $postId) {}

    /** Should this post trigger a bot reply? (the bot is mentioned, by someone else). */
    public static function shouldAnswer(Post $post, array $mentionedIds): bool
    {
        if (! Ask::enabled() || ! Settings::get('ai.mention_reply', true)) {
            return false;
        }
        $bot = AiBot::userId();

        return in_array($bot, array_map('intval', $mentionedIds), true) && (int) $post->user_id !== $bot;
    }

    public function handle(): void
    {
        $post = Post::with('topic')->find($this->postId);
        if (! $post || ! $post->topic) {
            return;
        }
        $bot = AiBot::userId();
        if ((int) $post->user_id === $bot) {
            return; // never answer ourselves
        }

        $topic = $post->topic;
        // The question is the post's text, minus the @mentions, with the topic
        // title for context.
        $text = trim((string) preg_replace('/@[\p{L}0-9_.]{2,40}/u', '', strip_tags((string) $post->body_html)));
        $question = trim($topic->title."\n\n".$text);
        if (mb_strlen(trim($text)) < 2) {
            $question = $topic->title; // bare "@bot" → answer the topic
        }

        $res = Ask::answer($question);
        if (empty($res['answer']) || ! empty($res['error'])) {
            return;
        }

        $reply = $topic->posts()->create([
            'user_id' => $bot,
            'body_html' => self::format($res),
            'is_first' => false,
            'is_ai' => true,
        ]);
        $topic->update([
            'last_post_at' => now(),
            'reply_count' => $topic->posts()->where('is_first', false)->count(),
        ]);

        $reply->load(['user', 'reactions']);
        broadcast(new \App\Events\PostCreated(Present::post($reply, null), $topic->id));
    }

    private static function format(array $res): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $html = '<p>'.nl2br($e($res['answer'])).'</p>';

        $cites = $res['citations'] ?? [];
        if ($cites) {
            $html .= '<p><strong>Sources</strong></p><ul>';
            foreach ($cites as $c) {
                $url = (string) ($c['url'] ?? '');
                $label = '['.(int) $c['n'].'] '.$e($c['title']);
                $html .= '<li>'.($url !== '' ? '<a href="'.$e($url).'">'.$label.'</a>' : $label).'</li>';
            }
            $html .= '</ul>';
        }

        return $html.'<p><small>🤖 AI-generated — a member can confirm or improve this.</small></p>';
    }
}
