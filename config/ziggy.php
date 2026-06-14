<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Ziggy route allow-list
    |---------------------------------------------------------------------------
    |
    | The `@routes` Blade directive serialises named routes into EVERY page's
    | HTML for the frontend `route()` helper. Shipping all ~360 routes added
    | ~26 KB to every response. The frontend only calls route() on the Breeze
    | auth / profile / dashboard pages — the forum shell never does — so we ship
    | only those (wildcards allowed). Add a name here if a new route() call needs
    | it; an unknown route makes route() throw, which is easy to catch in dev.
    |
    */
    'only' => [
        'login',
        'logout',
        'register',
        'dashboard',
        'verification.send',
        'notifications.preferences',
        'password.*',
        'profile.*',
    ],
];
