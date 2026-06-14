<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | SSR pre-renders the Vue app to HTML via a small Node sidecar
    | (php artisan inertia:start-ssr, default :13714) so the first paint is
    | real content instead of an empty <div id="app">. If the sidecar is down
    | or a page errors during render, Inertia transparently falls back to
    | client-side rendering — the site keeps working either way.
    |
    */

    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [resource_path('js/Pages')],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],
];
