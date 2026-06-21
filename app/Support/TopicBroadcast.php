<?php

namespace App\Support;

use App\Events\TopicListUpdated;
use App\Models\Topic;

/**
 * Pushes live topic-list updates to the public `forum` channel. Gated to
 * publicly-visible topics only (no group, not hidden), so a private group's or
 * a held topic's activity never reaches the public feed. Best-effort: a
 * realtime outage must never break posting.
 */
class TopicBroadcast
{
    public static function activity(Topic $topic, bool $isNew): void
    {
        if ($topic->group_id || $topic->hidden) {
            return;
        }

        try {
            $topic->loadMissing(['user.groups', 'category', 'tags', 'firstPost.reactions']);
            $card = Present::topicCard($topic, null, false, (bool) $topic->is_live);
            broadcast(new TopicListUpdated($card, $isNew));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
