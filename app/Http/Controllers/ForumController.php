<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic;
use App\Support\Present;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForumController extends Controller
{
    public function index(Request $request): Response
    {
        // View preference: explicit ?view wins, else the visitor's saved choice
        // (cookie), else the admin default. Keeps grid/feed sticky between visits.
        $view = $request->query('view');
        if (! in_array($view, ['feed', 'grid'], true)) {
            $view = $request->cookie('convoro_view');
        }
        $view = in_array($view, ['feed', 'grid'], true) ? $view : \App\Support\Settings::get('forum.default_view', 'feed');
        $sort = in_array($request->query('sort'), ['recent', 'popular', 'title']) ? $request->query('sort') : 'recent';
        $categorySlug = $request->query('category');

        $query = Topic::query()
            ->with(['user', 'category', 'tags', 'firstPost.reactions'])
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)));

        $query->orderByDesc('is_pinned');
        match ($sort) {
            'popular' => $query->orderByDesc('view_count'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('last_post_at'),
        };

        $topics = $query->paginate(20)->withQueryString();

        return Inertia::render('Forum/Index', [
            'view' => $view,
            'sort' => $sort,
            'activeCategory' => $categorySlug,
            'categories' => Category::orderBy('position')->withCount('topics')->get()
                ->map(fn (Category $c) => [
                    'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon,
                    'color' => $c->color, 'count' => $c->topics_count,
                ]),
            'topics' => [
                'data' => collect($topics->items())->map(fn (Topic $t) => Present::topicCard($t)),
                'next' => $topics->nextPageUrl(),
            ],
            'stats' => [
                'members' => \App\Models\User::count(),
                'topics' => Topic::count(),
                'posts' => \App\Models\Post::count(),
                'reactions' => \App\Models\Reaction::count(),
            ],
        ]);
    }
}
