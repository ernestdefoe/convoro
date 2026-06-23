<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message's source language has been detected (async, after the message was
 * already broadcast). Lets conversation viewers with auto-translate on translate
 * it live, instead of only after a page refresh re-fetches the detected locale.
 */
class MessageLocaleDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $messageId,
        public string $detectedLocale,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'MessageLocaleDetected';
    }

    public function broadcastWith(): array
    {
        return ['messageId' => $this->messageId, 'detectedLocale' => $this->detectedLocale];
    }
}
