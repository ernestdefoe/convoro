<?php

namespace Convoro\Ext\MembersOnline;

use App\Models\User;
use App\Support\Present;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/api/ext/members', function () {
            return response()->json([
                'total' => User::count(),
                'online' => User::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'newest' => User::latest('id')->limit(6)->get()->map(fn (User $u) => Present::avatar($u))->values(),
            ]);
        });
    }
}
