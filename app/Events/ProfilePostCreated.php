<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new profile-wall post. Broadcast on a PUBLIC per-profile channel so everyone
 * currently viewing that profile sees the post appear live, instead of only after
 * a reload. The wall is public, so the channel carries no private data; canDelete
 * is recomputed per viewer on the client.
 */
class ProfilePostCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $post shaped post (Present::profilePost) */
    public function __construct(public array $post, public int $profileUserId)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('profile.' . $this->profileUserId);
    }

    public function broadcastAs(): string
    {
        return 'ProfilePostCreated';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        return ['post' => $this->post];
    }
}
