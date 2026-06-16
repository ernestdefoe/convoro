<?php

namespace App\Notifications;

use App\Models\SocialGroup;
use App\Models\Topic;
use App\Models\User;
use App\Support\Present;
use Illuminate\Notifications\Notification;

/**
 * One notification covering every Social Groups event a member cares about:
 * a new discussion, a join request (to staff), an approval, or an invite.
 * Stores IDs + a localized line only — never raw post content.
 */
class GroupActivityNotification extends Notification
{
    public function __construct(
        public SocialGroup $group,
        public User $actor,
        public string $kind, // new_discussion | join_request | request_approved | invited
        public ?Topic $topic = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $base = '/groups/'.$this->group->slug;
        $url = $this->topic ? $base.'/d/'.$this->topic->id : $base;

        $text = match ($this->kind) {
            'new_discussion' => __(':name posted in :group', ['name' => $this->actor->name, 'group' => $this->group->name]),
            'join_request' => __(':name asked to join :group', ['name' => $this->actor->name, 'group' => $this->group->name]),
            'request_approved' => __('You were approved to join :group', ['group' => $this->group->name]),
            'invited' => __(':name added you to :group', ['name' => $this->actor->name, 'group' => $this->group->name]),
            default => $this->group->name,
        };

        return [
            'type' => 'group_'.$this->kind,
            'actor' => Present::avatar($this->actor),
            'group' => ['name' => $this->group->name, 'slug' => $this->group->slug],
            'topic' => $this->topic ? ['id' => $this->topic->id, 'title' => $this->topic->title] : null,
            'text' => $text,
            'url' => $url,
        ];
    }
}
