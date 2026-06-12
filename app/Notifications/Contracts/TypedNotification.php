<?php

namespace App\Notifications\Contracts;

/**
 * A notification that carries a stable machine "type" (reply, mention,
 * reaction, wall, message). The type drives per-user mute preferences
 * (see User::wantsNotification) and the live/push label switch.
 */
interface TypedNotification
{
    /** Stable type key, matching the 'type' field in toArray(). */
    public function type(): string;
}
