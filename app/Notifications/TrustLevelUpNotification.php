<?php

namespace App\Notifications;

use App\Support\TrustLevels;
use Illuminate\Notifications\Notification;

class TrustLevelUpNotification extends Notification
{
    public function __construct(public int $level) {}

    /** Persist to the database only; live delivery is handled by NotificationCreated. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trust_level_up',
            'level' => $this->level,
            'label' => TrustLevels::label($this->level),
            'url' => '/u/'.($notifiable->id ?? ''),
        ];
    }
}
