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

    /** Cache flag set when the posts-body full-text search times out — on a
     * multi-million-post board scoring a common phrase means evaluating every
     * match, and InnoDB materialises the whole match set even under a LIMIT.
     * Once flagged, searches stay title-only for a while (still ranked, still
     * ngram/multilingual) instead of burning seconds per query. Small boards
     * never trip this and keep full body search. Typesense is the real answer
     * at that scale — this keeps the zero-infra fallback usable, not great. */
    private const BODY_SLOW_FLAG = 'convoro.search.db.body_slow';

    public function topicIds(string $query, int $limit = 30): array
    {
        $ids = [];

        if (DB::getDriverName() === 'mysql') {
            // 1) Topic titles: a 20-50x smaller ngram index — fast even on big
            //    boards, and ranked. NATURAL LANGUAGE MODE + ngram handles
            //    CJK/Thai/etc. (tokenised by character n-grams, not whitespace).
            try {
                foreach (DB::select($this->capped(
                    'SELECT id AS topic_id FROM topics'
                    .' WHERE MATCH(title) AGAINST(? IN NATURAL LANGUAGE MODE)'
                    .' ORDER BY MATCH(title) AGAINST(? IN NATURAL LANGUAGE MODE) DESC LIMIT 150'
                ), [$query, $query]) as $r) {
                    $ids[] = (int) $r->topic_id;
                }
            } catch (\Throwable) {
                // fall through — LIKE below still answers
            }

            // 2) Post bodies top up the rest — skipped while flagged slow.
            if (count($ids) < $limit && ! \Illuminate\Support\Facades\Cache::get(self::BODY_SLOW_FLAG)) {
                try {
                    foreach (DB::select($this->capped(
                        'SELECT DISTINCT topic_id FROM posts'
                        .' WHERE MATCH(body_html) AGAINST(? IN NATURAL LANGUAGE MODE) LIMIT 150'
                    ), [$query]) as $r) {
                        if (! in_array((int) $r->topic_id, $ids, true)) {
                            $ids[] = (int) $r->topic_id;
                        }
                    }
                } catch (\Throwable) {
                    // Timed out (or failed) — too big for interactive body
                    // search right now; go title-only for the next hour.
                    \Illuminate\Support\Facades\Cache::put(self::BODY_SLOW_FLAG, true, 3600);
                }
            }
        }

        // 3) Last resort (non-MySQL drivers, or full-text produced nothing):
        //    plain title LIKE, so search never hard-fails anywhere.
        if (empty($ids)) {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $ids = DB::table('topics')
                ->where('title', 'like', $like)
                ->limit(150)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return array_slice($ids, 0, $limit);
    }

    /** Wrap a SELECT with a 2s server-side execution cap (MariaDB and MySQL
     * spell it differently) so no search can pin a worker. */
    private function capped(string $select): string
    {
        $version = (string) DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        if (str_contains($version, 'MariaDB')) {
            return 'SET STATEMENT max_statement_time=2 FOR '.$select;
        }

        return preg_replace('/^SELECT /', 'SELECT /*+ MAX_EXECUTION_TIME(2000) */ ', $select);
    }
}
