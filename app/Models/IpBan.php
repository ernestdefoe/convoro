<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    protected $guarded = ['id'];

    /** Quick check used by the BlockBanned middleware (cached). */
    public static function isBanned(string $ip): bool
    {
        // Fail open: a database hiccup must never 500 (or lock out) every
        // request — an un-enforced ban for up to 60s is the safer failure.
        try {
            return \Illuminate\Support\Facades\Cache::remember(
                'ipban:'.$ip, 60,
                fn () => static::where('ip_address', $ip)->exists(),
            );
        } catch (\Throwable) {
            return false;
        }
    }

    public static function flush(string $ip): void
    {
        \Illuminate\Support\Facades\Cache::forget('ipban:'.$ip);
    }
}
