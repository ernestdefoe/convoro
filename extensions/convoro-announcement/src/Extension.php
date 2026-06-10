<?php

namespace Convoro\Ext\Announcement;

use App\Support\ExtensionManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Sample first-party extension. Registers one read-only forum API endpoint that
 * returns the current announcement — driven by the extension's own declarative
 * settings (edited from the Marketplace card). Demonstrates that an enabled
 * extension's ServiceProvider boots in the normal lifecycle with full access to
 * the framework — routes, settings, container — all without Composer.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/api/ext/announcement', function () {
            $active = (bool) ExtensionManager::setting('convoro-announcement', 'active', true);
            $body = (string) ExtensionManager::setting('convoro-announcement', 'body', '');

            return response()->json(['body' => ($active && trim($body) !== '') ? $body : null]);
        });
    }
}
