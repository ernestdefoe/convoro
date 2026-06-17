<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Minimal GitHub OAuth (web application flow) so any seller can "Connect GitHub"
 * and list their own private repos in the extension directory. We request the
 * `repo` scope (read access to private repos) + `read:user`. The resulting access
 * token is stored encrypted on the user (see User::$casts) and used by the
 * registry when reading/downloading that seller's repos.
 *
 * Requires a GitHub OAuth App: set GITHUB_CLIENT_ID + GITHUB_CLIENT_SECRET (and,
 * if the callback isn't on the app's host, GITHUB_OAUTH_REDIRECT) in .env.
 */
class GitHubOAuth
{
    public const SCOPES = 'repo read:user';

    public static function configured(): bool
    {
        return (string) config('services.github.client_id') !== ''
            && (string) config('services.github.client_secret') !== '';
    }

    /** The GitHub consent URL to redirect the seller to. */
    public static function authorizeUrl(string $redirect, string $state): string
    {
        return 'https://github.com/login/oauth/authorize?'.http_build_query([
            'client_id' => (string) config('services.github.client_id'),
            'redirect_uri' => $redirect,
            'scope' => self::SCOPES,
            'state' => $state,
            'allow_signup' => 'false',
        ]);
    }

    /**
     * Exchange the OAuth `code` for an access token + the GitHub login.
     *
     * @return array{token:string,login:string}
     *
     * @throws \RuntimeException
     */
    public static function exchangeCode(string $code, string $redirect): array
    {
        $res = Http::asForm()->withHeaders(['Accept' => 'application/json'])
            ->post('https://github.com/login/oauth/access_token', [
                'client_id' => (string) config('services.github.client_id'),
                'client_secret' => (string) config('services.github.client_secret'),
                'code' => $code,
                'redirect_uri' => $redirect,
            ]);

        $token = (string) $res->json('access_token', '');
        if (! $res->successful() || $token === '') {
            throw new \RuntimeException('GitHub did not return an access token: '.($res->json('error_description') ?? $res->status()));
        }

        $user = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Convoro-Registry'])
            ->get('https://api.github.com/user');

        return ['token' => $token, 'login' => (string) $user->json('login', '')];
    }

    /** The redirect URI registered with the OAuth App (config override or route). */
    public static function redirectUri(): string
    {
        return (string) config('services.github.redirect') ?: route('store.connect.github.callback');
    }
}
