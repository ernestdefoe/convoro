<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Topic;
use App\Support\AskIndex;
use App\Support\Present;
use App\Support\Search\SearchManager;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site search. Uses Voyage semantic ranking when the AI index is ready, and
 * falls back to MySQL full-text otherwise — so it works on any install.
 */
class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $author = trim((string) $request->query('author', ''));
        $when = in_array($request->query('when'), ['week', 'month', 'year'], true) ? $request->query('when') : 'all';
        $sort = in_array($request->query('sort'), ['recent', 'replies'], true) ? $request->query('sort') : 'relevance';
        $actorId = $request->user()?->id;
        $results = [];
        $mode = 'text';
        $hasFilters = $category !== '' || $author !== '' || $when !== 'all';

        // Ranked id list from the query (semantic when available, else full-text).
        $rankedIds = null;
        if (mb_strlen($q) >= 2) {
            $ids = [];
            if (AskIndex::ready()) {
                $mode = 'semantic';
                try {
                    $ids = AskIndex::topicIds($q, 30);
                } catch (\Throwable) {
                    $ids = [];
                }
            }
            if (! $ids) {
                $mode = 'text';
                $ids = $this->fulltextTopicIds($q);
            }
            $rankedIds = $ids;
        }

        // Run the search when there's a query OR at least one filter (filters alone
        // turn the page into a browse-by-category/author/date tool).
        if ($rankedIds !== null || $hasFilters) {
            $query = Topic::query()->visible()->with(['user', 'category', 'tags', 'firstPost.reactions'])
                ->when($rankedIds !== null, fn ($qq) => $qq->whereIn('id', $rankedIds ?: [0]))
                ->when($category !== '', fn ($qq) => $qq->whereHas('category', fn ($c) => $c->where('slug', $category)))
                ->when($author !== '', function ($qq) use ($author) {
                    $uid = DB::table('users')->whereRaw('LOWER(name) = ?', [mb_strtolower($author)])->value('id');
                    $qq->where('user_id', $uid ?? -1);
                })
                ->when($when !== 'all', fn ($qq) => $qq->where('last_post_at', '>=', match ($when) {
                    'week' => now()->subWeek(), 'month' => now()->subMonth(), default => now()->subYear(),
                }));

            // Relevance keeps the ranked order (sorted in PHP); without a query it
            // falls back to most-recent. Recent/replies sort in SQL.
            $rankSort = $sort === 'relevance' && $rankedIds !== null;
            if ($sort === 'replies') {
                $query->orderByDesc('reply_count');
            } elseif (! $rankSort) {
                $query->orderByDesc('last_post_at');
            }

            $collection = $query->limit(60)->get();
            if ($rankSort) {
                $order = array_flip($rankedIds);
                $collection = $collection->sortBy(fn (Topic $t) => $order[$t->id] ?? PHP_INT_MAX)->values();
            }
            $collection = $collection->take(30)->values();
            $unread = Present::unreadTopicIds($collection, $request->user());
            $results = $collection->map(fn (Topic $t) => Present::topicCard($t, $actorId, in_array($t->id, $unread, true)))->values()->all();
        }

        return Inertia::render('Search', [
            'q' => $q,
            'results' => $results,
            'mode' => $mode,
            'filters' => ['category' => $category, 'author' => $author, 'when' => $when, 'sort' => $sort],
            'categories' => Category::orderBy('position')->orderBy('name')->get(['name', 'slug'])
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            'seo' => Seo::make(['title' => $q !== '' ? __('Search: :query', ['query' => $q]) : __('Search'), 'description' => __('Search the community.')]),
        ]);
    }

    /**
     * @return int[] ranked, de-duplicated topic ids via the configured search
     * driver — ngram full-text by default (multilingual, works on shared
     * hosting), or a hosted Typesense engine when one is configured.
     */
    private function fulltextTopicIds(string $q): array
    {
        return app(SearchManager::class)->topicIds($q, 30);
    }
}
