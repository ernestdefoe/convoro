<?php

return [
    // Current installed version of the software.
    'version' => '1.43.8',

    // Disk that stores user-uploaded media (post images, avatars — the
    // unbounded, disk-hogging content). Defaults to the local 'public' disk;
    // set CONVORO_MEDIA_DISK=r2 (with the R2_* env, see config/filesystems.php)
    // to offload to Cloudflare R2 and reclaim server disk. ImagePipeline serves
    // via the disk's own url(), so URLs follow the switch automatically; only
    // NEW uploads move — existing files stay where they are. (Generated
    // marketplace covers stay local: they use a deliberately host-relative
    // /storage URL, so they're not routed here.)
    'media_disk' => env('CONVORO_MEDIA_DISK', 'public'),

    // Cache the Inertia SSR render of the static marketing pages (homepage,
    // changelog, roadmap, etc.). Self-invalidates on deploy + content change.
    'ssr_cache' => env('CONVORO_SSR_CACHE', true),

    // Optional URL returning JSON {"version": "x.y.z", "url": "...", "notes": "..."}
    // used by the admin "check for updates" feature. Null = update checks disabled.
    // NB: convoro.co is now the WordPress marketing site, so the update feed is
    // served from the flagship community forum instead (WP doesn't intercept it).
    'update_url' => env('CONVORO_UPDATE_URL', 'https://community.convoro.co/update-feed.json'),

    // One-click public demo: /demo logs in this account (read it from env so it
    // can be disabled by leaving it blank). Pair with MAIL_MAILER=array on the demo.
    'demo_email' => env('CONVORO_DEMO_EMAIL', ''),

    // Allow the one-click demo to sign in even when the demo account is an ADMIN,
    // so visitors can explore admin features (theme editor, settings). Default
    // OFF — only enable on a dedicated, disposable demo install, never on a real
    // community (it would make /demo a public admin login).
    'demo_admin' => env('CONVORO_DEMO_ADMIN', false),

    // The apex marketing/store domain. Marketing + store routes are scoped to
    // this host; the forum lives on its own (sub)domain. Shared login works
    // across both when SESSION_DOMAIN is set to the parent (e.g. .convoro.co).
    'marketing_domain' => env('CONVORO_MARKETING_DOMAIN', parse_url((string) env('APP_URL'), PHP_URL_HOST) ?: 'localhost'),

    // The community/forum host. The public extension directory is mirrored here
    // too, so members can browse + buy without leaving the community.
    'community_domain' => env('CONVORO_COMMUNITY_DOMAIN', parse_url((string) env('APP_URL'), PHP_URL_HOST) ?: 'localhost'),

    // The central store this install validates premium license keys against.
    'store_url' => env('CONVORO_STORE_URL', 'https://convoro.co'),

    // Is THIS install the central Convoro storefront (sells extensions, runs
    // Stripe/licenses)? Off by default so ordinary installs don't ship the
    // seller admin — they only consume the catalog via the Marketplace.
    'store_owner' => env('CONVORO_STORE_OWNER', false),

    // POC ONLY — the managed-hosting panel + tenant provisioning. Off in
    // production; enabled locally (CONVORO_HOSTING_PANEL=true) for testing.
    'hosting' => env('CONVORO_HOSTING_PANEL', false),

    // Managed-hosting plans. Members and bandwidth are UNLIMITED on every plan;
    // only storage differs (storage_gb). Prices are monthly, in cents.
    'plans' => [
        'starter' => ['name' => 'Starter', 'price_cents' => 1900, 'storage_gb' => 10, 'blurb' => 'For new and growing communities.'],
        'pro' => ['name' => 'Pro', 'price_cents' => 4900, 'storage_gb' => 50, 'blurb' => 'For established, active communities.'],
        'business' => ['name' => 'Business', 'price_cents' => 9900, 'storage_gb' => 200, 'blurb' => 'For large communities at scale.'],
    ],

    // POC — public "request a demo" flow: provisions an isolated, time-boxed
    // demo per requester and emails them the login. Off by default.
    'demo_requests' => env('CONVORO_DEMO_REQUESTS', false),

    // Where to send a heads-up whenever someone requests a demo (provisioned or
    // waitlisted). Blank = no admin notification.
    'demo_notify' => env('CONVORO_DEMO_NOTIFY', ''),

    // Path to the privileged helper that creates/removes a demo's nginx vhost +
    // per-demo Let's Encrypt cert (run via sudo). Blank = skip (local dev, where
    // demos are just sqlite files with no subdomain serving).
    'demo_site_hook' => env('CONVORO_DEMO_SITE_HOOK', ''),

    // Search backend. The default 'database' driver works on ANY host — it uses
    // MySQL's ngram full-text parser (multilingual, incl. CJK/Thai) with the
    // existing Voyage semantic re-rank layered on top when the Ask index is
    // warm, and degrades to LIKE on sqlite/pgsql. Set driver to 'typesense' to
    // point at a hosted Typesense (Typesense Cloud or a small VPS) over HTTP —
    // Typesense can't run ON shared hosting (it's a daemon), but Convoro can
    // talk to a remote one from any host. Falls back to 'database' automatically
    // when the configured engine isn't reachable.
    'search' => [
        'driver' => env('CONVORO_SEARCH_DRIVER', 'database'),
        'typesense' => [
            'url' => env('CONVORO_TYPESENSE_URL', ''),
            'api_key' => env('CONVORO_TYPESENSE_API_KEY', ''),
            'collection' => env('CONVORO_TYPESENSE_COLLECTION', 'convoro_topics'),
            // Seconds before giving up on the engine and falling back to the
            // database driver — keep low so a slow/unreachable engine never
            // stalls the search page.
            'timeout' => (int) env('CONVORO_TYPESENSE_TIMEOUT', 3),
        ],
    ],

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
