<?php

namespace Convoro\Ext\Trending;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/api/ext/trending', function () {
            $items = DB::table('topics')
                ->leftJoin('categories', 'categories.id', '=', 'topics.category_id')
                ->where('topics.last_post_at', '>=', now()->subDays(30))
                ->orderByDesc('topics.reply_count')
                ->orderByDesc('topics.last_post_at')
                ->limit(6)
                ->get(['topics.title', 'topics.slug', 'topics.reply_count', 'categories.name as cat', 'categories.color as color']);

            // Fall back to most recent if nothing in the last 30 days.
            if ($items->isEmpty()) {
                $items = DB::table('topics')
                    ->leftJoin('categories', 'categories.id', '=', 'topics.category_id')
                    ->orderByDesc('topics.last_post_at')->limit(6)
                    ->get(['topics.title', 'topics.slug', 'topics.reply_count', 'categories.name as cat', 'categories.color as color']);
            }

            return response()->json($items->map(fn ($t) => [
                'title' => $t->title,
                'url' => '/t/'.$t->slug,
                'replies' => (int) $t->reply_count,
                'cat' => $t->cat,
                'color' => $t->color,
            ]));
        });
    }
}
