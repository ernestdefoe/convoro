<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Models\User;
use App\Notifications\WebPushAlert;
use Illuminate\Notifications\Notification;

/**
 * Persists a notification to the DB (instant), broadcasts it live over the
 * user's private channel (instant, when realtime is on), and fires a browser
 * push (queued, so it never blocks the request — the shared-host path).
 */
class Notifier
{
    public static function send(User $user, Notification $notification): void
    {
        $user->notify($notification);

        // notifications() is ordered newest-first, so first() is the row we just wrote.
        $fresh = $user->notifications()->first();
        if (! $fresh) {
            return;
        }

        $data = is_array($fresh->data) ? $fresh->data : (array) $fresh->data;
        $unread = $user->unreadNotifications()->count();

        broadcast(new NotificationCreated(Present::notification($fresh), (int) $user->id, $unread));

        $user->notify(new WebPushAlert([
            'title' => config('app.name', 'Convoro'),
            'body' => self::label($data),
            'url' => $data['url'] ?? '/',
            'tag' => $data['type'] ?? 'convoro',
        ]));
    }

    /** Human sentence for a notification payload (also used for push body). */
    private static function label(array $data): string
    {
        $actor = $data['actor']['name'] ?? 'Someone';
        $topic = $data['topic']['title'] ?? 'a topic';

        return match ($data['type'] ?? 'reply') {
            'mention' => "{$actor} mentioned you in {$topic}",
            'reaction' => "{$actor} reacted ".($data['emoji'] ?? '')." to your post",
            'wall' => "{$actor} posted on your profile",
            'message' => "{$actor} sent you a message",
            default => "{$actor} replied in {$topic}",
        };
    }
}
