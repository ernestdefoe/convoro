<?php

namespace App\Support;

use App\Jobs\AutoAnswerTopicJob;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a published topic from a set of data — shared by immediate publishing
 * (the composer) and deferred publishing (a saved draft or a scheduled post).
 * Expects body_html to already be sanitized by the caller.
 */
class TopicPublisher
{
    /**
     * @param  array{title:string,body_html:string,body_json?:?string,category_id?:?int,tags?:array,cover?:?string,poll?:?array}  $data
     */
    public static function publish(User $user, array $data, ?string $ip = null): Topic
    {
        $slug = (Str::slug($data['title']) ?: 'topic').'-'.Str::lower(Str::random(6));

        $topic = Topic::create([
            'title' => $data['title'],
            'slug' => $slug,
            'user_id' => $user->id,
            'category_id' => $data['category_id'] ?? null,
            'cover_image' => $data['cover'] ?? null,
            'last_post_at' => now(),
        ]);

        $firstPost = $topic->posts()->create([
            'user_id' => $user->id,
            'ip_address' => $ip,
            'body_html' => $data['body_html'],
            'body_json' => $data['body_json'] ?? null,
            'is_first' => true,
        ]);

        if (! empty($data['tags'])) {
            // Only attach tags that still exist.
            $topic->tags()->sync(\App\Models\Tag::whereIn('id', (array) $data['tags'])->pluck('id')->all());
        }

        $poll = $data['poll'] ?? null;
        if (! empty($poll['question'])) {
            $options = collect($poll['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->take(10)->values();
            if ($options->count() >= 2) {
                $pollModel = $topic->poll()->create([
                    'question' => $poll['question'],
                    'max_choices' => ! empty($poll['multiple']) ? $options->count() : 1,
                    'closes_at' => ! empty($poll['closes_days']) ? now()->addDays((int) $poll['closes_days']) : null,
                ]);
                $options->each(fn ($text, $i) => $pollModel->options()->create(['text' => $text, 'position' => $i]));
            }
        }

        // If the opening post @mentions the assistant, answer that explicitly
        // (grounded, no keyword gate). Otherwise fall back to the new-topic
        // auto-answer when enabled — never both.
        $mentionedIds = \App\Support\Mentions::parse(strip_tags($data['body_html']))->pluck('id')->all();
        if (\App\Jobs\AnswerMentionJob::shouldAnswer($firstPost, $mentionedIds)) {
            \App\Jobs\AnswerMentionJob::dispatch($firstPost->id)->afterCommit();
        } elseif (Ask::enabled() && Settings::get('ai.autoanswer_enabled', false)) {
            $delay = max(0, (int) Settings::get('ai.autoanswer_delay_minutes', 0));
            AutoAnswerTopicJob::dispatch($topic->id)->delay(now()->addMinutes($delay))->afterCommit();
        }

        // Broadcast to the fediverse (no-op unless federation is enabled + has followers).
        \App\Support\Federation::announceTopic($topic);

        // Reusable hook for extensions reacting to genuinely-published topics
        // (e.g. Facebook auto-post) — not fired for imports/seeds/demos.
        event(new \App\Events\TopicPublished($topic));

        return $topic;
    }
}
