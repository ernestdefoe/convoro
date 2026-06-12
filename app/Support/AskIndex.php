<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Semantic index powering "Ask Convoro". Embeds posts with Voyage AI and stores
 * the vectors in `content_embeddings`; at query time it embeds the question and
 * ranks posts by cosine similarity. Falls back to full-text when not configured.
 */
class AskIndex
{
    private const ENDPOINT = 'https://api.voyageai.com/v1/embeddings';

    /** Embeddings provider is configured (a Voyage key is set). */
    public static function configured(): bool
    {
        return trim((string) Settings::get('ai.embed_key', '')) !== '';
    }

    /** Configured AND the index has content → semantic search is available. */
    public static function ready(): bool
    {
        return self::configured() && self::count() > 0;
    }

    public static function count(): int
    {
        try {
            return (int) DB::table('content_embeddings')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function model(): string
    {
        return trim((string) Settings::get('ai.embed_model', '')) ?: 'voyage-3.5-lite';
    }

    /**
     * Embed a batch of texts via Voyage.
     *
     * @param  string[]  $texts
     * @return array<int, array<int, float>> one vector per input (same order)
     *
     * @throws \RuntimeException
     */
    public static function embed(array $texts, string $inputType = 'document'): array
    {
        $key = trim((string) Settings::get('ai.embed_key', ''));
        if ($key === '') {
            throw new \RuntimeException('No embeddings API key configured.');
        }
        $res = Http::timeout(60)->withToken($key)->post(self::ENDPOINT, [
            'input' => array_values($texts),
            'model' => self::model(),
            'input_type' => $inputType,
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('Embeddings error: '.($res->json('detail') ?? $res->json('error.message') ?? $res->status()));
        }
        $rows = $res->json('data') ?? [];
        // Keep provider order.
        usort($rows, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($r) => $r['embedding'] ?? [], $rows);
    }

    /**
     * (Re)build the whole index. $progress($message, $percent, $count).
     */
    public static function rebuild(callable $progress): int
    {
        @set_time_limit(0);
        $progress('Starting…', 0, 0);
        $total = (int) DB::table('posts')->count();
        if ($total === 0) {
            $progress('No posts to index.', 100, 0);

            return 0;
        }

        $done = 0;
        DB::table('posts')
            ->join('topics', 'topics.id', '=', 'posts.topic_id')
            ->orderBy('posts.id')
            ->select('posts.id as post_id', 'posts.topic_id', 'posts.body_html', 'topics.title', 'topics.slug')
            ->chunk(64, function ($rows) use (&$done, $total, $progress) {
                $texts = [];
                $meta = [];
                foreach ($rows as $r) {
                    $snippet = self::plain($r->body_html);
                    if ($snippet === '') {
                        continue;
                    }
                    $texts[] = mb_substr($r->title.".\n\n".$snippet, 0, 8000);
                    $meta[] = $r;
                }
                if ($texts) {
                    $vectors = self::embed($texts, 'document');
                    foreach ($meta as $i => $r) {
                        $vec = $vectors[$i] ?? null;
                        if (! $vec) {
                            continue;
                        }
                        DB::table('content_embeddings')->updateOrInsert(
                            ['post_id' => $r->post_id],
                            [
                                'topic_id' => $r->topic_id,
                                'title' => mb_substr($r->title, 0, 250),
                                'slug' => $r->slug,
                                'snippet' => mb_substr(self::plain($r->body_html), 0, 600),
                                'dims' => count($vec),
                                'vector' => json_encode($vec),
                                'updated_at' => now(),
                                'created_at' => now(),
                            ],
                        );
                    }
                }
                $done += count($rows);
                $progress("Indexed {$done} of {$total} posts…", (int) min(99, round($done / $total * 100)), $done);
            });

        // Drop embeddings for posts that no longer exist.
        DB::table('content_embeddings')
            ->whereNotIn('post_id', fn ($q) => $q->select('id')->from('posts'))
            ->delete();

        $count = self::count();
        $progress('Index complete.', 100, $count);

        return $count;
    }

    /** Index (or re-index) a single post. Best-effort. */
    public static function indexPost(int $postId): void
    {
        if (! self::configured()) {
            return;
        }
        try {
            $r = DB::table('posts')->join('topics', 'topics.id', '=', 'posts.topic_id')
                ->where('posts.id', $postId)
                ->select('posts.id as post_id', 'posts.topic_id', 'posts.body_html', 'topics.title', 'topics.slug')
                ->first();
            if (! $r) {
                return;
            }
            $snippet = self::plain($r->body_html);
            if ($snippet === '') {
                return;
            }
            $vec = self::embed([mb_substr($r->title.".\n\n".$snippet, 0, 8000)], 'document')[0] ?? null;
            if (! $vec) {
                return;
            }
            DB::table('content_embeddings')->updateOrInsert(
                ['post_id' => $r->post_id],
                [
                    'topic_id' => $r->topic_id, 'title' => mb_substr($r->title, 0, 250), 'slug' => $r->slug,
                    'snippet' => mb_substr($snippet, 0, 600), 'dims' => count($vec),
                    'vector' => json_encode($vec), 'updated_at' => now(), 'created_at' => now(),
                ],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function removePost(int $postId): void
    {
        try {
            DB::table('content_embeddings')->where('post_id', $postId)->delete();
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Semantic search: top-K distinct topics for a question.
     *
     * @return array<int, array{title:string, url:string, snippet:string}>
     */
    public static function search(string $question, int $k = 6): array
    {
        $qv = self::embed([$question], 'query')[0] ?? null;
        if (! $qv) {
            return [];
        }
        $qNorm = self::norm($qv);
        if ($qNorm == 0.0) {
            return [];
        }

        // Load candidate vectors (cap for memory; newest first).
        $cap = (int) (Settings::get('ai.embed_cap', 8000) ?: 8000);
        $rows = DB::table('content_embeddings')
            ->orderByDesc('id')->limit($cap)
            ->get(['topic_id', 'title', 'slug', 'snippet', 'vector']);

        $scored = [];
        foreach ($rows as $r) {
            $v = json_decode($r->vector, true);
            if (! is_array($v) || ! $v) {
                continue;
            }
            $score = self::dot($qv, $v) / ($qNorm * (self::norm($v) ?: 1));
            $scored[] = ['score' => $score, 'topic_id' => $r->topic_id, 'title' => $r->title, 'slug' => $r->slug, 'snippet' => $r->snippet];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $seen = [];
        $out = [];
        foreach ($scored as $s) {
            if ($s['score'] < 0.3 || isset($seen[$s['topic_id']])) {
                continue;
            }
            $seen[$s['topic_id']] = true;
            $out[] = ['title' => $s['title'], 'url' => '/t/'.$s['slug'], 'snippet' => $s['snippet']];
            if (count($out) >= $k) {
                break;
            }
        }

        return $out;
    }

    /**
     * Ranked, de-duplicated topic ids for a query (semantic site search).
     *
     * @return int[]
     */
    public static function topicIds(string $question, int $k = 30): array
    {
        $qv = self::embed([$question], 'query')[0] ?? null;
        if (! $qv) {
            return [];
        }
        $qNorm = self::norm($qv);
        if ($qNorm == 0.0) {
            return [];
        }
        $cap = (int) (Settings::get('ai.embed_cap', 8000) ?: 8000);
        $rows = DB::table('content_embeddings')->orderByDesc('id')->limit($cap)
            ->get(['topic_id', 'vector']);

        $best = [];
        foreach ($rows as $r) {
            $v = json_decode($r->vector, true);
            if (! is_array($v) || ! $v) {
                continue;
            }
            $score = self::dot($qv, $v) / ($qNorm * (self::norm($v) ?: 1));
            if (! isset($best[$r->topic_id]) || $score > $best[$r->topic_id]) {
                $best[$r->topic_id] = $score;
            }
        }
        arsort($best);

        return array_slice(array_keys(array_filter($best, fn ($s) => $s >= 0.25)), 0, $k);
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    }

    private static function dot(array $a, array $b): float
    {
        $sum = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $sum += $a[$i] * $b[$i];
        }

        return $sum;
    }

    private static function norm(array $a): float
    {
        $sum = 0.0;
        foreach ($a as $x) {
            $sum += $x * $x;
        }

        return sqrt($sum);
    }
}
