<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Topic;
use App\Support\Content;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request, Topic $topic): RedirectResponse
    {
        abort_if($topic->is_locked, 403);

        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
        ]);

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, 'Empty post.');

        Post::create([
            'topic_id' => $topic->id,
            'user_id' => $request->user()->id,
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
        ]);

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        return back();
    }
}
