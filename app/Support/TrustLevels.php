<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\TrustLevelUpNotification;
use Illuminate\Support\Facades\DB;

/**
 * Trust levels — a lightweight reputation ladder (Discourse-inspired).
 *
 * We ship a 0/1/4 subset: New (0), Member (1) and Leader (4). New → Member is
 * earned automatically through genuine participation; Leader is granted by an
 * admin. New accounts have links and images stripped from their posts to blunt
 * drive-by spam. Levels are separate from groups (groups are assigned roles;
 * trust is earned standing).
 */
class TrustLevels
{
    public const NEW = 0;
    public const MEMBER = 1;
    public const LEADER = 4;

    /** @return array<int, string> level => label */
    public static function levels(): array
    {
        return [
            self::NEW => 'New',
            self::MEMBER => 'Member',
            self::LEADER => 'Leader',
        ];
    }

    public static function label(int $level): string
    {
        return self::levels()[$level] ?? ('Level '.$level);
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('trust.enabled', true);
    }

    /**
     * The level a user has EARNED purely from activity. Only ever returns New or
     * Member — Leader is admin-granted, never auto-awarded.
     */
    public static function earned(User $user): int
    {
        $minPosts = (int) Settings::get('trust.tl1_posts', 3);
        $minTopicsRead = (int) Settings::get('trust.tl1_topics_read', 5);
        $minAgeMinutes = (int) Settings::get('trust.tl1_min_age_minutes', 30);

        $ageMinutes = $user->created_at ? $user->created_at->diffInMinutes(now()) : 0;
        if ($ageMinutes < $minAgeMinutes) {
            return self::NEW;
        }
        if ($user->posts()->count() < $minPosts) {
            return self::NEW;
        }
        $topicsRead = DB::table('topic_reads')->where('user_id', $user->id)->count();
        if ($topicsRead < $minTopicsRead) {
            return self::NEW;
        }

        return self::MEMBER;
    }

    /**
     * Re-evaluate a user and auto-promote New → Member when they qualify. Never
     * auto-demotes, never touches admins, locked users, or anyone already at
     * Member or above (Leader is admin-only).
     */
    public static function evaluate(User $user): void
    {
        if (! self::enabled() || $user->is_admin || $user->trust_locked) {
            return;
        }
        if ((int) $user->trust_level >= self::MEMBER) {
            return;
        }
        if (self::earned($user) >= self::MEMBER) {
            self::set($user, self::MEMBER, notify: true);
        }
    }

    /** Set a user's trust level, optionally notifying them on a promotion. */
    public static function set(User $user, int $level, bool $notify = false, bool $lock = false): void
    {
        $old = (int) $user->trust_level;
        $user->forceFill([
            'trust_level' => $level,
            'trust_promoted_at' => now(),
        ]);
        if ($lock) {
            $user->forceFill(['trust_locked' => true]);
        }
        $user->save();

        if ($notify && $level > $old) {
            try {
                Notifier::send($user, new TrustLevelUpNotification($level));
            } catch (\Throwable $e) {
                // A notification hiccup must never break the action that triggered it.
            }
        }
    }

    /** Whether this user's posts should have links/images stripped (New accounts). */
    public static function gatesContent(?User $user): bool
    {
        return $user !== null
            && self::enabled()
            && (bool) Settings::get('trust.gate_new_users', true)
            && ! $user->is_admin
            && (int) $user->trust_level < self::MEMBER;
    }

    /** Remove images and unwrap links to plain text (for gated New accounts). */
    public static function stripLinksMedia(string $html): string
    {
        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $html) ?? $html;

        return $html;
    }
}
