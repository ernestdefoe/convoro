<?php

namespace App\Support;

use App\Events\NotificationCreated;
use App\Mail\NotificationEmail;
use App\Models\Post;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

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

        // Best-effort live push: the notification is already persisted (above), so
        // a realtime outage (Reverb down) must never 500 the action that triggered
        // it — the recipient still sees it on their next page load.
        try {
            broadcast(new NotificationCreated(Present::notification($fresh), (int) $user->id, $unread));
        } catch (\Throwable $e) {
            report($e);
        }

        // Everything below is rendered server-side, so localize it to the
        // RECIPIENT's language (not the actor's request locale).
        $locale = $user->locale ?: (Settings::get('site.locale') ?: config('app.locale', 'en'));

        $type = $data['type'] ?? 'reply';

        // Browser push (queued), honouring the member's per-type preference.
        if ($user->wantsNotification($type, 'push')) {
            \App\Jobs\SendWebPush::dispatch((int) $user->id, [
                'title' => Settings::get('site.name') ?: config('app.name', 'Convoro'),
                'body' => self::label($data, $locale),
                'url' => $data['url'] ?? '/',
                'tag' => $type,
            ]);
        }

        // Instant email (queued, never blocks the request), honouring the member's
        // per-type preference + a real address.
        if ($user->wantsNotification($type, 'email')
            && Settings::get('mail.configured')
            && $user->isMailable()) {
            // For topic replies/mentions, let the member just reply to the email.
            $replyTo = null;
            if (EmailReply::enabled() && in_array($type, ['reply', 'mention'], true) && ! empty($data['post_id'])) {
                if ($topicId = Post::where('id', $data['post_id'])->value('topic_id')) {
                    $replyTo = EmailReply::addressFor($user, (int) $topicId);
                }
            }
            Mail::to($user->email)->locale($locale)->queue(new NotificationEmail(
                heading: self::label($data, $locale),
                excerpt: $data['excerpt'] ?? null,
                url: url($data['url'] ?? '/'),
                site: (string) (Settings::get('site.name') ?: config('app.name', 'Convoro')),
                replyToAddress: $replyTo,
            ));
        }
    }

    /** Human sentence for a notification payload, in $locale (push/email body). */
    private static function label(array $data, string $locale): string
    {
        $prev = app()->getLocale();
        app()->setLocale($locale);
        try {
            // Generic/extension notifications carry their own pre-rendered copy.
            if (! empty($data['text'])) {
                return (string) $data['text'];
            }

            $actor = $data['actor']['name'] ?? __('Someone');
            $topic = $data['topic']['title'] ?? __('a topic');

            return match ($data['type'] ?? 'reply') {
                'badge' => (string) ($data['text'] ?? __('You earned a badge')),
                'trust_level_up' => __('You reached :level', ['level' => $data['label'] ?? __('a new trust level')]),
                'mention' => __(':actor mentioned you in :topic', ['actor' => $actor, 'topic' => $topic]),
                'reaction' => __(':actor reacted :emoji to your post', ['actor' => $actor, 'emoji' => $data['emoji'] ?? '']),
                'wall' => __(':actor posted on your profile', ['actor' => $actor]),
                'message' => __(':actor sent you a message', ['actor' => $actor]),
                default => __(':actor replied in :topic', ['actor' => $actor, 'topic' => $topic]),
            };
        } finally {
            app()->setLocale($prev);
        }
    }
}
