<?php

namespace Convoro\Ext\Discord;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin Discord REST client authenticated as the bot. Used for role sync: read a
 * guild member's roles and add/remove roles. Requires a bot token, the guild
 * (server) id, and the bot to be in that guild with the "Manage Roles"
 * permission positioned ABOVE the roles it manages.
 */
class DiscordApi
{
    public static function configured(): bool
    {
        return self::botToken() !== '' && self::guildId() !== '';
    }

    public static function botToken(): string
    {
        return trim((string) Settings::get('discord.bot_token', ''));
    }

    public static function guildId(): string
    {
        return trim((string) Settings::get('discord.guild_id', ''));
    }

    private static function http(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bot '.self::botToken(),
            'User-Agent' => 'Convoro (https://convoro.co, 1.0)',
        ])->acceptJson()->timeout(20);
    }

    /**
     * The Discord role ids a member currently has, or null if the member isn't in
     * the guild / the lookup failed.
     *
     * @return string[]|null
     */
    public static function memberRoles(string $discordId): ?array
    {
        $guild = self::guildId();
        $res = self::http()->get("https://discord.com/api/guilds/{$guild}/members/{$discordId}");
        if (! $res->successful()) {
            return null;
        }

        return array_map('strval', (array) $res->json('roles', []));
    }

    /** Grant a role to a member. Returns true on success (204) or if already held. */
    public static function addRole(string $discordId, string $roleId): bool
    {
        $guild = self::guildId();

        return self::http()->put("https://discord.com/api/guilds/{$guild}/members/{$discordId}/roles/{$roleId}")->successful();
    }

    /** Remove a role from a member. */
    public static function removeRole(string $discordId, string $roleId): bool
    {
        $guild = self::guildId();

        return self::http()->delete("https://discord.com/api/guilds/{$guild}/members/{$discordId}/roles/{$roleId}")->successful();
    }
}
