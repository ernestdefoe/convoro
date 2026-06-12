<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Cross-references: when a post links to another topic (/t/{slug}), record it
 * so the target topic can show "mentioned in" back-links.
 */
class CrossRef
{
    /** Rebuild the references for one post from its current body. */
    public static function sync(Post $post): void
    {
        try {
            $targetIds = self::targetTopicIds((string) $post->body_html, (int) $post->topic_id);

            DB::transaction(function () use ($post, $targetIds) {
                DB::table('topic_references')->where('source_post_id', $post->id)->delete();
                if (! $targetIds) {
                    return;
                }
                $now = now();
                DB::table('topic_references')->insert(array_map(fn ($tid) => [
                    'source_post_id' => $post->id,
                    'source_topic_id' => $post->topic_id,
                    'target_topic_id' => $tid,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $targetIds));
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function remove(Post $post): void
    {
        try {
            DB::table('topic_references')->where('source_post_id', $post->id)->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    /** Topics referenced as back-links into $topicId. @return array<int,array{title:string,slug:string}> */
    public static function into(int $topicId, int $limit = 20): array
    {
        try {
            $sourceTopicIds = DB::table('topic_references')
                ->where('target_topic_id', $topicId)
                ->distinct()->pluck('source_topic_id');
            if ($sourceTopicIds->isEmpty()) {
                return [];
            }

            return Topic::whereIn('id', $sourceTopicIds)->latest('last_post_at')->limit($limit)
                ->get(['title', 'slug'])
                ->map(fn ($t) => ['title' => $t->title, 'slug' => $t->slug])->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Extract distinct target topic ids from links in the HTML (excluding the source topic). */
    private static function targetTopicIds(string $html, int $sourceTopicId): array
    {
        if ($html === '' || ! preg_match_all('#/t/([a-z0-9][a-z0-9\-]*)#i', $html, $m)) {
            return [];
        }
        $slugs = array_unique($m[1]);
        if (! $slugs) {
            return [];
        }

        return Topic::whereIn('slug', $slugs)
            ->where('id', '!=', $sourceTopicId)
            ->pluck('id')->all();
    }
}
