<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps an authenticated user's last_seen_at, throttled to once per minute via
 * the cache so it's cheap. Powers "online now" presence for the widget
 * extensions (online = last_seen within ~5 minutes).
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && Cache::add('seen:'.$user->id, true, 60)) {
            // updateQuietly to avoid touching updated_at / firing events.
            $user->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }
}
