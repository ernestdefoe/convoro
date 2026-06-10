<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{
    protected $guarded = ['id'];

    /** Quick check used by the BlockBannedIp middleware (cached). */
    public static function isBanned(string $ip): bool
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'ipban:'.$ip, 60,
            fn () => static::where('ip_address', $ip)->exists(),
        );
    }

    public static function flush(string $ip): void
    {
        \Illuminate\Support\Facades\Cache::forget('ipban:'.$ip);
    }
}
