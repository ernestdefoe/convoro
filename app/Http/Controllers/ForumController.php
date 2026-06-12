<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic;
use App\Support\LiveTopics;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ForumController extends Controller
{
    /** Which of the given topic ids are live right now — polled by the forum to keep badges fresh. */
    public function liveTopics(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($i) => (int) trim($i))->filter()->take(50)->all();

        return response()->json(['live' => $ids ? LiveTopics::liveIds($ids) : []]);
    }

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
            ->with(['user.groups', 'category', 'tags', 'firstPost.reactions'])
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)));

        $query->orderByDesc('is_pinned');
        match ($sort) {
            'popular' => $query->orderByDesc('view_count'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('last_post_at'),
        };

        $topics = $query->paginate(20)->withQueryString();
        $unread = Present::unreadTopicIds($topics->items(), $request->user());
        $live = LiveTopics::liveIds(collect($topics->items())->pluck('id')->all());
        $reader = $request->user();
        $actorId = $reader?->id;

        $categories = Category::orderBy('position')->withCount('topics')->get()
            ->map(fn (Category $c) => [
                'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon,
                'color' => $c->color, 'count' => $c->topics_count,
            ])->all();

        $cards = collect($topics->items())
            ->map(fn (Topic $t) => Present::topicCard($t, $actorId, in_array($t->id, $unread, true), $t->is_live || in_array($t->id, $live, true)))
            ->all();

        // For readers with auto-translate on, translate the list's content
        // (category names, topic titles, excerpts) into their language too —
        // post bodies are already translated on the topic page.
        if ($reader?->auto_translate && \App\Support\ContentTranslator::enabled()) {
            $locale = $reader->locale ?: app()->getLocale();
            $catNames = \App\Support\ContentTranslator::translateTexts(array_map(fn ($c) => $c['name'], $categories), $locale);
            foreach ($categories as $i => &$c) {
                $c['name'] = $catNames[$i];
            }
            unset($c);
            $titles = \App\Support\ContentTranslator::translateTexts(array_map(fn ($c) => $c['title'] ?? '', $cards), $locale);
            $excerpts = \App\Support\ContentTranslator::translateTexts(array_map(fn ($c) => $c['excerpt'] ?? '', $cards), $locale);
            foreach ($cards as $i => &$c) {
                $c['title'] = $titles[$i] ?? ($c['title'] ?? '');
                $c['excerpt'] = $excerpts[$i] ?? ($c['excerpt'] ?? '');
            }
            unset($c);
        }

        return Inertia::render('Forum/Index', [
            'view' => $view,
            'sort' => $sort,
            'activeCategory' => $categorySlug,
            'categories' => $categories,
            'topics' => [
                'data' => $cards,
                'next' => $topics->nextPageUrl(),
            ],
            'stats' => [
                'members' => \App\Models\User::count(),
                'topics' => Topic::count(),
                'posts' => \App\Models\Post::count(),
                'reactions' => \App\Models\Reaction::count(),
            ],
            'widgets' => \App\Support\Settings::widgetLayout(),
            'widgetData' => $this->widgetData(),
            'aboutHtml' => (string) \App\Support\Settings::get('widgets.about_html', ''),
            'aboutTitle' => (string) \App\Support\Settings::get('widgets.about_title', ''),
        ]);
    }

    /** Dynamic data for configurable sidebar widgets (cheap, index-only). */
    private function widgetData(): array
    {
        $onlineQuery = \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(5));

        // Trending: most-active topics by replies; fall back to most-viewed so an
        // early forum (nothing replied to yet) still shows something.
        $trending = Topic::query()->with('category')
            ->where('reply_count', '>', 0)
            ->orderByDesc('reply_count')->orderByDesc('view_count')
            ->limit(5)->get();
        if ($trending->isEmpty()) {
            $trending = Topic::query()->with('category')->orderByDesc('view_count')->limit(5)->get();
        }

        return [
            'onlineNow' => (clone $onlineQuery)->count(),
            'onlineUsers' => (clone $onlineQuery)->latest('last_seen_at')->limit(12)->get()
                ->map(fn ($u) => Present::avatar($u))->all(),
            'newestMembers' => \App\Models\User::latest()->limit(6)->get()
                ->map(fn ($u) => Present::avatar($u))->all(),
            'topPosters' => \Illuminate\Support\Facades\DB::table('posts')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->select('users.name', \Illuminate\Support\Facades\DB::raw('COUNT(*) c'))
                ->groupBy('users.id', 'users.name')->orderByDesc('c')->limit(5)->get()
                ->map(fn ($p) => ['name' => $p->name, 'count' => (int) $p->c])->all(),
            'trending' => $trending->map(fn (Topic $t) => [
                'title' => $t->title,
                'slug' => $t->slug,
                'replies' => (int) $t->reply_count,
                'cat' => $t->category?->name,
            ])->all(),
        ];
    }
}
