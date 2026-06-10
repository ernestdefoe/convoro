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

    // The central store this install validates premium license keys against.
    'store_url' => env('CONVORO_STORE_URL', 'https://convoro.co'),

    // Stripe (central store). Prefer admin Settings, fall back to env. Empty =
    // checkout disabled (store still browsable).
    //   - secret/key      = the PLATFORM account (also used for "Connect with
    //                       Stripe" OAuth token exchange).
    //   - connect_client_id = your Stripe Connect application id (ca_...) — set
    //                       this to enable the "Connect with Stripe" button.
    'stripe' => [
        'key' => env('STRIPE_KEY', ''),
        'secret' => env('STRIPE_SECRET', ''),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        'connect_client_id' => env('STRIPE_CONNECT_CLIENT_ID', ''),
        // Must EXACTLY match a redirect URI registered on your Stripe Connect
        // application. Defaults to the admin callback on the current host.
        'connect_redirect' => env('STRIPE_CONNECT_REDIRECT', ''),
    ],
];
