<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic;
use App\Support\LiveTopics;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $tagSlug = $request->query('tag');

        $query = Topic::query()->visible()
            ->with(['user.groups', 'category', 'tags', 'firstPost.reactions'])
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->when($tagSlug, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $tagSlug)));

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

        // Cached: withCount('topics') is a COUNT per category — cheap on a small
        // board, a table scan per category on a big one. Counts may lag 5 min.
        $categories = Cache::remember('convoro.forum.categories', 300, fn () => Category::orderBy('position')->withCount('topics')->get()
            ->map(fn (Category $c) => [
                'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon,
                'color' => $c->color, 'count' => $c->topics_count,
                'description' => $c->description,
            ])->all());

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

        // ── Prism tags ──────────────────────────────────────────────────────
        // Tags (with sub-tags) are the primary navigation in Convoro 2: each is
        // its own space with a customizable hero. Categories were imported into
        // this tag tree. The home page shows the community hero.
        // Cached: the tree itself rarely changes and the per-tag topic counts are
        // pivot COUNTs — one per tag per render otherwise. Counts may lag 5 min.
        $tags = Cache::remember('convoro.forum.tagtree', 300, function () {
            $tagTree = \App\Models\Tag::query()->whereNull('parent_id')
                ->withCount('topics')->orderBy('position')->orderBy('name')
                ->with(['children' => fn ($c) => $c->withCount('topics')->orderBy('position')->orderBy('name')])
                ->get();
            $mapTag = fn (\App\Models\Tag $t) => [
                'name' => $t->name, 'slug' => $t->slug, 'icon' => $t->icon,
                'color' => $t->color, 'count' => $t->topics_count,
            ];

            return $tagTree->map(fn (\App\Models\Tag $t) => $mapTag($t) + ['children' => $t->children->map($mapTag)->all()])->all();
        });

        $activeTag = $tagSlug ? \App\Models\Tag::where('slug', $tagSlug)->first() : null;
        $subtags = null;
        if ($activeTag) {
            $parent = $activeTag->parent_id ? $activeTag->parent : $activeTag;
            $subtags = [
                'parent' => ['name' => $parent->name, 'slug' => $parent->slug],
                'active' => $activeTag->slug,
                'items' => $parent->children()->orderBy('position')->orderBy('name')->get()
                    ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])->all(),
            ];
        }

        // Community-wide counts are COUNT(*) table scans on InnoDB — never run
        // them per request on a big board. Staleness up to 10 min is fine here.
        // Sandbox tags are practice areas — their topics/posts don't count.
        $counts = Cache::remember('convoro.stats.counts', 600, function () {
            $sandboxTopics = \App\Support\SandboxTags::topicIds();
            $topics = Topic::query();
            $posts = \App\Models\Post::query();
            if ($sandboxTopics !== null) {
                $topics->whereNotIn('id', $sandboxTopics);
                $posts->whereNotIn('topic_id', $sandboxTopics);
            }

            return [
                'members' => \App\Models\User::count(),
                'topics' => $topics->count(),
                'posts' => $posts->count(),
                'reactions' => \App\Models\Reaction::count(),
            ];
        });

        $fmt = fn (int $n) => $n >= 1000 ? round($n / 1000, 1).'k' : (string) $n;
        $hero = $activeTag
            ? $activeTag->heroConfig() + ['stats' => [['label' => 'Topics', 'icon' => 'fa-solid fa-comments', 'value' => $fmt(
                (int) Cache::remember('convoro.stats.tag_topics.'.$activeTag->id, 600, fn () => $activeTag->topics()->count())
            )]]]
            : [
                'title' => (string) \App\Support\Settings::get('community.name', config('app.name', 'Convoro')),
                'subtitle' => (string) \App\Support\Settings::get('forum.hero_subtitle', 'where every tag refracts its own light'),
                'icon' => (string) \App\Support\Settings::get('forum.hero_icon', 'fa-solid fa-meteor'),
                'c1' => (string) \App\Support\Settings::get('forum.hero_c1', '#7c3aed'),
                'c2' => (string) \App\Support\Settings::get('forum.hero_c2', '#ec4899'),
                'image' => ((string) \App\Support\Settings::get('forum.hero_image', '')) ?: null,
                'height' => ((int) \App\Support\Settings::get('forum.hero_height', 0)) ?: null,
                'width' => ((int) \App\Support\Settings::get('forum.hero_width', 0)) ?: null,
                'stats' => [
                    ['label' => 'Members', 'icon' => 'fa-solid fa-users', 'value' => $fmt($counts['members'])],
                    ['label' => 'Topics', 'icon' => 'fa-solid fa-comments', 'value' => $fmt($counts['topics'])],
                    ['label' => 'Posts', 'icon' => 'fa-solid fa-message', 'value' => $fmt($counts['posts'])],
                    ['label' => 'Reactions', 'icon' => 'fa-solid fa-heart', 'value' => $fmt($counts['reactions'])],
                ],
            ];

        // "Hottest" topic = most engaged recently (replies + views), used as the
        // featured lead card on the feed. Falls back to null → the page uses the
        // top list item instead.
        // The engagement-score ORDER BY can't use an index, so it scans every
        // topic active in the window. Cache the winning id (actor-independent)
        // and refetch the row per request so unread/live flags stay personal.
        $hotId = Cache::remember('convoro.feed.hot.'.($tagSlug ?: '_all'), 300, fn () => Topic::query()->visible()
            ->when($tagSlug, fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $tagSlug)))
            ->where('last_post_at', '>=', now()->subDays(21))
            ->orderByRaw('(reply_count * 25 + view_count * 0.06) DESC')
            ->value('id') ?? 0);
        $hot = $hotId
            ? Topic::query()->visible()->with(['user.groups', 'category', 'tags', 'firstPost.reactions'])->find($hotId)
            : null;
        $featured = $hot
            ? Present::topicCard($hot, $actorId, in_array($hot->id, $unread, true), $hot->is_live || in_array($hot->id, $live, true))
            : null;

        return Inertia::render('Forum/Index', [
            'view' => $view,
            'hero' => $hero,
            'featured' => $featured,
            'tags' => $tags,
            'subtags' => $subtags,
            'activeTag' => $tagSlug,
            'sort' => $sort,
            'activeCategory' => $categorySlug,
            'categories' => $categories,
            'topics' => [
                'data' => $cards,
                'next' => $topics->nextPageUrl(),
            ],
            'stats' => $counts,
            'widgets' => \App\Support\Settings::widgetLayout(),
            'widgetData' => $this->widgetData(),
            'aboutHtml' => (string) \App\Support\Settings::get('widgets.about_html', ''),
            'aboutTitle' => (string) \App\Support\Settings::get('widgets.about_title', ''),
            // Fallback cover so grid view shows an image even for topics without
            // their own cover. Empty = no fallback (cards render text-only).
            'defaultCover' => (string) \App\Support\Settings::get('forum.default_cover', '') ?: null,
        ]);
    }

    /** Dynamic data for configurable sidebar widgets. Everything here is
     * actor-independent, so each block is cached: presence briefly (it should
     * feel live), the heavier aggregates for minutes. */
    private function widgetData(): array
    {
        $online = Cache::remember('convoro.widgets.online', 60, function () {
            $onlineQuery = \App\Models\User::where('last_seen_at', '>=', now()->subMinutes(5));

            return [
                'onlineNow' => (clone $onlineQuery)->count(),
                'onlineUsers' => (clone $onlineQuery)->latest('last_seen_at')->limit(12)->get()
                    ->map(fn ($u) => Present::avatar($u))->all(),
            ];
        });

        // Trending: most-active topics by replies; fall back to most-viewed so an
        // early forum (nothing replied to yet) still shows something. The ORDER BY
        // has no covering index, hence the cache. Sandbox tags never trend.
        $trending = Cache::remember('convoro.widgets.trending', 300, function () {
            $sandbox = \App\Models\Tag::query()->where('is_sandbox', true)->pluck('id');
            $scope = fn ($q) => $sandbox->isEmpty()
                ? $q
                : $q->whereDoesntHave('tags', fn ($t) => $t->whereIn('tags.id', $sandbox));

            $trending = $scope(Topic::query()->visible()->with('category'))
                ->where('reply_count', '>', 0)
                ->orderByDesc('reply_count')->orderByDesc('view_count')
                ->limit(5)->get();
            if ($trending->isEmpty()) {
                $trending = $scope(Topic::query()->visible()->with('category'))->orderByDesc('view_count')->limit(5)->get();
            }

            return $trending->map(fn (Topic $t) => [
                'title' => $t->title,
                'slug' => $t->slug,
                'replies' => (int) $t->reply_count,
                'cat' => $t->category?->name,
            ])->all();
        });

        return [
            'onlineNow' => $online['onlineNow'],
            'onlineGuests' => \App\Support\Presence::guestCount(),
            'onlineUsers' => $online['onlineUsers'],
            'newestMembers' => Cache::remember('convoro.widgets.newest', 300, fn () => \App\Models\User::latest()->limit(6)->get()
                ->map(fn ($u) => Present::avatar($u))->all()),
            // GROUP BY over the whole posts table — by far the most expensive
            // widget on a big board; refreshing every 15 min is plenty.
            'topPosters' => Cache::remember('convoro.widgets.top_posters', 900, fn () => \Illuminate\Support\Facades\DB::table('posts')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->select('users.name', \Illuminate\Support\Facades\DB::raw('COUNT(*) c'))
                ->groupBy('users.id', 'users.name')->orderByDesc('c')->limit(5)->get()
                ->map(fn ($p) => ['name' => $p->name, 'count' => (int) $p->c])->all()),
            'trending' => $trending,
        ];
    }
}
