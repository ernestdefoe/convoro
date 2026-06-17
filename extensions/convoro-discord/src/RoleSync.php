<?php

namespace Convoro\Ext\Discord;

use App\Models\User;

/**
 * Two-way role sync between Discord roles and Convoro groups, over the admin's
 * role map (each entry pairs a Convoro group id with a Discord role id).
 *
 * Order matters: we SEED from Discord first (grant Convoro groups for roles the
 * member holds), then RECONCILE Discord to match Convoro membership. So Convoro
 * is authoritative after a merge, while a role first granted in Discord still
 * flows in. Only mapped groups/roles are ever touched — unmapped ones are left
 * exactly as they are on both sides.
 */
class RoleSync
{
    /** Sync one linked user. Safe to call often; no-ops when unconfigured. */
    public static function sync(User $user): void
    {
        $discordId = (string) ($user->discord_id ?? '');
        if ($discordId === '' || ! DiscordApi::configured()) {
            return;
        }

        $map = Extension::roleMap(); // [ ['group' => int, 'role' => string], ... ]
        if (! $map) {
            return;
        }

        $discordRoles = DiscordApi::memberRoles($discordId);
        if ($discordRoles === null) {
            return; // member not in the guild (nothing we can read or write)
        }
        $discordRoles = array_flip($discordRoles);

        $groupIds = $user->groups()->pluck('groups.id')->map('intval')->all();
        $hasGroup = array_flip($groupIds);

        // 1) Discord → Convoro: grant mapped groups for roles the member holds.
        $attach = [];
        foreach ($map as $m) {
            if (isset($discordRoles[$m['role']]) && ! isset($hasGroup[$m['group']])) {
                $attach[] = $m['group'];
                $hasGroup[$m['group']] = true;
            }
        }
        if ($attach) {
            $user->groups()->syncWithoutDetaching($attach);
        }

        // 2) Convoro → Discord: make each mapped Discord role match group membership.
        foreach ($map as $m) {
            $shouldHave = isset($hasGroup[$m['group']]);
            $does = isset($discordRoles[$m['role']]);
            if ($shouldHave && ! $does) {
                DiscordApi::addRole($discordId, $m['role']);
            } elseif (! $shouldHave && $does) {
                DiscordApi::removeRole($discordId, $m['role']);
            }
        }

        $user->forceFill(['discord_synced_at' => now()])->save();
    }
}
