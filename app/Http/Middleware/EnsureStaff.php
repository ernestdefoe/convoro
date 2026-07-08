<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the moderation panel: full admins, or any member holding at least one
 * moderation permission. Individual actions still assert their specific
 * permission in the controller, so a moderator only sees the panel — they can't
 * perform an action they weren't granted.
 */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && ($user->is_admin || $user->hasAnyPermission(Permissions::moderationKeys())), 403);

        return $next($request);
    }
}
