<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The AI's authoritative knowledge: the built-in documentation and changelog
 * (and, in a later phase, admin-curated KB articles). These are embedded into
 * `knowledge_sources` so "Ask" can ground support answers in real software
 * facts and cite the doc — instead of paraphrasing whatever members wrote.
 *
 * Reuses AskIndex's Voyage embedding + cosine math; kept separate from the post
 * index so the two corpora can be ranked and weighted independently.
 */
class KnowledgeBase
{
    /** Master switch — let "Ask" consult the knowledge base at all. */
    public static function enabled(): bool
    {
        return (bool) Settings::get('ask.knowledge', true);
    }

    public static function count(): int
    {
        try {
            return (int) DB::table('knowledge_sources')->whereNotNull('vector')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Configured (Voyage key) AND something embedded → semantic grounding works. */
    public static function ready(): bool
    {
        return self::enabled() && AskIndex::configured() && self::count() > 0;
    }

    /**
     * Every authoritative source as a flat list of documents to embed.
     *
     * @return array<int, array{kind:string, ref:string, title:string, url:string, text:string}>
     */
    public static function sources(): array
    {
        $out = [];

        // Documentation — one document per guide (title + intro + sections).
        foreach ((array) config('docs', []) as $slug => $g) {
            if (! is_array($g)) {
                continue;
            }
            $parts = [(string) ($g['title'] ?? $slug), (string) ($g['intro'] ?? '')];
            foreach ((array) ($g['sections'] ?? []) as $sec) {
                $parts[] = (string) ($sec['h'] ?? '');
                foreach ((array) ($sec['body'] ?? []) as $b) {
                    if (is_string($b)) {
                        $parts[] = $b;
                    } elseif (is_array($b) && isset($b['code'])) {
                        $parts[] = (string) $b['code'];
                    }
                }
            }
            $text = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", array_filter($parts))) ?? '');
            if ($text === '') {
                continue;
            }
            $out[] = [
                'kind' => 'doc',
                'ref' => (string) $slug,
                'title' => 'Docs: '.(string) ($g['title'] ?? $slug),
                'url' => '/docs/'.$slug,
                'text' => $text,
            ];
        }

        // Changelog — one document per release entry (what changed and why).
        foreach ((array) config('trust.changelog', []) as $entry) {
            if (! is_array($entry) || empty($entry['tag'])) {
                continue;
            }
            $items = array_map(fn ($i) => (string) ($i['text'] ?? ''), (array) ($entry['items'] ?? []));
            $text = trim(($entry['title'] ?? '')."\n".implode("\n", array_filter($items)));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'kind' => 'changelog',
                'ref' => (string) $entry['tag'],
                'title' => 'Changelog '.$entry['tag'].(isset($entry['title']) ? ' — '.$entry['title'] : ''),
                'url' => '/changelog',
                'text' => "Convoro {$entry['tag']}: {$text}",
            ];
        }

        // Curated KB articles (Phase 2) live in the same table with kind=kb and a
        // vector already set; they're re-embedded on save, not rebuilt from here.

        return $out;
    }

    /**
     * (Re)build the doc + changelog embeddings. $progress($message, $percent).
     */
    public static function reindex(?callable $progress = null): int
    {
        $progress ??= fn () => null;
        if (! AskIndex::configured()) {
            $progress('No embeddings key configured.', 100);

            return 0;
        }

        $sources = self::sources();
        $total = count($sources);
        if ($total === 0) {
            $progress('Nothing to index.', 100);

            return 0;
        }

        $done = 0;
        foreach (array_chunk($sources, 32) as $batch) {
            $vectors = AskIndex::embed(array_map(fn ($s) => mb_substr($s['text'], 0, 8000), $batch), 'document');
            foreach ($batch as $i => $s) {
                $vec = $vectors[$i] ?? null;
                if (! $vec) {
                    continue;
                }
                DB::table('knowledge_sources')->updateOrInsert(
                    ['kind' => $s['kind'], 'ref' => $s['ref']],
                    [
                        'title' => mb_substr($s['title'], 0, 250),
                        'url' => $s['url'],
                        'snippet' => mb_substr($s['text'], 0, 1200),
                        'dims' => count($vec),
                        'vector' => json_encode($vec),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
            $done += count($batch);
            $progress("Indexed {$done} of {$total} sources…", (int) min(99, round($done / $total * 100)));
        }

        // Drop doc/changelog rows that no longer exist (keep curated kb rows).
        $keep = array_map(fn ($s) => $s['kind'].':'.$s['ref'], $sources);
        DB::table('knowledge_sources')->whereIn('kind', ['doc', 'changelog'])->get(['id', 'kind', 'ref'])
            ->each(function ($r) use ($keep) {
                if (! in_array($r->kind.':'.$r->ref, $keep, true)) {
                    DB::table('knowledge_sources')->where('id', $r->id)->delete();
                }
            });

        $progress('Knowledge index complete.', 100);

        return self::count();
    }

    // ---- Curated KB articles (Phase 2): admin-authored support knowledge ----

    /** @return array<int, array{id:int,title:string,url:?string,body:string,indexed:bool,updated:string}> */
    public static function articles(): array
    {
        return DB::table('knowledge_sources')->where('kind', 'kb')->orderByDesc('updated_at')
            ->get(['id', 'title', 'url', 'snippet', 'vector', 'updated_at'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'title' => (string) $r->title,
                'url' => $r->url,
                'body' => (string) $r->snippet,
                'indexed' => $r->vector !== null,
                'updated' => optional($r->updated_at)->diffForHumans() ?? '',
            ])->all();
    }

    /** Create or update a curated KB article; embeds it on save. Returns its id. */
    public static function saveArticle(?int $id, string $title, string $body, ?string $url = null): int
    {
        $title = trim($title);
        $body = trim($body);
        $text = mb_substr($title."\n\n".$body, 0, 6000);

        $vec = null;
        if (AskIndex::configured()) {
            try {
                $vec = AskIndex::embed([$text], 'document')[0] ?? null;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $data = [
            'kind' => 'kb',
            'title' => mb_substr($title, 0, 250),
            'url' => $url ?: null,
            'snippet' => mb_substr($body, 0, 6000),
            'dims' => $vec ? count($vec) : 0,
            'vector' => $vec ? json_encode($vec) : null,
            'updated_at' => now(),
        ];

        if ($id && DB::table('knowledge_sources')->where('id', $id)->where('kind', 'kb')->exists()) {
            DB::table('knowledge_sources')->where('id', $id)->update($data);

            return $id;
        }

        $data['ref'] = 'kb-'.bin2hex(random_bytes(8));
        $data['created_at'] = now();

        return (int) DB::table('knowledge_sources')->insertGetId($data);
    }

    public static function deleteArticle(int $id): void
    {
        DB::table('knowledge_sources')->where('id', $id)->where('kind', 'kb')->delete();
    }

    /**
     * Semantic search over the knowledge base.
     *
     * @return array<int, array{title:string, url:string, snippet:string, score:float, kind:string}>
     */
    public static function search(string $question, int $k = 4): array
    {
        if (! self::ready()) {
            return [];
        }
        $qv = AskIndex::embed([$question], 'query')[0] ?? null;
        if (! $qv) {
            return [];
        }
        $qNorm = self::norm($qv);
        if ($qNorm == 0.0) {
            return [];
        }

        $rows = DB::table('knowledge_sources')->whereNotNull('vector')->get(['kind', 'title', 'url', 'snippet', 'vector']);
        $scored = [];
        foreach ($rows as $r) {
            $v = json_decode($r->vector, true);
            if (! is_array($v) || ! $v) {
                continue;
            }
            $score = self::dot($qv, $v) / ($qNorm * (self::norm($v) ?: 1));
            $scored[] = ['score' => $score, 'kind' => $r->kind, 'title' => $r->title, 'url' => (string) $r->url, 'snippet' => (string) $r->snippet];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values(array_filter(array_slice($scored, 0, $k), fn ($s) => $s['score'] >= 0.35));
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
