<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Generic OpenID Connect single sign-on — works with any compliant provider
 * (Okta, Auth0, Azure AD, Authentik, Keycloak, Google Workspace, …). The admin
 * supplies an issuer URL + client id/secret; we discover the endpoints from the
 * provider's /.well-known/openid-configuration.
 *
 * Confidential authorization-code flow: the code is exchanged at the token
 * endpoint server-to-server (authenticated with the client secret over TLS) and
 * the profile is read from the userinfo endpoint, so no local JWT verification
 * is required. CSRF is covered by the `state` parameter.
 */
class Oidc
{
    public static function enabled(): bool
    {
        return (bool) Settings::get('sso.enabled', false)
            && Settings::get('sso.issuer') && Settings::get('sso.client_id') && Settings::get('sso.client_secret');
    }

    /** Label for the sign-in button, e.g. "Sign in with Okta". */
    public static function label(): string
    {
        $name = trim((string) Settings::get('sso.label', '')) ?: 'SSO';

        return __('Sign in with :name', ['name' => $name]);
    }

    public static function scopes(): string
    {
        $s = trim((string) Settings::get('sso.scopes', ''));

        return $s !== '' ? $s : 'openid profile email';
    }

    /** Discover (and cache) the provider's endpoints. Throws on failure. */
    public static function discover(): array
    {
        $issuer = rtrim((string) Settings::get('sso.issuer'), '/');
        if ($issuer === '') {
            throw new \RuntimeException('No SSO issuer configured.');
        }

        return Cache::remember('sso.discovery:'.md5($issuer), 3600, function () use ($issuer) {
            $url = str_contains($issuer, '/.well-known/') ? $issuer : $issuer.'/.well-known/openid-configuration';
            $doc = Http::timeout(10)->acceptJson()->get($url)->throw()->json();
            foreach (['authorization_endpoint', 'token_endpoint'] as $req) {
                if (empty($doc[$req])) {
                    throw new \RuntimeException("The provider's OpenID configuration is missing {$req}.");
                }
            }

            return $doc;
        });
    }

    /** Build the authorization redirect URL. */
    public static function authUrl(string $state, string $nonce, string $redirectUri): string
    {
        $doc = self::discover();
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) Settings::get('sso.client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => self::scopes(),
            'state' => $state,
            'nonce' => $nonce,
        ]);

        return $doc['authorization_endpoint'].(str_contains($doc['authorization_endpoint'], '?') ? '&' : '?').$params;
    }

    /** Exchange the authorization code for tokens. */
    public static function exchange(string $code, string $redirectUri): array
    {
        $doc = self::discover();
        $res = Http::asForm()->timeout(15)->acceptJson()->post($doc['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => (string) Settings::get('sso.client_id'),
            'client_secret' => (string) Settings::get('sso.client_secret'),
        ]);
        if (! $res->ok() || ! empty($res->json('error'))) {
            throw new \RuntimeException('Token exchange failed: '.($res->json('error_description') ?? $res->json('error') ?? 'unknown error'));
        }

        return $res->json();
    }

    /**
     * Read the member's profile. Prefers the userinfo endpoint; falls back to
     * the (unverified) id_token claims when no userinfo endpoint is published.
     */
    public static function profile(array $tokens): array
    {
        $doc = self::discover();
        if (! empty($doc['userinfo_endpoint']) && ! empty($tokens['access_token'])) {
            $res = Http::withToken($tokens['access_token'])->timeout(10)->acceptJson()->get($doc['userinfo_endpoint']);
            if ($res->ok() && is_array($res->json())) {
                return $res->json();
            }
        }
        if (! empty($tokens['id_token'])) {
            return self::decodeIdToken($tokens['id_token']);
        }

        throw new \RuntimeException('Could not read your profile from the identity provider.');
    }

    /** Decode (NOT verify) an id_token's payload — only used as a userinfo fallback. */
    private static function decodeIdToken(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '[]', true);

        return is_array($payload) ? $payload : [];
    }
}
