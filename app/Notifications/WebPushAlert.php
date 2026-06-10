<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Fires a browser push for an already-stored in-app notification.
 * Queued (ShouldQueue) so the originating request never blocks on the push
 * service — the cron-driven scheduler drains it on shared hosting; a worker
 * delivers it instantly on a VPS.
 *
 * @property array{title:string,body:string,url:string,tag:string} $payload
 */
class WebPushAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $payload) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->payload['title'])
            ->icon('/icons/icon-192.png')
            ->badge('/icons/favicon-32.png')
            ->body($this->payload['body'])
            ->tag($this->payload['tag'] ?? 'convoro')
            ->data(['url' => $this->payload['url'] ?? '/'])
            ->options(['TTL' => 3600]);
    }
}
