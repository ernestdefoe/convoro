<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $post shaped post (Present::post) */
    public function __construct(public array $post, public int $topicId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PresenceChannel('topic.' . $this->topicId)];
    }

    public function broadcastAs(): string
    {
        return 'PostCreated';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return ['post' => $this->post];
    }
}
