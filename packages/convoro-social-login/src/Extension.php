<?php

namespace Convoro\Ext\SocialLogin;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Social Login — first-party Convoro extension.
 *
 * A dependency-free OAuth2 client (uses Laravel's built-in HTTP client only —
 * no Composer packages), so it installs as a drop-in extension on any host.
 * Supports GitHub, Google, Facebook and X. Each provider is configured from
 * the admin settings page; a login button only appears once its keys are set.
 *
 * Access tokens are never persisted on the user row. The GitHub token is only
 * stored — in Settings('github.token') — when an admin opts into repo access,
 * so the extension store can read their private repositories.
 */
class Extension extends ServiceProvider
{
    /** Public slug => internal driver key. */
    private const DRIVERS = ['github', 'google', 'facebook', 'x'];

    public function boot(): void
    {
        // Which providers are configured (consumed by assets/forum.js).
        Route::middleware('web')->get('/api/ext/social-login/providers', fn () => response()->json([
            'providers' => self::configured(),
        ]));

        // OAuth dance (all hosts).
        Route::middleware('web')->group(function () {
            Route::get('/auth/{provider}/redirect', fn (Request $r, string $provider) => self::redirectToProvider($r, $provider))->name('social.redirect');
            Route::get('/auth/{provider}/callback', fn (Request $r, string $provider) => self::handleCallback($r, $provider))->name('social.callback');
        });

        // Connected-accounts management for signed-in members (Settings page).
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/api/ext/social-login/status', fn (Request $r) => response()->json(self::status($r)));
            Route::post('/auth/{provider}/disconnect', fn (Request $r, string $provider) => self::disconnect($r, $provider))->name('social.disconnect');
        });

        // Admin settings.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/social-login')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', fn (Request $r) => self::saveSettings($r));
        });
    }

    // ── Provider metadata ────────────────────────────────────────────────

    /** Endpoints + behaviour flags per provider. */
    private static function meta(string $p): array
    {
        return match ($p) {
            'github' => [
                'authorize' => 'https://github.com/login/oauth/authorize',
                'token' => 'https://github.com/login/oauth/access_token',
                'scopes' => ['read:user', 'user:email'],
                'pkce' => false,
            ],
            'google' => [
                'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token' => 'https://oauth2.googleapis.com/token',
                'scopes' => ['openid', 'email', 'profile'],
                'pkce' => false,
            ],
            'facebook' => [
                'authorize' => 'https://www.facebook.com/v19.0/dialog/oauth',
                'token' => 'https://graph.facebook.com/v19.0/oauth/access_token',
                'scopes' => ['email', 'public_profile'],
                'pkce' => false,
            ],
            'x' => [
                'authorize' => 'https://twitter.com/i/oauth2/authorize',
                'token' => 'https://api.twitter.com/2/oauth2/token',
                'scopes' => ['users.read', 'tweet.read'],
                'pkce' => true,
            ],
            default => [],
        };
    }

    public static function isValidProvider(string $p): bool
    {
        return in_array($p, self::DRIVERS, true);
    }

    /** Provider slugs that have both a client id and secret configured. */
    public static function configured(): array
    {
        return array_values(array_filter(self::DRIVERS, fn ($p) => self::clientId($p) !== '' && self::clientSecret($p) !== ''));
    }

    private static function clientId(string $p): string
    {
        return trim((string) Settings::get("social.{$p}.client_id", ''));
    }

    private static function clientSecret(string $p): string
    {
        return trim((string) Settings::get("social.{$p}.client_secret", ''));
    }

    private static function redirectUri(string $p): string
    {
        return url('/auth/'.$p.'/callback');
    }

    // ── OAuth2 flow ──────────────────────────────────────────────────────

    public static function redirectToProvider(Request $request, string $provider)
    {
        if (! self::isValidProvider($provider) || self::clientId($provider) === '') {
            abort(404);
        }

        $meta = self::meta($provider);
        $scopes = $meta['scopes'];

        // GitHub: optionally request repo access for the extension store owner.
        if ($provider === 'github' && (bool) Settings::get('social.github.repos', false)) {
            $scopes[] = 'repo';
        }

        // Linking to an existing account (from the Settings page) vs. signing in.
        if ($request->boolean('link') && Auth::check()) {
            $request->session()->put('social.link', true);
        } else {
            $request->session()->forget('social.link');
        }

        $state = bin2hex(random_bytes(20));
        $request->session()->put('social.state', $state);
        $request->session()->put('social.provider', $provider);

        $query = [
            'client_id' => self::clientId($provider),
            'redirect_uri' => self::redirectUri($provider),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        // PKCE (required by X).
        if (! empty($meta['pkce'])) {
            $verifier = bin2hex(random_bytes(48));
            $request->session()->put('social.verifier', $verifier);
            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        return redirect()->away($meta['authorize'].'?'.http_build_query($query));
    }

    public static function handleCallback(Request $request, string $provider)
    {
        if (! self::isValidProvider($provider) || self::clientId($provider) === '') {
            abort(404);
        }

        // CSRF: state must match what we issued for this provider.
        $expected = $request->session()->pull('social.state');
        $verifier = $request->session()->pull('social.verifier');
        if (! $request->filled('code') || ! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect('/')->with('authError', 'Social sign-in was cancelled or expired. Please try again.');
        }

        try {
            $token = self::exchangeCode($provider, (string) $request->query('code'), $verifier);
            if (! ($token['access_token'] ?? null)) {
                throw new \RuntimeException('No access token returned.');
            }
            $profile = self::fetchProfile($provider, $token['access_token']);
            if (! ($profile['id'] ?? null)) {
                throw new \RuntimeException('Could not read your profile from '.$provider.'.');
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect('/')->with('authError', 'We could not sign you in with '.ucfirst($provider).'. Please try again.');
        }

        // Linking flow: attach this identity to the already-signed-in member.
        if ($request->session()->pull('social.link') && Auth::check()) {
            return self::linkToCurrentUser($provider, $profile, $token);
        }

        $user = self::findOrCreateUser($provider, $profile);
        self::maybeStoreGithubToken($provider, $user, $token);

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /** Store the store owner's GitHub token for private-repo access. */
    private static function maybeStoreGithubToken(string $provider, User $user, array $token): void
    {
        if ($provider === 'github' && $user->is_admin
            && str_contains((string) ($token['scope'] ?? ''), 'repo')) {
            Settings::set('github.token', $token['access_token']);
        }
    }

    /** Attach a provider identity to the current user (from Settings → Connect). */
    private static function linkToCurrentUser(string $provider, array $profile, array $token)
    {
        $user = Auth::user();

        $takenBy = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_id', $profile['id'])
            ->where('id', '!=', $user->id)
            ->exists();
        if ($takenBy) {
            return redirect('/profile')->with('authError', 'That '.ucfirst($provider).' account is already linked to another member.');
        }

        $user->forceFill([
            'oauth_provider' => $provider,
            'oauth_id' => $profile['id'],
            'oauth_avatar' => $profile['avatar'] ?: $user->oauth_avatar,
        ])->save();

        self::maybeStoreGithubToken($provider, $user, $token);

        return redirect('/profile')->with('status', ucfirst($provider).' connected.');
    }

    /** Current member's linked provider + which providers are available. */
    public static function status(Request $request): array
    {
        $u = $request->user();

        return [
            'provider' => $u?->oauth_provider,
            'avatar' => $u?->oauth_avatar,
            'providers' => self::configured(),
        ];
    }

    /** Unlink the current member's provider identity. */
    public static function disconnect(Request $request, string $provider)
    {
        $u = $request->user();
        if ($u && $u->oauth_provider === $provider) {
            $u->forceFill(['oauth_provider' => null, 'oauth_id' => null, 'oauth_avatar' => null])->save();
        }

        return response()->json(['ok' => true]);
    }

    /** Exchange the authorization code for an access token. */
    private static function exchangeCode(string $provider, string $code, ?string $verifier): array
    {
        $meta = self::meta($provider);
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => self::redirectUri($provider),
            'client_id' => self::clientId($provider),
            'client_secret' => self::clientSecret($provider),
        ];
        if (! empty($meta['pkce']) && $verifier) {
            $payload['code_verifier'] = $verifier;
        }

        $http = Http::asForm()->acceptJson();

        // X authenticates the confidential client with HTTP Basic.
        if ($provider === 'x') {
            $http = $http->withBasicAuth(self::clientId($provider), self::clientSecret($provider));
        }

        $response = $http->post($meta['token'], $payload);
        $response->throw();

        return $response->json() ?? [];
    }

    /** Fetch and normalise the user profile: id, name, email, verified, avatar. */
    private static function fetchProfile(string $provider, string $token): array
    {
        return match ($provider) {
            'github' => self::githubProfile($token),
            'google' => self::googleProfile($token),
            'facebook' => self::normalize($provider, Http::acceptJson()->get('https://graph.facebook.com/v19.0/me', [
                'fields' => 'id,name,email,picture.width(256)',
                'access_token' => $token,
            ])->throw()->json(), ['id' => 'id', 'name' => 'name', 'email' => 'email', 'avatar' => 'picture.data.url'], true),
            'x' => self::xProfile($token),
            default => [],
        };
    }

    private static function githubProfile(string $token): array
    {
        $u = Http::withToken($token)->withHeaders([
            'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Convoro',
        ])->get('https://api.github.com/user')->throw()->json();

        $email = $u['email'] ?? null;
        $verified = $email !== null;
        if (! $email) {
            $emails = Http::withToken($token)->withHeaders(['User-Agent' => 'Convoro'])
                ->get('https://api.github.com/user/emails')->json() ?? [];
            foreach ($emails as $row) {
                if (($row['primary'] ?? false) && ($row['verified'] ?? false)) {
                    $email = $row['email'];
                    $verified = true;
                    break;
                }
            }
        }

        return [
            'id' => (string) ($u['id'] ?? ''),
            'name' => $u['name'] ?: ($u['login'] ?? 'GitHub user'),
            'email' => $email,
            'verified' => $verified,
            'avatar' => $u['avatar_url'] ?? null,
        ];
    }

    private static function googleProfile(string $token): array
    {
        $u = Http::withToken($token)->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v3/userinfo')->throw()->json();

        return [
            'id' => (string) ($u['sub'] ?? ''),
            'name' => $u['name'] ?: 'Google user',
            'email' => $u['email'] ?? null,
            'verified' => filter_var($u['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'avatar' => $u['picture'] ?? null,
        ];
    }

    private static function xProfile(string $token): array
    {
        $data = Http::withToken($token)->acceptJson()
            ->get('https://api.twitter.com/2/users/me', ['user.fields' => 'profile_image_url'])
            ->throw()->json('data') ?? [];

        return [
            'id' => (string) ($data['id'] ?? ''),
            'name' => $data['name'] ?? ($data['username'] ?? 'X user'),
            'email' => null, // X does not expose email via OAuth2.
            'verified' => false,
            'avatar' => $data['profile_image_url'] ?? null,
        ];
    }

    /** Map an arbitrary provider payload to our normalized shape via dot paths. */
    private static function normalize(string $provider, array $data, array $map, bool $verified): array
    {
        $get = fn ($path) => data_get($data, $path);

        return [
            'id' => (string) ($get($map['id']) ?? ''),
            'name' => $get($map['name']) ?: ucfirst($provider).' user',
            'email' => $get($map['email']),
            'verified' => $verified,
            'avatar' => $get($map['avatar']),
        ];
    }

    /** Find the linked account, link by verified email, or create a new user. */
    private static function findOrCreateUser(string $provider, array $profile): User
    {
        $user = User::query()
            ->where('oauth_provider', $provider)
            ->where('oauth_id', $profile['id'])
            ->first();

        if (! $user && ! empty($profile['email'])) {
            $user = User::query()->where('email', $profile['email'])->first();
        }

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => self::uniqueName($profile['name']),
                'email' => $profile['email'] ?: $provider.'_'.$profile['id'].'@users.noreply.convoro.co',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]);
            if (! empty($profile['verified'])) {
                $user->email_verified_at = now();
            }
        }

        $user->forceFill([
            'oauth_provider' => $provider,
            'oauth_id' => $profile['id'],
            'oauth_avatar' => $profile['avatar'] ?: $user->oauth_avatar,
        ])->save();

        return $user;
    }

    /** Keep display names unique without surprising the member. */
    private static function uniqueName(string $name): string
    {
        $name = trim($name) ?: 'Member';
        $candidate = $name;
        $i = 1;
        while (User::query()->where('name', $candidate)->exists()) {
            $candidate = $name.' '.(++$i);
        }

        return $candidate;
    }

    // ── Admin settings ───────────────────────────────────────────────────

    public static function saveSettings(Request $request)
    {
        $data = $request->validate([
            'github_client_id' => ['nullable', 'string', 'max:255'],
            'github_client_secret' => ['nullable', 'string', 'max:255'],
            'github_repos' => ['boolean'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'facebook_client_id' => ['nullable', 'string', 'max:255'],
            'facebook_client_secret' => ['nullable', 'string', 'max:255'],
            'x_client_id' => ['nullable', 'string', 'max:255'],
            'x_client_secret' => ['nullable', 'string', 'max:255'],
        ]);

        Settings::setMany([
            'social.github.client_id' => $data['github_client_id'] ?? '',
            'social.github.client_secret' => $data['github_client_secret'] ?? '',
            'social.github.repos' => $request->boolean('github_repos'),
            'social.google.client_id' => $data['google_client_id'] ?? '',
            'social.google.client_secret' => $data['google_client_secret'] ?? '',
            'social.facebook.client_id' => $data['facebook_client_id'] ?? '',
            'social.facebook.client_secret' => $data['facebook_client_secret'] ?? '',
            'social.x.client_id' => $data['x_client_id'] ?? '',
            'social.x.client_secret' => $data['x_client_secret'] ?? '',
        ]);

        return response()->json(['ok' => true]);
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $v = fn ($p, $k) => htmlspecialchars((string) Settings::get("social.{$p}.{$k}", ''), ENT_QUOTES);
        $repos = Settings::get('social.github.repos', false) ? 'checked' : '';
        $gh = htmlspecialchars(self::redirectUri('github'), ENT_QUOTES);
        $go = htmlspecialchars(self::redirectUri('google'), ENT_QUOTES);
        $fb = htmlspecialchars(self::redirectUri('facebook'), ENT_QUOTES);
        $xr = htmlspecialchars(self::redirectUri('x'), ENT_QUOTES);

        $ghId = $v('github', 'client_id');
        $ghSecret = $v('github', 'client_secret');
        $goId = $v('google', 'client_id');
        $goSecret = $v('google', 'client_secret');
        $fbId = $v('facebook', 'client_id');
        $fbSecret = $v('facebook', 'client_secret');
        $xId = $v('x', 'client_id');
        $xSecret = $v('x', 'client_secret');

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Social Login · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin:0 0 4px;display:flex;align-items:center;gap:8px}
.hint{color:#9aa0b8;font-size:12.5px;margin:0 0 14px;line-height:1.5}
.hint code{background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:2px 6px;font-size:12px;user-select:all}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:10px 0 4px}
input[type=text]{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
label.chk{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#c7cbe0;margin:14px 0 0}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
.ok{color:#34d399;font-size:13px}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Social Login</h1><p class="sub">Add a provider's keys and its button appears on the sign-in dialog automatically.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card"><h2>GitHub</h2>
<p class="hint">Create an OAuth App at <a href="https://github.com/settings/developers" target="_blank" rel="noopener">github.com/settings/developers</a>. Authorization callback URL: <code>{$gh}</code></p>
<div class="row">
  <div><label class="f">Client ID</label><input type="text" id="github_client_id" value="{$ghId}"></div>
  <div><label class="f">Client secret</label><input type="text" id="github_client_secret" value="{$ghSecret}"></div>
</div>
<label class="chk"><input type="checkbox" id="github_repos" {$repos}> Request access to private repositories (lets the store owner list private GitHub repos)</label>
</div>

<div class="card"><h2>Google</h2>
<p class="hint">Create an OAuth 2.0 Client ID in the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud console</a>. Authorized redirect URI: <code>{$go}</code></p>
<div class="row">
  <div><label class="f">Client ID</label><input type="text" id="google_client_id" value="{$goId}"></div>
  <div><label class="f">Client secret</label><input type="text" id="google_client_secret" value="{$goSecret}"></div>
</div>
</div>

<div class="card"><h2>Facebook</h2>
<p class="hint">Create an app at <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com</a> and add Facebook Login. Valid OAuth Redirect URI: <code>{$fb}</code></p>
<div class="row">
  <div><label class="f">App ID</label><input type="text" id="facebook_client_id" value="{$fbId}"></div>
  <div><label class="f">App secret</label><input type="text" id="facebook_client_secret" value="{$fbSecret}"></div>
</div>
</div>

<div class="card"><h2>X</h2>
<p class="hint">Create an OAuth 2.0 app in the <a href="https://developer.twitter.com/en/portal/dashboard" target="_blank" rel="noopener">X developer portal</a> (Confidential client, PKCE). Callback URI: <code>{$xr}</code></p>
<div class="row">
  <div><label class="f">Client ID</label><input type="text" id="x_client_id" value="{$xId}"></div>
  <div><label class="f">Client secret</label><input type="text" id="x_client_secret" value="{$xSecret}"></div>
</div>
</div>

<div><span class="ok" id="saveMsg" style="color:#9aa0b8">Changes save automatically</span></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const msg=document.getElementById('saveMsg'),IDLE='Changes save automatically';let t=null;
const ids=['github_client_id','github_client_secret','google_client_id','google_client_secret','facebook_client_id','facebook_client_secret','x_client_id','x_client_secret'];
const notifyParent=(message,kind='success')=>{try{if(window.parent!==window)window.parent.postMessage({type:'convoro:toast',message,kind},location.origin);}catch(e){}};
function collect(){const o={github_repos:document.getElementById('github_repos').checked};ids.forEach(id=>o[id]=document.getElementById(id).value);return o;}
async function save(){msg.style.color='#9aa0b8';msg.textContent='Saving…';
  try{const r=await fetch('/admin/ext/social-login',{method:'POST',headers:h,body:JSON.stringify(collect())});
    msg.style.color=r.ok?'#34d399':'#f87171';msg.textContent=r.ok?'Saved ✓':'Error — will retry';
    notifyParent(r.ok?'Settings saved':"Couldn't save",r.ok?'success':'error');}
  catch(e){msg.style.color='#f87171';msg.textContent='Error — will retry';notifyParent("Couldn't save",'error');}
  setTimeout(()=>{if(msg.textContent==='Saved ✓'){msg.style.color='#9aa0b8';msg.textContent=IDLE;}},1800);}
function debounced(){if(t)clearTimeout(t);t=setTimeout(save,700);}
ids.forEach(id=>document.getElementById(id).addEventListener('input',debounced));
document.getElementById('github_repos').addEventListener('change',save);
</script></body></html>
HTML;
    }
}
