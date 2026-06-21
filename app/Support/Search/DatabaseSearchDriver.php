<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\DB;

/**
 * The zero-infrastructure default — works on ANY host, including shared
 * hosting. On MySQL/MariaDB it uses the ngram full-text index (multilingual,
 * incl. languages without word spacing); everywhere else it degrades to a
 * title LIKE so search never hard-fails. This is what makes "search that works
 * in all languages" possible without standing up a Typesense/Meilisearch
 * daemon the host may not allow.
 */
class DatabaseSearchDriver implements SearchDriver
{
    public function available(): bool
    {
        return true;
    }

    public function topicIds(string $query, int $limit = 30): array
    {
        $rows = [];

        try {
            if (DB::getDriverName() !== 'mysql') {
                throw new \RuntimeException('full-text unsupported on this driver');
            }

            // NATURAL LANGUAGE MODE against the ngram index handles CJK/Thai/etc.
            // (the ngram parser tokenises by character n-grams, not whitespace).
            $rows = DB::table('posts')
                ->join('topics', 'topics.id', '=', 'posts.topic_id')
                ->whereRaw('MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
                ->selectRaw('posts.topic_id, MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE) as score', [$query])
                ->orderByDesc('score')
                ->limit(150)
                ->get();
        } catch (\Throwable) {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $rows = DB::table('topics')
                ->where('title', 'like', $like)
                ->limit(150)
                ->get()
                ->map(fn ($t) => (object) ['topic_id' => $t->id]);
        }

        $ids = [];
        foreach ($rows as $r) {
            if (! in_array($r->topic_id, $ids, true)) {
                $ids[] = $r->topic_id;
            }
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }
}
