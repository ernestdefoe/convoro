<?php

namespace Convoro\Ext\Announcement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Sample first-party extension. Registers one read-only forum API endpoint that
 * returns the current active announcement. Demonstrates that an enabled
 * extension's ServiceProvider boots in the normal lifecycle with full access to
 * the framework — routes, DB, container — all without Composer.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/api/ext/announcement', function () {
            $row = DB::table('ext_announcements')
                ->where('active', true)
                ->orderByDesc('id')
                ->first();

            return response()->json($row ? ['body' => $row->body] : ['body' => null]);
        });
    }
}
