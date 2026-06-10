<?php

namespace App\Notifications;

use App\Models\Post;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class MentionNotification extends Notification
{
    public function __construct(public Post $post) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $post = $this->post->loadMissing(['user', 'topic']);

        return [
            'type' => 'mention',
            'actor' => Present::avatar($post->user),
            'topic' => ['title' => $post->topic->title, 'slug' => $post->topic->slug],
            'post_id' => $post->id,
            'excerpt' => Present::excerpt($post->body_html, 100),
            'url' => '/t/'.$post->topic->slug.'#post-'.$post->id,
        ];
    }
}
