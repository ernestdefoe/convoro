<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live "someone is typing in this tag" signal. Broadcast on a PUBLIC `tags`
 * channel that the discussion list subscribes to, so it can show an iPhone-style
 * typing bubble beside the tag(s) of whatever topic is being replied to right
 * now. Broadcast-now (not queued) so the indicator feels instant; it's ephemeral
 * and the listener auto-expires it after a few seconds.
 */
class TagTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  string[]  $tags  tag slugs being typed in */
    public function __construct(public array $tags, public string $name) {}

    public function broadcastOn(): Channel
    {
        return new Channel('forum');
    }

    public function broadcastAs(): string
    {
        return 'TagTyping';
    }

    public function broadcastWith(): array
    {
        return ['tags' => $this->tags, 'name' => $this->name];
    }
}
