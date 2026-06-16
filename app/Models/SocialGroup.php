<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member-created social group. Its discussions are core Topics carrying this
 * group's id (hidden from the main forum by SocialGroupTopicScope), so group
 * threads reuse the real composer, reactions, polls and moderation.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property bool $is_private
 * @property string $membership_type
 */
class SocialGroup extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_private' => 'boolean',
        'is_featured' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    public const ROLES = ['owner', 'moderator', 'member'];
    public const MEMBERSHIP_TYPES = ['open', 'approval', 'invite'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SocialGroupMember::class);
    }

    /** Members who can act — not banned. */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('banned_at');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(SocialGroupJoinRequest::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class, 'group_id');
    }

    // -- Membership helpers -------------------------------------------------

    public function memberFor(?User $user): ?SocialGroupMember
    {
        if (! $user) {
            return null;
        }

        return $this->members->firstWhere('user_id', $user->id)
            ?? $this->members()->where('user_id', $user->id)->first();
    }

    public function roleOf(?User $user): ?string
    {
        $m = $this->memberFor($user);

        return ($m && $m->banned_at === null) ? $m->role : null;
    }

    public function isActiveMember(?User $user): bool
    {
        return $this->roleOf($user) !== null;
    }

    public function isBanned(?User $user): bool
    {
        $m = $this->memberFor($user);

        return $m !== null && $m->banned_at !== null;
    }

    // -- Authorization ------------------------------------------------------

    /** Can the actor see this group and its content at all? */
    public function isVisibleTo(?User $user): bool
    {
        if ($user && ($user->is_admin || $user->hasPermission('group.moderate'))) {
            return true;
        }
        if (! $this->is_private) {
            return true;
        }
        if (! $user) {
            return false;
        }

        return (int) $user->id === (int) $this->user_id || $this->isActiveMember($user);
    }

    /** Can the actor post discussions/replies in this group? */
    public function canPost(?User $user): bool
    {
        if (! $user || $this->isBanned($user)) {
            return false;
        }
        if ($user->is_admin || $user->hasPermission('group.moderate')) {
            return true;
        }

        return $this->isActiveMember($user);
    }

    /** Can the actor moderate (pin/lock/delete content, manage members)? */
    public function canModerate(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->is_admin || $user->hasPermission('group.moderate')) {
            return true;
        }

        return in_array($this->roleOf($user), ['owner', 'moderator'], true);
    }

    /** Can the actor edit the group itself / delete it / manage roles? */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->is_admin || $user->hasPermission('group.moderate')) {
            return true;
        }

        return (int) $user->id === (int) $this->user_id;
    }
}
