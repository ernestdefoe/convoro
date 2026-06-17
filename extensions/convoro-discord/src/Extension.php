<?php

namespace Convoro\Ext\Discord;

use App\Events\TopicPublished;
use App\Models\User;
use App\Support\Settings;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Discord — first-party Convoro extension.
 *
 *  • Login: "Continue with Discord" (OAuth2) on the auth modal, registered into
 *    the core `auth:providers` slot from assets/forum.js.
 *  • Role sync: two-way between Discord roles and Convoro groups (see RoleSync),
 *    driven by an admin role map and run at login/link + a manual "resync all".
 *  • Announcements: each new topic posts to its category's Discord channel
 *    webhook — per-category, so only the categories you choose are mirrored.
 *
 * Route actions are closures (NOT [self::class, 'method']) because this class is
 * a ServiceProvider — the router can't resolve its $app constructor as a
 * controller.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Announce newly-published topics (queued; never blocks publishing).
        Event::listen(TopicPublished::class, function (TopicPublished $e) {
            if (self::enabled()) {
                PostToDiscordJob::dispatch($e->topic->id)->afterCommit();
            }
        });

        // OAuth login / account linking.
        Route::middleware('web')->group(function () {
            Route::get('/auth/discord', fn (Request $r) => self::redirectToDiscord($r))->name('discord.redirect');
            Route::get('/auth/discord/callback', fn (Request $r) => self::handleCallback($r))->name('discord.callback');
        });

        // Admin settings.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/discord')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', fn (Request $r) => self::saveSettings($r));
            Route::post('/test', fn () => self::testWebhook());
            Route::post('/resync', fn () => self::resync());
        });
    }

    // ---- Config -------------------------------------------------------------

    public static function enabled(): bool
    {
        return (bool) Settings::get('discord.enabled', false);
    }

    public static function callbackUrl(): string
    {
        return url('/auth/discord/callback');
    }

    /**
     * The role map: each non-empty `groupId = discordRoleId` line.
     *
     * @return list<array{group:int,role:string}>
     */
    public static function roleMap(): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', (string) Settings::get('discord.role_map', '')) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$group, $role] = array_map('trim', explode('=', $line, 2));
            if (ctype_digit($group) && $role !== '') {
                $out[] = ['group' => (int) $group, 'role' => $role];
            }
        }

        return $out;
    }

    /** Webhook URL for a category id, or null if that category isn't mapped. */
    public static function webhookFor(int $categoryId): ?string
    {
        foreach (preg_split('/\r?\n/', (string) Settings::get('discord.webhooks', '')) as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }
            [$cat, $url] = array_map('trim', explode('=', $line, 2));
            if (ctype_digit($cat) && (int) $cat === $categoryId && str_starts_with($url, 'https://')) {
                return $url;
            }
        }

        return null;
    }

    // ---- OAuth login / link -------------------------------------------------

    public static function redirectToDiscord(Request $request)
    {
        if (! DiscordOAuth::configured()) {
            return redirect('/')->with('status', __('Discord login isn’t set up yet.'));
        }
        $request->session()->put('discord.state', $state = Str::random(40));

        return redirect()->away(DiscordOAuth::authorizeUrl(self::callbackUrl(), $state));
    }

    public static function handleCallback(Request $request)
    {
        if (! DiscordOAuth::configured()) {
            return redirect('/')->with('status', __('Discord login isn’t set up yet.'));
        }
        if ($request->query('error')) {
            return redirect('/')->with('status', __('Discord sign-in was cancelled.'));
        }
        $state = (string) $request->query('state');
        if ($state === '' || $state !== $request->session()->pull('discord.state')) {
            return redirect('/')->with('status', __('Discord sign-in could not be verified. Please try again.'));
        }
        $code = (string) $request->query('code');
        if ($code === '') {
            return redirect('/')->with('status', __('Discord sign-in failed.'));
        }

        try {
            $profile = DiscordOAuth::exchangeAndFetch($code, self::callbackUrl());
        } catch (\Throwable $e) {
            return redirect('/')->with('status', __('We couldn’t complete Discord sign-in: :msg', ['msg' => $e->getMessage()]));
        }

        $discordId = $profile['id'];
        $owner = User::where('discord_id', $discordId)->first();

        // Already signed in → link this Discord account to the current user.
        if (Auth::check()) {
            $user = Auth::user();
            if ($owner && $owner->id !== $user->id) {
                return redirect('/')->with('status', __('That Discord account is already linked to another member.'));
            }
            self::linkProfile($user, $profile);
            self::syncRoles($user);

            return redirect('/')->with('status', __('Discord connected as :name.', ['name' => $profile['username']]));
        }

        // Otherwise log in: by existing link, then by email, else create.
        $user = $owner;
        if (! $user && $profile['email']) {
            $user = User::where('email', $profile['email'])->first();
        }
        if (! $user) {
            if (! $profile['email']) {
                return redirect('/')->with('status', __('Discord didn’t share an email address, which is required to create an account.'));
            }
            if (! Settings::get('discord.auto_create', true)) {
                return redirect('/')->with('status', __('No account matches that Discord login. Ask an admin for an invitation.'));
            }
            $user = User::create([
                'name' => Username::sanitize($profile['username'] ?: Str::before($profile['email'], '@')),
                'email' => $profile['email'],
                'password' => bcrypt(Str::random(40)),
                'registration_ip' => $request->ip(),
                'last_ip' => $request->ip(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($user->isBanned()) {
            return redirect('/')->with('status', __('Your account is suspended.'));
        }

        self::linkProfile($user, $profile);
        Auth::login($user, true);
        self::syncRoles($user);

        return redirect()->intended(route('forum.index', absolute: false));
    }

    private static function linkProfile(User $user, array $profile): void
    {
        $user->forceFill([
            'discord_id' => $profile['id'],
            'discord_username' => $profile['username'],
            'discord_avatar' => $profile['avatar'],
        ])->save();
    }

    /** Best-effort role sync — never let a sync failure break login. */
    private static function syncRoles(User $user): void
    {
        try {
            RoleSync::sync($user);
        } catch (\Throwable) {
            // ignore (e.g. member not in guild, bot misconfigured)
        }
    }

    // ---- Admin --------------------------------------------------------------

    public static function saveSettings(Request $request)
    {
        $request->validate([
            'enabled' => ['boolean'],
            'auto_create' => ['boolean'],
            'client_id' => ['nullable', 'string', 'max:64'],
            'client_secret' => ['nullable', 'string', 'max:128'],
            'bot_token' => ['nullable', 'string', 'max:128'],
            'guild_id' => ['nullable', 'string', 'max:64'],
            'role_map' => ['nullable', 'string', 'max:4000'],
            'webhooks' => ['nullable', 'string', 'max:8000'],
        ]);

        Settings::setMany([
            'discord.enabled' => $request->boolean('enabled'),
            'discord.auto_create' => $request->boolean('auto_create'),
            'discord.client_id' => trim((string) $request->input('client_id', '')),
            'discord.client_secret' => trim((string) $request->input('client_secret', '')),
            'discord.bot_token' => trim((string) $request->input('bot_token', '')),
            'discord.guild_id' => trim((string) $request->input('guild_id', '')),
            'discord.role_map' => trim((string) $request->input('role_map', '')),
            'discord.webhooks' => trim((string) $request->input('webhooks', '')),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Post a test embed to the first configured category webhook. */
    public static function testWebhook()
    {
        $url = null;
        foreach (preg_split('/\r?\n/', (string) Settings::get('discord.webhooks', '')) as $line) {
            if (str_contains($line, '=')) {
                $candidate = trim(explode('=', $line, 2)[1]);
                if (str_starts_with($candidate, 'https://')) {
                    $url = $candidate;
                    break;
                }
            }
        }
        if (! $url) {
            return response()->json(['ok' => false, 'message' => 'Add at least one category webhook first.'], 422);
        }

        try {
            $resp = Http::acceptJson()->timeout(20)->post($url, [
                'embeds' => [[
                    'title' => '✅ Convoro is connected',
                    'description' => 'New topics in this category will post here automatically.',
                    'color' => 0x5865F2,
                    'url' => url('/'),
                ]],
            ]);

            return $resp->successful()
                ? response()->json(['ok' => true])
                : response()->json(['ok' => false, 'message' => $resp->json('message') ?? ('HTTP '.$resp->status())], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Queue a full role resync of every linked member. */
    public static function resync()
    {
        if (! DiscordApi::configured()) {
            return response()->json(['ok' => false, 'message' => 'Add your bot token and server ID first.'], 422);
        }
        ResyncRolesJob::dispatch();

        return response()->json(['ok' => true]);
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $e = fn ($k, $d = '') => htmlspecialchars((string) Settings::get("discord.{$k}", $d), ENT_QUOTES);
        $on = Settings::get('discord.enabled', false) ? 'checked' : '';
        $auto = Settings::get('discord.auto_create', true) ? 'checked' : '';
        $clientId = $e('client_id');
        $clientSecret = $e('client_secret');
        $botToken = $e('bot_token');
        $guildId = $e('guild_id');
        $roleMap = $e('role_map');
        $webhooks = $e('webhooks');
        $callback = htmlspecialchars(self::callbackUrl(), ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Discord · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin:0 0 4px}
.hint{color:#9aa0b8;font-size:12.5px;margin:0 0 14px;line-height:1.5}
.hint code{background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:2px 6px;font-size:12px;user-select:all}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:10px 0 4px}
input[type=text],textarea{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
textarea{min-height:90px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12.5px}
label.chk{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#c7cbe0;margin:14px 0 0}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
.ok{color:#9aa0b8;font-size:13px}
button{background:#5865F2;border:0;color:#fff;border-radius:9px;padding:10px 16px;font:inherit;font-weight:600;cursor:pointer}
button:hover{filter:brightness(1.08)}
.toggle{display:flex;align-items:center;gap:10px;font-size:14px;color:#e6e8f5}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Discord</h1><p class="sub">Login, two-way role sync, and per-category channel announcements.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card">
  <label class="toggle"><input type="checkbox" id="enabled" {$on}> <b>Enable Discord integration</b></label>
</div>

<div class="card"><h2>Discord application (login)</h2>
<p class="hint">Create an app at the <a href="https://discord.com/developers/applications" target="_blank" rel="noopener">Discord Developer Portal</a>. Under <b>OAuth2</b>, add this redirect URL: <code>{$callback}</code></p>
<label class="f">Client ID</label><input type="text" id="client_id" value="{$clientId}" placeholder="123456789012345678">
<label class="f">Client Secret</label><input type="text" id="client_secret" value="{$clientSecret}" placeholder="••••••••">
<label class="chk"><input type="checkbox" id="auto_create" {$auto}> Create a Convoro account on first Discord login</label>
</div>

<div class="card"><h2>Bot &amp; role sync</h2>
<p class="hint">Add a bot to your app, invite it to your server with <b>Manage Roles</b>, and drag its role <b>above</b> the roles it should manage. Find your <b>Server ID</b> via Discord → User Settings → Advanced → Developer Mode → right-click the server → Copy Server ID.</p>
<label class="f">Bot Token</label><input type="text" id="bot_token" value="{$botToken}" placeholder="MTk4...">
<label class="f">Server (Guild) ID</label><input type="text" id="guild_id" value="{$guildId}" placeholder="123456789012345678">
<label class="f">Role map <span style="color:#9aa0b8;font-weight:normal">— one per line, <code>convoroGroupId = discordRoleId</code></span></label>
<textarea id="role_map" placeholder="3 = 987654321098765432&#10;5 = 876543210987654321">{$roleMap}</textarea>
<p class="hint" style="margin-top:6px">Synced both ways: holding the Discord role grants the Convoro group, and being in the group grants the Discord role. Only mapped groups/roles are touched.</p>
<div style="margin-top:8px"><button id="resyncBtn" type="button">Resync all members</button> <span class="ok" id="resyncMsg"></span></div>
</div>

<div class="card"><h2>Channel announcements (per category)</h2>
<p class="hint">In Discord: Channel → Edit → Integrations → Webhooks → New Webhook → Copy URL. Map each Convoro <b>category ID</b> to a channel webhook — only listed categories post. New topics only.</p>
<label class="f">Category webhooks <span style="color:#9aa0b8;font-weight:normal">— one per line, <code>categoryId = webhookUrl</code></span></label>
<textarea id="webhooks" placeholder="1 = https://discord.com/api/webhooks/.../...&#10;4 = https://discord.com/api/webhooks/.../...">{$webhooks}</textarea>
<div style="margin-top:8px"><button id="testBtn" type="button">Send a test post</button> <span class="ok" id="testMsg"></span></div>
</div>

<div><span class="ok" id="saveMsg">Changes save automatically</span></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const msg=document.getElementById('saveMsg'),IDLE='Changes save automatically';let t=null;
const notifyParent=(message,kind='success')=>{try{if(window.parent!==window)window.parent.postMessage({type:'convoro:toast',message,kind},location.origin);}catch(e){}};
function collect(){return{
  enabled:document.getElementById('enabled').checked,
  auto_create:document.getElementById('auto_create').checked,
  client_id:document.getElementById('client_id').value,
  client_secret:document.getElementById('client_secret').value,
  bot_token:document.getElementById('bot_token').value,
  guild_id:document.getElementById('guild_id').value,
  role_map:document.getElementById('role_map').value,
  webhooks:document.getElementById('webhooks').value};}
async function save(){msg.style.color='#9aa0b8';msg.textContent='Saving…';
  try{const r=await fetch('/admin/ext/discord',{method:'POST',headers:h,body:JSON.stringify(collect())});
    msg.style.color=r.ok?'#34d399':'#f87171';msg.textContent=r.ok?'Saved ✓':'Error — will retry';
    notifyParent(r.ok?'Settings saved':"Couldn't save",r.ok?'success':'error');}
  catch(e){msg.style.color='#f87171';msg.textContent='Error — will retry';}
  setTimeout(()=>{if(msg.textContent==='Saved ✓'){msg.style.color='#9aa0b8';msg.textContent=IDLE;}},1800);}
function debounced(){if(t)clearTimeout(t);t=setTimeout(save,700);}
['client_id','client_secret','bot_token','guild_id','role_map','webhooks'].forEach(id=>document.getElementById(id).addEventListener('input',debounced));
['enabled','auto_create'].forEach(id=>document.getElementById(id).addEventListener('change',save));
document.getElementById('testBtn').addEventListener('click',async()=>{
  const tm=document.getElementById('testMsg');tm.style.color='#9aa0b8';tm.textContent='Posting…';await save();
  try{const r=await fetch('/admin/ext/discord/test',{method:'POST',headers:h});const d=await r.json();
    tm.style.color=d.ok?'#34d399':'#f87171';tm.textContent=d.ok?'Posted to Discord ✓':(d.message||'Failed');
    notifyParent(d.ok?'Test posted to Discord':("Test failed: "+(d.message||'')),d.ok?'success':'error');}
  catch(e){tm.style.color='#f87171';tm.textContent='Failed';}});
document.getElementById('resyncBtn').addEventListener('click',async()=>{
  const rm=document.getElementById('resyncMsg');rm.style.color='#9aa0b8';rm.textContent='Queuing…';await save();
  try{const r=await fetch('/admin/ext/discord/resync',{method:'POST',headers:h});const d=await r.json();
    rm.style.color=d.ok?'#34d399':'#f87171';rm.textContent=d.ok?'Resync queued ✓':(d.message||'Failed');
    notifyParent(d.ok?'Role resync queued':("Resync failed: "+(d.message||'')),d.ok?'success':'error');}
  catch(e){rm.style.color='#f87171';rm.textContent='Failed';}});
</script></body></html>
HTML;
    }
}
