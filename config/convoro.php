<?php

return [
    // Current installed version of the software.
    'version' => '0.1.0-dev',

    // Optional URL returning JSON {"version": "x.y.z", "url": "...", "notes": "..."}
    // used by the admin "check for updates" feature. Null = update checks disabled.
    'update_url' => env('CONVORO_UPDATE_URL'),

    // The apex marketing/store domain. Marketing + store routes are scoped to
    // this host; the forum lives on its own (sub)domain. Shared login works
    // across both when SESSION_DOMAIN is set to the parent (e.g. .convoro.co).
    'marketing_domain' => env('CONVORO_MARKETING_DOMAIN', 'convoro.co'),

    // Stripe (central store). Prefer admin Settings, fall back to env. Empty =
    // checkout disabled (store still browsable).
    'stripe' => [
        'key' => env('STRIPE_KEY', ''),
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    ],
];
