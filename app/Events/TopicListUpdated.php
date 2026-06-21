<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on the public `forum` channel when a publicly-visible topic gains
 * activity — a new topic (`isNew`) or a fresh reply (bump). The forum index
 * listens and upserts its list so new threads appear and replied-to threads
 * jump to the top without a manual refresh. Only ever carries a public topic's
 * card (gated in TopicBroadcast), so nothing private leaks onto the channel.
 */
class TopicListUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $card shaped topic card (Present::topicCard) */
    public function __construct(public array $card, public bool $isNew)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('forum')];
    }

    public function broadcastAs(): string
    {
        return 'TopicListUpdated';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return ['topic' => $this->card, 'isNew' => $this->isNew];
    }
}
