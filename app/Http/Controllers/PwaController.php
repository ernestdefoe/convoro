<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    /** The web app manifest (installability). */
    public function manifest(): JsonResponse
    {
        $name = (string) config('app.name', 'Convoro');

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'description' => $name.' community',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#f3f4f9',
            'theme_color' => '#5b5bd6',
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
