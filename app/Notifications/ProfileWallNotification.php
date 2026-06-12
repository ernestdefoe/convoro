<?php

namespace App\Notifications;

use App\Models\ProfilePost;
use App\Support\Present;
use Illuminate\Notifications\Notification;

class ProfileWallNotification extends Notification
{
    public function __construct(public ProfilePost $post) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $post = $this->post->loadMissing(['author', 'profileUser']);

        return [
            'type' => 'wall',
            'actor' => Present::avatar($post->author),
            'topic' => ['title' => __('your profile'), 'slug' => ''],
            'post_id' => $post->id,
            'excerpt' => Present::excerpt($post->body_html, 100),
            'url' => '/u/'.$post->profile_user_id,
        ];
    }
}
