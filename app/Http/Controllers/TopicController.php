<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Support\Present;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function show(Topic $topic): Response
    {
        $topic->increment('view_count');
        $topic->load(['user', 'category', 'tags', 'posts.user', 'posts.reactions']);
        $actorId = auth()->id();

        return Inertia::render('Topic/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'isLive' => $topic->is_live,
                'isLocked' => $topic->is_locked,
                'category' => $topic->category ? [
                    'name' => $topic->category->name, 'slug' => $topic->category->slug,
                    'color' => $topic->category->color, 'icon' => $topic->category->icon,
                ] : null,
                'tags' => $topic->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug, 'color' => $t->color]),
                'replyCount' => $topic->reply_count,
                'viewCount' => $topic->view_count,
            ],
            'posts' => $topic->posts->sortBy('created_at')->values()->map(fn ($p) => Present::post($p, $actorId)),
            'canReply' => auth()->check() && ! $topic->is_locked,
        ]);
    }
}
