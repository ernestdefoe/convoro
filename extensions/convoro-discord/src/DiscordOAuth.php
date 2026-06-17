<?php

namespace Convoro\Ext\Discord;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;

/**
 * Discord OAuth2 (authorization-code) — used for "Sign in / Connect with
 * Discord". We request only `identify email`; the member's roles are read via
 * the bot (see DiscordApi), not the user token, so no token is persisted.
 */
class DiscordOAuth
{
    public const SCOPES = 'identify email';

    public static function configured(): bool
    {
        return Settings::get('discord.client_id', '') !== '' && Settings::get('discord.client_secret', '') !== '';
    }

    public static function authorizeUrl(string $redirect, string $state): string
    {
        return 'https://discord.com/api/oauth2/authorize?'.http_build_query([
            'client_id' => (string) Settings::get('discord.client_id', ''),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'consent',
        ]);
    }

    /**
     * Exchange the code for an access token, then fetch the Discord profile.
     *
     * @return array{id:string,username:string,email:?string,avatar:?string}
     *
     * @throws \RuntimeException
     */
    public static function exchangeAndFetch(string $code, string $redirect): array
    {
        $tok = Http::asForm()->acceptJson()->timeout(20)->post('https://discord.com/api/oauth2/token', [
            'client_id' => (string) Settings::get('discord.client_id', ''),
            'client_secret' => (string) Settings::get('discord.client_secret', ''),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect,
        ]);
        $access = (string) $tok->json('access_token', '');
        if (! $tok->successful() || $access === '') {
            throw new \RuntimeException('Discord token exchange failed: '.($tok->json('error_description') ?? $tok->status()));
        }

        $me = Http::withToken($access)->acceptJson()->timeout(20)->get('https://discord.com/api/users/@me');
        if (! $me->successful() || ! $me->json('id')) {
            throw new \RuntimeException('Could not read your Discord profile.');
        }

        $avatarHash = (string) $me->json('avatar', '');
        $id = (string) $me->json('id');

        return [
            'id' => $id,
            'username' => (string) ($me->json('global_name') ?: $me->json('username') ?: ''),
            'email' => $me->json('email') ? strtolower(trim((string) $me->json('email'))) : null,
            'avatar' => $avatarHash !== '' ? "https://cdn.discordapp.com/avatars/{$id}/{$avatarHash}.png" : null,
        ];
    }
}
