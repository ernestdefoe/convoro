<?php

namespace App\Support;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

/**
 * Creates a reply post and runs the same side-effects the web composer does —
 * counts, live broadcast, mention/reply notifications, federation, trust
 * re-evaluation. Shared so an email reply behaves identically to a web reply.
 */
class ReplyPoster
{
    public static function create(User $author, Topic $topic, string $html, ?string $ip = null): Post
    {
        $post = Post::create([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'ip_address' => $ip,
            'body_html' => $html,
        ]);

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        // Best-effort like the web composer: a realtime outage must never
        // fail the reply itself (this path serves reply-by-email too).
        $post->load(['user', 'reactions']);
        try {
            broadcast(new PostCreated(Present::post($post, null), $topic->id));
        } catch (\Throwable $e) {
            report($e);
        }

        // Same queued mention/reply fanout as the web composer.
        \App\Jobs\NotifyParticipantsJob::dispatch($post->id)->afterCommit();

        Federation::announceReply($post, $topic);
        TrustLevels::evaluate($author);

        return $post;
    }
}
