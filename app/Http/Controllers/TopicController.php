<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use App\Models\Topic;
use App\Support\Content;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Topic/Create', [
            'categories' => Category::orderBy('position')->get(['id', 'name', 'icon', 'color']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tags' => ['array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'body_html' => ['required', 'string', 'max:120000'],
            'body_json' => ['nullable', 'string', 'max:200000'],
            'cover' => ['nullable', 'string', 'max:2048'],
        ]);

        $html = Content::clean($data['body_html']);
        abort_if(trim(strip_tags($html)) === '', 422, 'The post body is empty.');

        $base = Str::slug($data['title']) ?: 'topic';
        $slug = $base.'-'.Str::lower(Str::random(6));

        $topic = Topic::create([
            'title' => $data['title'],
            'slug' => $slug,
            'user_id' => $request->user()->id,
            'category_id' => $data['category_id'] ?? null,
            'cover_image' => $data['cover'] ?? null,
            'last_post_at' => now(),
        ]);

        $topic->posts()->create([
            'user_id' => $request->user()->id,
            'body_html' => $html,
            'body_json' => $data['body_json'] ?? null,
            'is_first' => true,
        ]);

        if (! empty($data['tags'])) {
            $topic->tags()->sync($data['tags']);
        }

        return redirect()->route('topics.show', $topic);
    }

    public function show(Topic $topic): Response
    {
        $topic->increment('view_count');
        $topic->load(['user', 'category', 'tags', 'posts.user', 'posts.reactions']);
        $actor = auth()->user();
        $actor?->loadMissing('groups');
        $actorId = $actor?->id;

        return Inertia::render('Topic/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'cover' => $topic->cover_image,
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
            'posts' => $topic->posts->sortBy('created_at')->values()->map(fn ($p) => Present::post($p, $actorId, $actor)),
            'canReply' => auth()->check() && ! $topic->is_locked,
        ]);
    }
}
