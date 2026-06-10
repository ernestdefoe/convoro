<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushes a freshly-created notification to its recipient in real time.
 * ShouldBroadcastNow → delivered synchronously, no queue worker required (mirrors PostCreated).
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public array $notification,
        public int $userId,
        public int $unread,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'unread' => $this->unread,
        ];
    }
}
