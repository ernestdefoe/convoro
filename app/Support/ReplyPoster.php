<?php

namespace App\Support;

use App\Events\PostCreated;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ReplyNotification;

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

        $post->load(['user', 'reactions']);
        broadcast(new PostCreated(Present::post($post, null), $topic->id));

        self::notify($author, $topic, $post, $html);

        Federation::announceReply($post, $topic);
        TrustLevels::evaluate($author);

        return $post;
    }

    /** Mention notifications to those named, reply notifications to participants. */
    private static function notify(User $author, Topic $topic, Post $post, string $html): void
    {
        $authorId = (int) $author->id;

        $mentioned = Mentions::parse(strip_tags($html));
        $mentionedIds = $mentioned->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($mentioned as $user) {
            if ((int) $user->id === $authorId) {
                continue;
            }
            Notifier::send($user, new MentionNotification($post));
        }

        $participantIds = Post::where('topic_id', $topic->id)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $replyIds = array_diff(
            array_unique(array_merge($participantIds, [(int) $topic->user_id])),
            array_merge([$authorId], $mentionedIds),
        );

        if (! empty($replyIds)) {
            foreach (User::whereIn('id', $replyIds)->get() as $user) {
                Notifier::send($user, new ReplyNotification($post));
            }
        }
    }
}
