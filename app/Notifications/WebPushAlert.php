<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Fires a browser push for an already-stored in-app notification.
 * Dispatched (and error-swallowed) via App\Jobs\SendWebPush so a stale/corrupt
 * device subscription can never surface as a "failed job" — push is best-effort
 * and the same alert is always delivered in-app + by email anyway.
 *
 * @property array{title:string,body:string,url:string,tag:string} $payload
 */
class WebPushAlert extends Notification
{
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
