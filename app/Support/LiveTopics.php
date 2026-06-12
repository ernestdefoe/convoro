<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Tracks who is viewing each topic right now (via lightweight client heartbeats)
 * so a discussion can show a "LIVE" badge when several members are reading it at
 * the same time. Portable — uses the cache (works on Redis or a plain file cache),
 * not a hard Reverb dependency, so it works on shared hosting too.
 */
class LiveTopics
{
    /** A heartbeat keeps a viewer "present" for this many seconds. */
    private const WINDOW = 45;

    private const TTL = 90;

    /** Minimum concurrent viewers for a topic to count as live. */
    public static function threshold(): int
    {
        return max(2, (int) Settings::get('realtime.live_threshold', 2));
    }

    private static function key(int $topicId): string
    {
        return "live.viewers.{$topicId}";
    }

    /** Record a heartbeat — $userId is viewing $topicId right now. Returns the live count. */
    public static function heartbeat(int $topicId, int $userId): int
    {
        $now = time();
        $map = self::fresh($topicId, $now);
        $map[$userId] = $now;
        Cache::put(self::key($topicId), $map, self::TTL);

        return count($map);
    }

    /** Current number of active viewers for a topic. */
    public static function count(int $topicId): int
    {
        return count(self::fresh($topicId, time()));
    }

    /**
     * Of the given topic ids, which are currently live (>= threshold viewers).
     *
     * @param  array<int>  $topicIds
     * @return array<int>
     */
    public static function liveIds(array $topicIds): array
    {
        $threshold = self::threshold();
        $now = time();
        $live = [];
        foreach (array_unique(array_map('intval', $topicIds)) as $id) {
            if (count(self::fresh($id, $now)) >= $threshold) {
                $live[] = $id;
            }
        }

        return $live;
    }

    /** The viewer map for a topic with stale entries pruned. */
    private static function fresh(int $topicId, int $now): array
    {
        $map = (array) Cache::get(self::key($topicId), []);
        foreach ($map as $uid => $ts) {
            if (! is_int($ts) || $now - $ts > self::WINDOW) {
                unset($map[$uid]);
            }
        }

        return $map;
    }
}
