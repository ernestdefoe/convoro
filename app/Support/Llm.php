<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal multi-provider LLM client for core features (e.g. extension security
 * review). Reads the shared `ai.*` settings (provider/api_key/base_url/model)
 * — one key powers Ask, translation, moderation, the writing assistant, etc.
 *
 * Supports Anthropic Messages API and any OpenAI-compatible chat/completions
 * endpoint (OpenAI, OpenRouter, Together, local, …).
 */
class Llm
{
    public static function configured(): bool
    {
        return trim((string) Settings::get('ai.api_key', '')) !== '';
    }

    public static function model(): string
    {
        $m = trim((string) Settings::get('ai.model', ''));
        if ($m !== '') {
            return $m;
        }

        return (string) Settings::get('ai.provider', 'anthropic') === 'anthropic'
            ? 'claude-3-5-haiku-latest' : 'gpt-4o-mini';
    }

    /**
     * Send a single system+user turn and return the assistant text. Records
     * token usage + estimated cost (per $feature) and enforces the monthly
     * budget cap.
     *
     * @param  string  $feature  Label for the usage dashboard (ask|translate|moderate|assist|summary|digest|review|general)
     *
     * @throws \RuntimeException
     */
    public static function chat(string $system, string $user, int $maxTokens = 1500, string $feature = 'general'): string
    {
        $provider = (string) Settings::get('ai.provider', 'anthropic');
        $key = trim((string) Settings::get('ai.api_key', ''));
        if ($key === '') {
            throw new \RuntimeException('No AI API key configured.');
        }
        if (self::overBudget()) {
            throw new \RuntimeException('AI monthly budget reached.');
        }
        $model = self::model();

        if ($provider === 'anthropic') {
            $res = Http::timeout(120)
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);
            if (! $res->successful()) {
                throw new \RuntimeException('AI error: '.($res->json('error.message') ?? $res->status()));
            }
            self::record($feature, $model, (int) $res->json('usage.input_tokens', 0), (int) $res->json('usage.output_tokens', 0));

            return (string) ($res->json('content.0.text') ?? '');
        }

        // OpenAI-compatible
        $base = rtrim((string) (Settings::get('ai.base_url') ?: 'https://api.openai.com/v1'), '/');
        $res = Http::timeout(120)->withToken($key)->post($base.'/chat/completions', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('AI error: '.($res->json('error.message') ?? $res->status()));
        }
        self::record($feature, $model, (int) $res->json('usage.prompt_tokens', 0), (int) $res->json('usage.completion_tokens', 0));

        return (string) ($res->json('choices.0.message.content') ?? '');
    }

    /** Estimated cost of a call in cents, from the configured per-1M-token prices. */
    private static function costCents(int $in, int $out): int
    {
        $priceIn = (float) Settings::get('ai.price_in', 0);   // USD per 1M input tokens
        $priceOut = (float) Settings::get('ai.price_out', 0); // USD per 1M output tokens
        $usd = ($in / 1_000_000 * $priceIn) + ($out / 1_000_000 * $priceOut);

        return (int) round($usd * 100);
    }

    /** Log one call's token usage + cost for the dashboard. Never throws. */
    private static function record(string $feature, string $model, int $in, int $out): void
    {
        try {
            if (! Schema::hasTable('ai_usage')) {
                return;
            }
            DB::table('ai_usage')->insert([
                'feature' => substr($feature, 0, 32),
                'model' => $model,
                'prompt_tokens' => $in,
                'completion_tokens' => $out,
                'cost_cents' => self::costCents($in, $out),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Usage logging must never break an AI feature.
        }
    }

    /** Cents spent this calendar month. */
    public static function monthSpentCents(): int
    {
        if (! Schema::hasTable('ai_usage')) {
            return 0;
        }

        return (int) DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->sum('cost_cents');
    }

    /** True when a monthly budget cap is set and this month's spend has reached it. */
    public static function overBudget(): bool
    {
        $cap = (int) Settings::get('ai.monthly_budget_cents', 0);

        return $cap > 0 && self::monthSpentCents() >= $cap;
    }

    /** Usage summary for the admin dashboard. */
    public static function usageSummary(): array
    {
        $cap = (int) Settings::get('ai.monthly_budget_cents', 0);
        $spent = self::monthSpentCents();
        $hasTable = Schema::hasTable('ai_usage');

        $byFeature = ($hasTable && Schema::hasColumn('ai_usage', 'feature'))
            ? DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw('feature, COUNT(*) as calls, SUM(prompt_tokens+completion_tokens) as tokens, SUM(cost_cents) as cents')
                ->groupBy('feature')->orderByDesc('cents')->get()
                ->map(fn ($r) => [
                    'feature' => $r->feature,
                    'calls' => (int) $r->calls,
                    'tokens' => (int) $r->tokens,
                    'cents' => (int) $r->cents,
                ])->all()
            : [];

        return [
            'spentCents' => $spent,
            'budgetCents' => $cap,
            'overBudget' => $cap > 0 && $spent >= $cap,
            'calls' => $hasTable ? (int) DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->count() : 0,
            'tokens' => $hasTable ? (int) DB::table('ai_usage')->where('created_at', '>=', now()->startOfMonth())->sum(DB::raw('prompt_tokens + completion_tokens')) : 0,
            'byFeature' => $byFeature,
        ];
    }
}
