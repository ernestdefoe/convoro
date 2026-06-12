<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Report;
use Illuminate\Support\Facades\Log;

/**
 * AI moderation copilot. Triages new posts for spam / toxicity / personal data
 * and either flags them for review or holds (hides) them pending a human.
 */
class Moderation
{
    public static function enabled(): bool
    {
        return Llm::configured() && (bool) Settings::get('ai.moderation_enabled', false);
    }

    /** Score in [0,1] above which a post is flagged for review. */
    private static function reviewThreshold(): float
    {
        return (float) Settings::get('ai.moderation_review', 0.6);
    }

    /** Score in [0,1] above which a post is auto-held (hidden) for review. */
    private static function holdThreshold(): float
    {
        return (float) Settings::get('ai.moderation_hold', 0.85);
    }

    /**
     * Classify a piece of content. Returns:
     *   ['risk'=>float, 'labels'=>string[], 'reason'=>string, 'spam'=>float, 'toxicity'=>float, 'pii'=>bool]
     * or null if classification could not be performed.
     */
    public static function triage(string $html): ?array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if ($text === '' || ! Llm::configured()) {
            return null;
        }

        $system = 'You are a forum moderation classifier. Analyse the post and respond with ONLY a JSON object: '
            .'{"spam":0-1,"toxicity":0-1,"pii":true|false,"labels":["spam"|"toxicity"|"harassment"|"pii"|"scam"|"nsfw"...],'
            .'"reason":"one short sentence"}. Be conservative: ordinary disagreement or strong opinions are NOT toxic. '
            .'pii = the text exposes someone\'s private personal data (phone, address, etc).';

        try {
            $raw = Llm::chat($system, mb_substr($text, 0, 4000), 300, 'moderate');
        } catch (\Throwable $e) {
            Log::warning('Moderation triage failed: '.$e->getMessage());

            return null;
        }

        $data = self::json($raw);
        if (! is_array($data)) {
            return null;
        }

        $spam = (float) ($data['spam'] ?? 0);
        $tox = (float) ($data['toxicity'] ?? 0);
        $pii = (bool) ($data['pii'] ?? false);
        $risk = max($spam, $tox, $pii ? 0.7 : 0);

        return [
            'risk' => round($risk, 2),
            'spam' => round($spam, 2),
            'toxicity' => round($tox, 2),
            'pii' => $pii,
            'labels' => array_values(array_filter((array) ($data['labels'] ?? []), 'is_string')),
            'reason' => (string) ($data['reason'] ?? ''),
        ];
    }

    /** Triage a post and act: hold if very risky, flag if moderately risky. */
    public static function review(Post $post): void
    {
        if (! self::enabled() || $post->is_ai) {
            return;
        }
        $result = self::triage((string) $post->body_html);
        if (! $result) {
            return;
        }

        $post->moderation = $result;
        $hold = $result['risk'] >= self::holdThreshold();
        if ($hold) {
            $post->hidden = true;
        }
        $post->saveQuietly();

        if ($result['risk'] >= self::reviewThreshold()) {
            self::flag($post, $result, $hold);
        }
    }

    /** Create (or refresh) an AI moderation report for the mod queue. */
    private static function flag(Post $post, array $result, bool $held): void
    {
        $exists = Report::where('reportable_type', 'post')
            ->where('reportable_id', $post->id)
            ->where('source', 'ai')
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return;
        }

        $labels = implode(', ', $result['labels']) ?: 'flagged';
        Report::create([
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reporter_id' => null,
            'reason' => ($held ? 'Held — ' : 'Flagged — ').$labels.($result['reason'] ? ': '.$result['reason'] : ''),
            'source' => 'ai',
            'ai_meta' => $result,
            'status' => 'open',
        ]);
    }

    private static function json(string $raw): ?array
    {
        $raw = trim($raw);
        $s = strpos($raw, '{');
        $e = strrpos($raw, '}');
        if ($s === false || $e === false || $e <= $s) {
            return null;
        }

        return json_decode(substr($raw, $s, $e - $s + 1), true);
    }
}
