<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Contracts\TypedNotification;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class InviteAcceptedNotification extends Notification implements TypedNotification
{
    /** @param  User  $joiner  the person who accepted the invite */
    public function __construct(public User $joiner) {}

    public function type(): string
    {
        return 'invite';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invite',
            'actor' => Present::avatar($this->joiner),
            'topic' => ['title' => 'your invite', 'slug' => ''],
            'url' => '/u/'.$this->joiner->id,
        ];
    }
}
