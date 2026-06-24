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

    /** Which of the given user ids are online now (seen in the last 5 min) — polled to keep presence dots fresh. */
    public function presence(Request $request): JsonResponse
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($i) => (int) trim($i))->filter()->unique()->take(100)->all();

        $online = $ids
            ? \App\Models\User::whereIn('id', $ids)
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->pluck('id')->all()
            : [];

        return response()->json(['online' => $online]);
    }

    public function index(Request $request): Response
    {
        // View preference: explicit ?view wins, else the visitor's saved choice
        // (cookie), else the admin default. Keeps grid/feed sticky between visits.
        $view = $request->query('view');
        if (! in_array($view, ['feed', 'grid', 'category'], true)) {
            $view = $request->cookie('convoro_view');
        }
        $view = in_array($view, ['feed', 'grid', 'category'], true) ? $view : \App\Support\Settings::get('forum.default_view', 'feed');
        $sort = in_array($request->query('sort'), ['recent', 'popular', 'title']) ? $request->query('sort') : 'recent';
        $categorySlug = $request->query('category');

        $query = Topic::query()->visible()
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
                'description' => $c->description,
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

        // Prism hero — the home page gets the community hero; viewing a category
        // surfaces that space's own hero (its icon, colour, blurb). First piece
        // of the Convoro 2 "Prism" concept; the tag/sub-tag heroes follow this
        // path once categories migrate to tags.
        $activeCat = $categorySlug ? collect($categories)->firstWhere('slug', $categorySlug) : null;
        $fmt = fn (int $n) => $n >= 1000 ? round($n / 1000, 1).'k' : (string) $n;
        $hero = $activeCat
            ? [
                'title' => $activeCat['name'],
                'subtitle' => $activeCat['description'] ?: null,
                'icon' => (is_string($activeCat['icon']) && str_starts_with((string) $activeCat['icon'], 'fa-')) ? $activeCat['icon'] : 'fa-solid fa-hashtag',
                'c1' => $activeCat['color'] ?: '#7c3aed',
                'stats' => [['label' => 'topics', 'value' => $fmt((int) $activeCat['count'])]],
            ]
            : [
                'title' => (string) \App\Support\Settings::get('community.name', config('app.name', 'Convoro')),
                'subtitle' => (string) \App\Support\Settings::get('forum.hero_subtitle', 'where every space refracts its own light'),
                'icon' => (string) \App\Support\Settings::get('forum.hero_icon', 'fa-solid fa-meteor'),
                'c1' => (string) \App\Support\Settings::get('forum.hero_c1', '#7c3aed'),
                'c2' => (string) \App\Support\Settings::get('forum.hero_c2', '#ec4899'),
                'image' => ((string) \App\Support\Settings::get('forum.hero_image', '')) ?: null,
                'stats' => [
                    ['label' => 'members', 'value' => $fmt(\App\Models\User::count())],
                    ['label' => 'topics', 'value' => $fmt(Topic::count())],
                ],
            ];

        return Inertia::render('Forum/Index', [
            'view' => $view,
            'hero' => $hero,
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
            // Fallback cover so grid view shows an image even for topics without
            // their own cover. Empty = no fallback (cards render text-only).
            'defaultCover' => (string) \App\Support\Settings::get('forum.default_cover', '') ?: null,
        ]);
    }

    /** Dynamic data for configurable sidebar widgets (cheap, index-only). */
    private function widgetData(): array
    {
        $onlineQuery = \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(5));

        // Trending: most-active topics by replies; fall back to most-viewed so an
        // early forum (nothing replied to yet) still shows something.
        $trending = Topic::query()->visible()->with('category')
            ->where('reply_count', '>', 0)
            ->orderByDesc('reply_count')->orderByDesc('view_count')
            ->limit(5)->get();
        if ($trending->isEmpty()) {
            $trending = Topic::query()->visible()->with('category')->orderByDesc('view_count')->limit(5)->get();
        }

        return [
            'onlineNow' => (clone $onlineQuery)->count(),
            'onlineGuests' => \App\Support\Presence::guestCount(),
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
