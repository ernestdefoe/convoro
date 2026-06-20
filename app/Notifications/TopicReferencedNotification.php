<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * "X referenced your topic" — sent to a topic's author when someone links to it
 * (a /t/ link or a #123 shorthand) from another topic. Carries pre-rendered
 * `text` so the bell + push/email render it without type-specific frontend code.
 */
class TopicReferencedNotification extends Notification
{
    public function __construct(
        public array $actor,
        public string $text,
        public string $url,
        public ?string $excerpt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reference',
            'actor' => $this->actor,
            'text' => $this->text,
            'excerpt' => $this->excerpt,
            'url' => $this->url,
        ];
    }
}
