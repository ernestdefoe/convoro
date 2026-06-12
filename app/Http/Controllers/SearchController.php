<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Support\AskIndex;
use App\Support\Present;
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
        $actorId = $request->user()?->id;
        $results = [];
        $mode = 'text';

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

            if ($ids) {
                $order = array_flip($ids);
                $results = Topic::with(['user', 'category', 'tags', 'firstPost.reactions'])
                    ->whereIn('id', $ids)->get()
                    ->sortBy(fn (Topic $t) => $order[$t->id] ?? PHP_INT_MAX)
                    ->values()
                    ->map(fn (Topic $t) => Present::topicCard($t, $actorId))
                    ->all();
            }
        }

        return Inertia::render('Search', [
            'q' => $q,
            'results' => $results,
            'mode' => $mode,
            'seo' => Seo::make(['title' => $q !== '' ? __('Search: :query', ['query' => $q]) : __('Search'), 'description' => __('Search the community.')]),
        ]);
    }

    /** @return int[] ranked, de-duplicated topic ids via full-text (LIKE fallback). */
    private function fulltextTopicIds(string $q): array
    {
        try {
            $rows = DB::table('posts')->join('topics', 'topics.id', '=', 'posts.topic_id')
                ->whereRaw('MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE)', [$q])
                ->selectRaw('posts.topic_id, MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE) as score', [$q])
                ->orderByDesc('score')->limit(150)->get();
        } catch (\Throwable) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $rows = DB::table('topics')->where('title', 'like', $like)->limit(150)
                ->get()->map(fn ($t) => (object) ['topic_id' => $t->id]);
        }

        $ids = [];
        foreach ($rows as $r) {
            if (! in_array($r->topic_id, $ids, true)) {
                $ids[] = $r->topic_id;
            }
            if (count($ids) >= 30) {
                break;
            }
        }

        return $ids;
    }
}
