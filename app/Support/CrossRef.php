<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\TopicReferencedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cross-references: when a post links to another topic — by pasting a /t/{slug}
 * link OR by typing the GitHub-style #123 shorthand — record it so the target
 * shows a "mentioned in" back-link, render #123 inline as a live-title chip,
 * and notify the referenced topic's author.
 */
class CrossRef
{
    /** Rebuild the references for one post from its current body. */
    public static function sync(Post $post): void
    {
        try {
            $targetIds = self::targetTopicIds((string) $post->body_html, (int) $post->topic_id);

            $existing = DB::table('topic_references')->where('source_post_id', $post->id)
                ->pluck('target_topic_id')->map(fn ($v) => (int) $v)->all();

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

            // Notify the authors of newly-referenced topics (not on re-edits).
            $newIds = array_values(array_diff($targetIds, $existing));
            if ($newIds) {
                self::notifyAuthors($post, $newIds);
            }
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

    /**
     * Render bare #123 / #123/pN tokens in post HTML as live chips showing the
     * target topic's CURRENT title (so renames flow through). Unknown ids render
     * muted. Called from the PostRender pipeline.
     */
    public static function chips(string $html): string
    {
        if ($html === '' || ! str_contains($html, '#')) {
            return $html;
        }
        // Protect existing links + code so we never rewrite inside them.
        $stash = [];
        $html = preg_replace_callback('#<a\b[^>]*>.*?</a>|<code\b[^>]*>.*?</code>|<pre\b[^>]*>.*?</pre>#is', function ($m) use (&$stash) {
            $stash[] = $m[0];

            return "\x00".(count($stash) - 1)."\x00";
        }, $html);

        if (preg_match_all('/(?<![\w#])#(\d+)/', $html, $idm)) {
            $ids = array_values(array_unique(array_map('intval', $idm[1])));
            $topics = Topic::whereIn('id', $ids)->get(['id', 'slug', 'title'])->keyBy('id');

            $html = preg_replace_callback('/(?<![\w#])#(\d+)(\/p\d+)?/', function ($m) use ($topics) {
                $id = (int) $m[1];
                $t = $topics->get($id);
                if (! $t) {
                    return '<span class="xref-chip xref-chip--missing">#'.$id.'</span>';
                }

                return '<a class="xref-chip" href="/t/'.htmlspecialchars($t->slug, ENT_QUOTES).'">'
                    .'<span class="xref-chip-hash">#'.$id.'</span> '
                    .htmlspecialchars(Str::limit(trim((string) $t->title), 60), ENT_QUOTES).'</a>';
            }, $html);
        }

        return preg_replace_callback('/\x00(\d+)\x00/', fn ($m) => $stash[(int) $m[1]] ?? '', $html);
    }

    private static function notifyAuthors(Post $post, array $targetTopicIds): void
    {
        $targets = Topic::whereIn('id', $targetTopicIds)->get(['id', 'user_id']);
        $source = Topic::find($post->topic_id);
        $actorName = $post->user->name ?? 'Someone';
        foreach ($targets as $t) {
            if ((int) $t->user_id === (int) $post->user_id) {
                continue;
            }
            $author = User::find($t->user_id);
            if (! $author) {
                continue;
            }
            Notifier::send($author, new TopicReferencedNotification(
                actor: Present::avatar($post->user),
                text: $actorName.' referenced your topic in “'.($source->title ?? 'a topic').'”',
                url: '/t/'.($source->slug ?? '').'#post-'.$post->id,
                excerpt: Present::excerpt($post->body_html, 100),
            ));
        }
    }

    /** Extract distinct target topic ids referenced in the HTML (excluding the source). */
    private static function targetTopicIds(string $html, int $sourceTopicId): array
    {
        if ($html === '') {
            return [];
        }
        $ids = [];

        // Pasted /t/{slug} links.
        if (preg_match_all('#/t/([a-z0-9][a-z0-9\-]*)#i', $html, $m)) {
            $slugs = array_unique($m[1]);
            $ids = array_merge($ids, Topic::whereIn('slug', $slugs)->pluck('id')->all());
        }

        // #123 / #123/pN shorthand in the visible text.
        $text = strip_tags($html);
        if (preg_match_all('/(?<![\w#])#(\d+)/', $text, $m2)) {
            $cand = array_unique(array_map('intval', $m2[1]));
            $ids = array_merge($ids, Topic::whereIn('id', $cand)->pluck('id')->all());
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn ($id) => $id !== $sourceTopicId,
        )));
    }
}
