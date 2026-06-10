<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

use App\Models\Topic;

// Presence on a topic: anyone who can view the topic may join (see who's here).
Broadcast::channel('topic.{topicId}', function ($user, int $topicId) {
    $topic = Topic::find($topicId);
    if (! $topic) {
        return false;
    }
    return [
        'id' => $user->id,
        'name' => $user->name,
        'initials' => strtoupper(mb_substr($user->name, 0, 1)),
        'color' => ($user->id % 6) + 1,
    ];
});
