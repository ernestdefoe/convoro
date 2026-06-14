<?php

namespace Convoro\Ext\Calendar;

use Illuminate\Notifications\Notification;

/**
 * A generic, pre-rendered notification for event reminders. Core's Notifier and
 * the notification bell both fall back to `text` for the label, and `external`
 * tells the bell to do a full-page visit to the standalone /events page.
 */
class EventReminderNotification extends Notification
{
    public function __construct(
        public int $eventId,
        public string $text,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'event',
            'text' => $this->text,
            'icon' => '📅',
            'url' => '/events/'.$this->eventId,
            'external' => true,
        ];
    }
}
