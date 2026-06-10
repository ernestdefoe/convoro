<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class ReactionNotification extends Notification
{
    public function __construct(public Post $post, public User $actor, public string $emoji) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $post = $this->post->loadMissing('topic');

        return [
            'type' => 'reaction',
            'actor' => Present::avatar($this->actor),
            'topic' => ['title' => $post->topic->title, 'slug' => $post->topic->slug],
            'post_id' => $post->id,
            'emoji' => $this->emoji,
            'excerpt' => Present::excerpt($post->body_html, 100),
            'url' => '/t/'.$post->topic->slug.'#post-'.$post->id,
        ];
    }
}
