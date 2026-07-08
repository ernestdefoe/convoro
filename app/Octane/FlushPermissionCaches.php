<?php

namespace App\Octane;

use App\Support\CategoryVisibility;
use App\Support\Permissions;

/**
 * Octane safety net for the per-request static memos in the permission layer.
 *
 * Permissions::$restricted (the category-restriction set) and
 * CategoryVisibility::$memo (per-user hidden categories) are computed once and
 * cached in static properties for the life of the request. Under Octane those
 * statics OUTLIVE the request on a shared worker, so without this the next
 * request could read another user's memoized visibility or a stale restriction
 * set after an admin changed permissions. Flush at the start of every request so
 * each one recomputes from current state.
 */
class FlushPermissionCaches
{
    public function handle($event): void
    {
        Permissions::flush();
        CategoryVisibility::flush();
    }
}
