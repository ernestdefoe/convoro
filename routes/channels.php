<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

use App\Models\Conversation;
use App\Models\Topic;

// Private DM channel: only conversation participants may listen.
Broadcast::channel('conversation.{id}', function ($user, int $id) {
    return $user->conversations()->whereKey($id)->exists();
});

// Presence on a topic: anyone who can view the topic may join (see who's here).
Broadcast::channel('topic.{topicId}', function ($user, int $topicId) {
    // withoutGlobalScope so group discussions resolve here too — then gate them
    // to people who can see the group, so a private group's presence and live
    // posts never reach non-members.
    $topic = Topic::withoutGlobalScope(\App\Models\Scopes\SocialGroupTopicScope::class)->find($topicId);
    if (! $topic) {
        return false;
    }
    if ($topic->group_id && ! $topic->group?->isVisibleTo($user)) {
        return false;
    }
    return [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => strtoupper(mb_substr($user->name, 0, 1)),
        'color' => ($user->id % 6) + 1,
    ];
});
