<?php

namespace App\Support\Search;

use App\Models\Post;
use App\Models\Topic;
use App\Support\ExtensionManager;

/**
 * Keeps the Typesense collection in step with topics when the convoro-typesense
 * extension is enabled. A topic's document is its title plus the stripped text
 * of its posts (capped), so search matches replies too — mirroring what the
 * database driver covers. Everything is a no-op (and never throws) when the
 * extension is disabled or the engine is unreachable.
 */
class TypesenseSync
{
    private const MAX_POSTS = 500;
    private const MAX_BODY = 100000;

    public static function active(): bool
    {
        return ExtensionManager::isEnabled('convoro-typesense');
    }

    public static function index(Topic $topic): void
    {
        if (! self::active()) {
            return;
        }
        $driver = TypesenseSearchDriver::fromExtension();
        if (! $driver->available()) {
            return;
        }

        $body = Post::where('topic_id', $topic->id)
            ->orderBy('id')
            ->limit(self::MAX_POSTS)
            ->pluck('body_html')
            ->map(fn ($h) => trim(strip_tags((string) $h)))
            ->filter()
            ->implode("\n");

        $driver->indexTopic((int) $topic->id, (string) $topic->title, mb_substr($body, 0, self::MAX_BODY));
    }

    public static function remove(int $topicId): void
    {
        if (! self::active()) {
            return;
        }
        TypesenseSearchDriver::fromExtension()->deleteTopic($topicId);
    }

    /**
     * Full rebuild — creates the collection and (re)indexes every topic.
     * Returns the number indexed. Throws if the engine isn't reachable.
     */
    public static function reindexAll(?callable $progress = null): int
    {
        $driver = TypesenseSearchDriver::fromExtension();
        if (! $driver->available()) {
            throw new \RuntimeException('Typesense is not reachable — check the URL and API key on the extension settings page.');
        }
        $driver->ensureCollection();

        $n = 0;
        Topic::query()->orderBy('id')->chunk(200, function ($chunk) use ($driver, &$n, $progress) {
            foreach ($chunk as $topic) {
                $body = Post::where('topic_id', $topic->id)
                    ->orderBy('id')
                    ->limit(self::MAX_POSTS)
                    ->pluck('body_html')
                    ->map(fn ($h) => trim(strip_tags((string) $h)))
                    ->filter()
                    ->implode("\n");
                $driver->indexTopic((int) $topic->id, (string) $topic->title, mb_substr($body, 0, self::MAX_BODY));
                $n++;
                if ($progress) {
                    $progress($n);
                }
            }
        });

        return $n;
    }
}
