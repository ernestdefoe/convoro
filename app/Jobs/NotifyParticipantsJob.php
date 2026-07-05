<?php

namespace App\Jobs;

use App\Models\Post;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\ReplyNotification;
use App\Support\Mentions;
use App\Support\Notifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans out mention + reply notifications for a new post. Queued so replying in
 * a topic with hundreds of participants doesn't serialize all those
 * notification writes and broadcasts into the poster's request. Recipients
 * match the old inline fanout exactly: everyone @mentioned, then every
 * distinct topic participant (+ the topic author), minus the poster and the
 * already-mentioned.
 */
class NotifyParticipantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // a retry would double-send notifications

    public function __construct(public int $postId) {}

    public function handle(): void
    {
        $post = Post::with(['topic', 'user'])->find($this->postId);
        if (! $post || ! $post->topic || $post->hidden) {
            return; // deleted or moderated away before the queue got here
        }
        $topic = $post->topic;
        $authorId = (int) $post->user_id;

        $mentioned = Mentions::parse(strip_tags((string) $post->body_html));
        $mentionedIds = $mentioned->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($mentioned as $user) {
            if ((int) $user->id === $authorId) {
                continue;
            }
            Notifier::send($user, new MentionNotification($post));
        }

        // DISTINCT in SQL: the old inline pluck returned one row per post, so a
        // 10k-post topic shipped 10k ids to PHP just to dedupe them.
        $participantIds = Post::where('topic_id', $topic->id)
            ->whereNotNull('user_id')->distinct()->pluck('user_id')
            ->map(fn ($id) => (int) $id)->all();
        $replyIds = array_diff(
            array_unique(array_merge($participantIds, [(int) $topic->user_id])),
            array_merge([$authorId], $mentionedIds),
        );

        // One pending notification per member per topic: anyone who still has
        // an UNREAD reply notification for this topic isn't notified again —
        // they already know there's new activity. This is what keeps a
        // megathread from generating thousands of writes per reply (and from
        // burying its participants): steady-state fanout is only the members
        // who've seen their last notification.
        if ($replyIds) {
            $alreadyPending = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', array_values($replyIds))
                ->where('type', ReplyNotification::class)
                ->whereNull('read_at')
                ->where('data->topic->slug', $topic->slug)
                ->pluck('notifiable_id')
                ->map(fn ($id) => (int) $id)->all();
            $replyIds = array_diff($replyIds, $alreadyPending);
        }

        if (! empty($replyIds)) {
            User::whereIn('id', $replyIds)->orderBy('id')
                ->chunk(200, function ($users) use ($post) {
                    foreach ($users as $user) {
                        Notifier::send($user, new ReplyNotification($post));
                    }
                });
        }
    }
}
