<?php

namespace App\Notifications;

use App\Models\Post;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class ReplyNotification extends Notification
{
    public function __construct(public Post $post) {}

    /** Persist to the database only; live delivery is handled by NotificationCreated. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $post = $this->post->loadMissing(['user', 'topic']);

        return [
            'type' => 'reply',
            'actor' => Present::avatar($post->user),
            'topic' => ['title' => $post->topic->title, 'slug' => $post->topic->slug],
            'post_id' => $post->id,
            'excerpt' => Present::excerpt($post->body_html, 100),
            'url' => '/t/'.$post->topic->slug.'#post-'.$post->id,
        ];
    }
}
