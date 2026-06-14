<?php

namespace App\Notifications;

use App\Models\Message;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class DirectMessageNotification extends Notification
{
    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->message->loadMissing('user');

        return [
            'type' => 'message',
            'actor' => Present::avatar($m->user),
            // No stored title: the dropdown renders a localized "X sent you a
            // message" in the VIEWER's language. (A translated title here would
            // freeze the sender's locale into the notification.)
            'topic' => ['title' => '', 'slug' => ''],
            'post_id' => $m->id,
            'excerpt' => Present::excerpt($m->body_html, 100),
            'url' => '/messages/'.$m->conversation_id,
        ];
    }
}
