<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight guest (logged-out visitor) presence, so "Online now" can show
 * visitors alongside members. Members use users.last_seen_at; guests don't have
 * a row, so we keep a small token→timestamp registry in the cache (portable
 * across file/redis drivers). The per-token write is throttled to once a minute.
 */
class Presence
{
    private const KEY = 'presence:guests';

    private const WINDOW = 300; // a guest is "online" for 5 minutes since last seen

    public static function pingGuest(string $token): void
    {
        $token = substr(preg_replace('/[^a-z0-9]/i', '', $token), 0, 32);
        if ($token === '' || ! Cache::add('gseen:'.$token, true, 60)) {
            return; // throttled: at most one registry write per guest per minute
        }

        $now = time();
        $cut = $now - self::WINDOW;
        $guests = Cache::get(self::KEY, []);
        $guests = is_array($guests) ? array_filter($guests, fn ($t) => (int) $t >= $cut) : [];
        $guests[$token] = $now;
        Cache::put(self::KEY, $guests, self::WINDOW + 120);
    }

    public static function guestCount(): int
    {
        $cut = time() - self::WINDOW;
        $guests = Cache::get(self::KEY, []);

        return is_array($guests) ? count(array_filter($guests, fn ($t) => (int) $t >= $cut)) : 0;
    }
}
