<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;

/**
 * Spam, flood & abuse gate for new topics and replies.
 *
 * One call — PostGuard::inspect() — runs every enabled control and returns a
 * decision the caller acts on:
 *   ['block' => bool, 'hold' => bool, 'message' => ?string, 'reason' => ?string]
 *
 *   block  → reject the post outright (HTTP 422 with the message).
 *   hold   → accept the post but hide it for moderator approval (the message is
 *            a friendly "awaiting approval" note).
 *   neither → let it through.
 *
 * Controls, in order: banned words, banned link domains, max links (content
 * rules — apply to everyone except admins), then slow mode (global floor +
 * per-category), hourly post/topic caps, duplicate-content blocking and
 * first-post approval (flood rules — also skipped for trusted members when
 * `spam.exempt_trusted` is on). No external services; all self-contained.
 */
class PostGuard
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('spam.enabled', true);
    }

    /**
     * @return array{block:bool, hold:bool, message:?string, reason:?string}
     */
    public static function inspect(User $user, string $html, string $type, ?Category $category = null): array
    {
        $pass = ['block' => false, 'hold' => false, 'message' => null, 'reason' => null];

        // Admins are never gated; the whole system can be switched off.
        if (! self::enabled() || $user->is_admin) {
            return $pass;
        }

        $s = self::settings();
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));

        // ---- Content rules (everyone but admins) ----------------------------
        if ($s['banned_words'] !== '' && self::matchesWords($text, $s['banned_words'])) {
            return self::act($s['content_action'], __('Your post was blocked by the spam filter.'), 'Banned word');
        }

        $links = self::links($html);
        if ($s['banned_domains'] !== '' && self::matchesDomains($links, $s['banned_domains'])) {
            return self::act($s['content_action'], __('Links to that site aren’t allowed here.'), 'Banned link domain');
        }
        if ($s['max_links'] > 0 && count($links) > $s['max_links']) {
            return self::act($s['content_action'], __('Your post has too many links.'), 'Too many links');
        }

        // ---- Flood rules (skipped for trusted members when configured) ------
        $level = (int) ($user->trust_level ?? 0);
        $trusted = $s['exempt_trusted'] && $level >= TrustLevels::MEMBER;
        if ($trusted) {
            return $pass;
        }

        // Slow mode: a minimum gap between any two posts. New (level 0) accounts
        // get the stricter of the two floors.
        $floor = $level === TrustLevels::NEW ? max($s['min_seconds'], $s['new_user_seconds']) : $s['min_seconds'];
        if ($floor > 0) {
            $since = self::secondsSinceLastPost($user);
            if ($since !== null && $since < $floor) {
                return self::block(__('You’re posting too fast — please wait :n seconds.', ['n' => $floor - $since]), 'Slow mode');
            }
        }

        // Per-category cooldown.
        if ($category && (int) $category->slow_mode > 0) {
            $since = self::secondsSinceLastPostInCategory($user, (int) $category->id);
            if ($since !== null && $since < (int) $category->slow_mode) {
                return self::block(__('Slow mode is on here — please wait :n seconds before posting again.', ['n' => (int) $category->slow_mode - $since]), 'Category slow mode');
            }
        }

        // Hourly caps.
        if ($s['max_posts_per_hour'] > 0 && self::countSince($user, 'posts') >= $s['max_posts_per_hour']) {
            return self::block(__('You’ve reached the hourly posting limit. Please try again later.'), 'Hourly post cap');
        }
        if ($type === 'topic' && $s['max_topics_per_hour'] > 0 && self::countSince($user, 'topics') >= $s['max_topics_per_hour']) {
            return self::block(__('You’ve started too many topics this hour. Please try again later.'), 'Hourly topic cap');
        }

        // Duplicate content.
        if ($s['duplicate_minutes'] > 0 && $text !== '' && self::isDuplicate($user, $text, $s['duplicate_minutes'])) {
            return self::block(__('You’ve already posted that.'), 'Duplicate content');
        }

        // First-post approval: hold a new member's first posts for review.
        if ($s['first_post_approval'] && self::approvedPostCount($user) < $s['first_post_count']) {
            return self::held('First-post approval');
        }

        return $pass;
    }

    /**
     * File a held post into the moderation queue so a human can approve or
     * remove it. Mirrors the AI copilot's report shape (App\Support\Moderation).
     */
    public static function report(Post $post, ?string $reason): void
    {
        $exists = \App\Models\Report::where('reportable_type', 'post')
            ->where('reportable_id', $post->id)
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return;
        }

        \App\Models\Report::create([
            'reportable_type' => 'post',
            'reportable_id' => $post->id,
            'reporter_id' => null,
            'reason' => __('Held for approval').($reason ? ' — '.$reason : ''),
            'source' => 'spam',
            'status' => 'open',
        ]);
    }

    // -- Settings -------------------------------------------------------------

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        $action = (string) Settings::get('spam.content_action', 'block');

        return [
            'exempt_trusted' => (bool) Settings::get('spam.exempt_trusted', true),
            'min_seconds' => max(0, (int) Settings::get('spam.min_seconds_between_posts', 15)),
            'new_user_seconds' => max(0, (int) Settings::get('spam.new_user_seconds_between_posts', 30)),
            'max_posts_per_hour' => max(0, (int) Settings::get('spam.max_posts_per_hour', 30)),
            'max_topics_per_hour' => max(0, (int) Settings::get('spam.max_topics_per_hour', 6)),
            'duplicate_minutes' => max(0, (int) Settings::get('spam.duplicate_minutes', 60)),
            'max_links' => max(0, (int) Settings::get('spam.max_links', 0)),
            'banned_words' => trim((string) Settings::get('spam.banned_words', '')),
            'banned_domains' => trim((string) Settings::get('spam.banned_domains', '')),
            'content_action' => $action === 'hold' ? 'hold' : 'block',
            'first_post_approval' => (bool) Settings::get('spam.first_post_approval', false),
            'first_post_count' => max(1, (int) Settings::get('spam.first_post_count', 1)),
        ];
    }

    // -- Decisions ------------------------------------------------------------

    private static function block(string $message, string $reason): array
    {
        return ['block' => true, 'hold' => false, 'message' => $message, 'reason' => $reason];
    }

    private static function held(string $reason): array
    {
        return ['block' => false, 'hold' => true, 'message' => __('Thanks! Your post is awaiting moderator approval.'), 'reason' => $reason];
    }

    /** Banned-word / domain / link hits respect the admin's chosen action. */
    private static function act(string $action, string $blockMessage, string $reason): array
    {
        return $action === 'hold' ? self::held($reason) : self::block($blockMessage, $reason);
    }

    // -- Lookups --------------------------------------------------------------

    /** Whole-topic posts include the opening post, so this covers topics too. */
    private static function secondsSinceLastPost(User $user): ?int
    {
        $last = Post::where('user_id', $user->id)->max('created_at');

        return $last ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last)) : null;
    }

    private static function secondsSinceLastPostInCategory(User $user, int $categoryId): ?int
    {
        $last = Post::where('posts.user_id', $user->id)
            ->join('topics', 'topics.id', '=', 'posts.topic_id')
            ->where('topics.category_id', $categoryId)
            ->max('posts.created_at');

        return $last ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last)) : null;
    }

    private static function countSince(User $user, string $table): int
    {
        $model = $table === 'topics' ? Topic::class : Post::class;

        return $model::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
    }

    private static function isDuplicate(User $user, string $text, int $minutes): bool
    {
        $needle = self::normalize($text);
        if ($needle === '') {
            return false;
        }

        $recent = Post::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->latest('id')->limit(25)->pluck('body_html');

        foreach ($recent as $html) {
            if (self::normalize(strip_tags((string) $html)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** A member's visible (approved) posts — held posts stay hidden until passed. */
    private static function approvedPostCount(User $user): int
    {
        return Post::where('user_id', $user->id)->where('hidden', false)->count();
    }

    // -- Content helpers ------------------------------------------------------

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $text)));
    }

    /** Hostnames of every <a href> in the post (lowercased, no leading www.). */
    private static function links(string $html): array
    {
        if (! preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            return [];
        }
        $hosts = [];
        foreach ($m[1] as $url) {
            $host = parse_url(trim($url), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = preg_replace('/^www\./i', '', mb_strtolower($host));
            }
        }

        return $hosts;
    }

    /** @return string[] non-empty, lowercased, de-duplicated terms */
    private static function terms(string $list): array
    {
        $parts = preg_split('/[\r\n,]+/', mb_strtolower($list)) ?: [];

        return array_values(array_unique(array_filter(array_map('trim', $parts), fn ($t) => $t !== '')));
    }

    private static function matchesWords(string $text, string $list): bool
    {
        $haystack = mb_strtolower($text);
        foreach (self::terms($list) as $term) {
            // Multi-word phrases match as substrings; single tokens match whole
            // words so "ass" doesn't trip on "class".
            if (str_contains($term, ' ')) {
                if (str_contains($haystack, $term)) {
                    return true;
                }
            } elseif (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u', $haystack)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesDomains(array $hosts, string $list): bool
    {
        $banned = array_map(fn ($d) => preg_replace('/^www\./i', '', $d), self::terms($list));
        foreach ($hosts as $host) {
            foreach ($banned as $domain) {
                if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                    return true;
                }
            }
        }

        return false;
    }
}
