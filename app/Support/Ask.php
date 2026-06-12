<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * "Ask Convoro" — the AI-native answer engine.
 *
 * Retrieves the most relevant community posts (MySQL full-text) and asks the
 * configured LLM to synthesize a concise answer that cites the real threads.
 * Turns the whole forum's history into an instantly-answerable knowledge base.
 */
class Ask
{
    public static function enabled(): bool
    {
        return Llm::configured() && (bool) Settings::get('ask.enabled', true);
    }

    /**
     * Starter questions for the Ask bar — the one-click "ask anything" moment for
     * new visitors. Uses recent topic titles, falling back to generic prompts.
     *
     * @return string[]
     */
    public static function suggestions(int $k = 4): array
    {
        if (! self::enabled()) {
            return [];
        }
        try {
            $titles = DB::table('topics')->orderByDesc('last_post_at')->limit($k)
                ->pluck('title')->map(fn ($t) => trim((string) $t))->filter()->values()->all();
        } catch (\Throwable) {
            $titles = [];
        }
        if (count($titles) >= 2) {
            return array_slice($titles, 0, $k);
        }

        $site = (string) (Settings::get('site.name') ?: config('app.name', 'this community'));

        return ["What is {$site} about?", 'How do I get started here?', 'What can I do on this site?'];
    }

    /**
     * @return array{answer:string, citations:array<int,array{n:int,title:string,url:string}>, error?:bool}
     */
    public static function answer(string $question): array
    {
        $question = trim($question);
        if (mb_strlen($question) < 3) {
            return ['answer' => 'Please ask a longer question.', 'citations' => []];
        }

        $sources = self::retrieve($question);

        $context = '';
        $citations = [];
        foreach ($sources as $i => $s) {
            $n = $i + 1;
            $context .= "[{$n}] \"{$s['title']}\": {$s['snippet']}\n\n";
            $citations[] = ['n' => $n, 'title' => $s['title'], 'url' => $s['url']];
        }

        $site = (string) (Settings::get('site.name') ?: config('app.name', 'this community'));
        $system = "You are the AI assistant for {$site}, a community forum. Answer the member's question "
            ."clearly and concisely (a few short paragraphs at most). Prefer the COMMUNITY CONTEXT below and cite it "
            .'inline like [1], [2] matching the numbered sources. If the context does not contain the answer, answer '
            .'from general knowledge and briefly say it is not based on community posts. Never invent citations or sources.';
        $user = ($context !== '' ? "COMMUNITY CONTEXT:\n{$context}" : "(No matching community posts were found.)\n\n")
            ."QUESTION: {$question}";

        try {
            $answer = trim(Llm::chat($system, $user, 700, 'ask'));
        } catch (\Throwable $e) {
            report($e);

            return ['answer' => 'The assistant is unavailable right now. Please try again in a moment.', 'citations' => [], 'error' => true];
        }

        // Only return citations the model actually referenced.
        $citations = array_values(array_filter($citations, fn ($c) => str_contains($answer, '['.$c['n'].']')));

        return ['answer' => $answer !== '' ? $answer : 'I could not find an answer to that.', 'citations' => $citations];
    }

    /**
     * Related existing threads for a draft question — retrieval only, no LLM
     * (cheap enough for type-ahead "asked before?"). Works on any install:
     * semantic when indexed, else full-text.
     *
     * @return array<int, array{title:string, url:string, snippet:string}>
     */
    public static function related(string $q, int $k = 5): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 4) {
            return [];
        }

        return array_slice(self::retrieve($q), 0, $k);
    }

    /** Top relevant threads for a question — semantic (Voyage) when indexed, else full-text. */
    private static function retrieve(string $q): array
    {
        if (AskIndex::ready()) {
            try {
                $hits = AskIndex::search($q, 6);
                if ($hits) {
                    return $hits;
                }
            } catch (\Throwable $e) {
                report($e); // fall through to full-text
            }
        }

        $rows = collect();
        try {
            $rows = DB::table('posts')
                ->join('topics', 'topics.id', '=', 'posts.topic_id')
                ->whereRaw('MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE)', [$q])
                ->selectRaw('topics.title as title, topics.slug as slug, posts.body_html as body, MATCH(posts.body_html) AGAINST(? IN NATURAL LANGUAGE MODE) as score', [$q])
                ->orderByDesc('score')
                ->limit(25)
                ->get();
        } catch (\Throwable) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $rows = DB::table('posts')
                ->join('topics', 'topics.id', '=', 'posts.topic_id')
                ->where('posts.body_html', 'like', $like)
                ->select('topics.title as title', 'topics.slug as slug', 'posts.body_html as body')
                ->limit(25)
                ->get();
        }

        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            if (isset($seen[$r->slug])) {
                continue;
            }
            $seen[$r->slug] = true;
            $out[] = ['title' => $r->title, 'url' => '/t/'.$r->slug, 'snippet' => self::snippet((string) $r->body)];
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    private static function snippet(string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return mb_strimwidth($text, 0, 320, '…');
    }
}
