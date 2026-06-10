<?php

return [
    // Current installed version of the software.
    'version' => '0.1.0-dev',

    // Optional URL returning JSON {"version": "x.y.z", "url": "...", "notes": "..."}
    // used by the admin "check for updates" feature. Null = update checks disabled.
    'update_url' => env('CONVORO_UPDATE_URL'),
];
