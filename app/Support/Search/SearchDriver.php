<?php

namespace App\Support\Search;

/**
 * A pluggable full-text search backend. Implementations resolve a free-text
 * query into a ranked list of topic ids; the SearchController layers Voyage
 * semantic re-ranking on top when the Ask index is warm.
 */
interface SearchDriver
{
    /**
     * Whether this driver can serve a query right now. The manager falls back
     * to the always-available database driver when this returns false.
     */
    public function available(): bool;

    /**
     * Resolve a query into ranked, de-duplicated topic ids.
     *
     * @return int[]
     */
    public function topicIds(string $query, int $limit = 30): array;
}
