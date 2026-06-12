<?php

return [
    // Current installed version of the software.
    'version' => '1.10.0',

    // Optional URL returning JSON {"version": "x.y.z", "url": "...", "notes": "..."}
    // used by the admin "check for updates" feature. Null = update checks disabled.
    'update_url' => env('CONVORO_UPDATE_URL'),

    // One-click public demo: /demo logs in this account (read it from env so it
    // can be disabled by leaving it blank). Pair with MAIL_MAILER=log on the demo.
    'demo_email' => env('CONVORO_DEMO_EMAIL', ''),

    // The apex marketing/store domain. Marketing + store routes are scoped to
    // this host; the forum lives on its own (sub)domain. Shared login works
    // across both when SESSION_DOMAIN is set to the parent (e.g. .convoro.co).
    'marketing_domain' => env('CONVORO_MARKETING_DOMAIN', 'convoro.co'),

    // The community/forum host. The public extension directory is mirrored here
    // too, so members can browse + buy without leaving the community.
    'community_domain' => env('CONVORO_COMMUNITY_DOMAIN', 'community.convoro.co'),

    // The central store this install validates premium license keys against.
    'store_url' => env('CONVORO_STORE_URL', 'https://convoro.co'),

    // Is THIS install the central Convoro storefront (sells extensions, runs
    // Stripe/licenses)? Off by default so ordinary installs don't ship the
    // seller admin — they only consume the catalog via the Marketplace.
    'store_owner' => env('CONVORO_STORE_OWNER', false),

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
