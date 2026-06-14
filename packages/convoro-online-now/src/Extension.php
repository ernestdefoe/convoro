<?php

namespace Convoro\Ext\OnlineNow;

use App\Models\User;
use App\Support\Present;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/api/ext/online', function () {
            $users = User::where('last_seen_at', '>=', now()->subMinutes(5))
                ->orderByDesc('last_seen_at')->limit(30)->get();

            return response()->json([
                'count' => $users->count(),
                'guests' => \App\Support\Presence::guestCount(),
                'users' => $users->map(fn (User $u) => Present::avatar($u))->values(),
            ]);
        });
    }
}
