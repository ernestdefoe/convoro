<?php

namespace Convoro\Ext\Gdpr;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * GDPR & Privacy — first-party Convoro extension.
 *
 * Ships compliance tooling every regulated community needs:
 *  - granular cookie-consent banner (frontend asset) + proof-of-consent log
 *  - member self-service: export my data (Art. 15/20) + erase my account (Art. 17)
 *  - admin: banner copy, category toggles, IP-log retention + manual prune
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Public banner config (consumed by assets/forum.js).
        Route::middleware('web')->get('/api/ext/gdpr/config', fn () => response()->json(self::config()));

        // Record a consent decision (guest or member) — proof of consent.
        Route::middleware('web')->post('/privacy/consent', function (Request $request) {
            $data = $request->validate([
                'analytics' => ['boolean'],
                'marketing' => ['boolean'],
            ]);

            if (Schema::hasTable('gdpr_consents')) {
                DB::table('gdpr_consents')->insert([
                    'user_id' => $request->user()?->id,
                    'ip_address' => $request->ip(),
                    'analytics' => $request->boolean('analytics'),
                    'marketing' => $request->boolean('marketing'),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'created_at' => now(),
                ]);
            }

            return response()->json(['ok' => true]);
        });

        // Member self-service (must be signed in).
        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/privacy', fn () => response(self::privacyPage()))->name('gdpr.privacy');
            Route::get('/privacy/export', fn (Request $r) => self::exportData($r))->name('gdpr.export');
            Route::post('/privacy/erase', fn (Request $r) => self::eraseAccount($r))->name('gdpr.erase');
        });

        // Admin settings.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/gdpr')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'banner_heading' => ['nullable', 'string', 'max:120'],
                    'banner_message' => ['nullable', 'string', 'max:600'],
                    'analytics_enabled' => ['boolean'],
                    'marketing_enabled' => ['boolean'],
                    'ip_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
                    'privacy_url' => ['nullable', 'string', 'max:300'],
                ]);
                Settings::setMany([
                    'gdpr.banner_heading' => $data['banner_heading'] ?? '',
                    'gdpr.banner_message' => $data['banner_message'] ?? '',
                    'gdpr.analytics_enabled' => $request->boolean('analytics_enabled'),
                    'gdpr.marketing_enabled' => $request->boolean('marketing_enabled'),
                    'gdpr.ip_retention_days' => (int) ($data['ip_retention_days'] ?? 0),
                    'gdpr.privacy_url' => $data['privacy_url'] ?? '',
                ]);

                return response()->json(['ok' => true]);
            });
            // Null out IP columns older than the retention window.
            Route::post('/prune', function () {
                $days = (int) Settings::get('gdpr.ip_retention_days', 0);
                if ($days <= 0) {
                    return response()->json(['pruned' => 0, 'message' => 'Retention is set to keep IPs indefinitely.']);
                }
                $cutoff = now()->subDays($days);
                $posts = Schema::hasColumn('posts', 'ip_address')
                    ? DB::table('posts')->whereNotNull('ip_address')->where('created_at', '<', $cutoff)->update(['ip_address' => null]) : 0;
                $users = Schema::hasColumn('users', 'last_ip')
                    ? DB::table('users')->where(function ($q) { $q->whereNotNull('last_ip')->orWhereNotNull('registration_ip'); })
                        ->where('updated_at', '<', $cutoff)->update(['last_ip' => null, 'registration_ip' => null]) : 0;

                return response()->json(['pruned' => $posts + $users, 'message' => "Cleared IPs from {$posts} posts and {$users} accounts."]);
            });
        });
    }

    public static function config(): array
    {
        return [
            'heading' => Settings::get('gdpr.banner_heading') ?: 'We value your privacy',
            'message' => Settings::get('gdpr.banner_message') ?: 'We use cookies to run this community and, with your consent, to understand usage and personalize content. You can change your choice anytime.',
            'analytics' => (bool) Settings::get('gdpr.analytics_enabled', true),
            'marketing' => (bool) Settings::get('gdpr.marketing_enabled', false),
            'privacyUrl' => Settings::get('gdpr.privacy_url') ?: '/privacy',
        ];
    }

    /** Download every piece of personal data we hold for the current member (JSON). */
    public static function exportData(Request $request)
    {
        $u = $request->user();
        $export = [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email,
                'bio' => $u->bio ?? null,
                'created_at' => optional($u->created_at)->toIso8601String(),
                'last_ip' => $u->last_ip ?? null, 'registration_ip' => $u->registration_ip ?? null,
            ],
        ];

        if (Schema::hasTable('topics')) {
            $export['topics'] = DB::table('topics')->where('user_id', $u->id)
                ->get(['title', 'slug', 'created_at']);
        }
        if (Schema::hasTable('posts')) {
            $export['posts'] = DB::table('posts')->where('user_id', $u->id)
                ->get(['topic_id', 'body_html', 'created_at']);
        }
        if (Schema::hasTable('reactions')) {
            $export['reactions'] = DB::table('reactions')->where('user_id', $u->id)
                ->get(['post_id', 'emoji', 'created_at']);
        }
        if (Schema::hasTable('messages')) {
            $export['messages'] = DB::table('messages')->where('user_id', $u->id)
                ->get(['conversation_id', 'body_html', 'created_at']);
        }
        if (Schema::hasTable('profile_posts')) {
            $export['profile_posts'] = DB::table('profile_posts')->where('author_id', $u->id)
                ->get(['profile_user_id', 'body_html', 'created_at']);
        }
        if (Schema::hasTable('gdpr_consents')) {
            $export['consent_history'] = DB::table('gdpr_consents')->where('user_id', $u->id)
                ->get(['analytics', 'marketing', 'created_at']);
        }

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $name = 'convoro-data-export-'.$u->id.'-'.now()->format('Ymd').'.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /** Right to erasure: anonymize the account in place (keeps threads intact). */
    public static function eraseAccount(Request $request)
    {
        $u = $request->user();
        if ($u->is_admin) {
            return redirect('/privacy')->with('status', 'Administrator accounts cannot self-erase.');
        }

        $deletePosts = $request->boolean('delete_posts');

        DB::transaction(function () use ($u, $deletePosts) {
            if ($deletePosts && Schema::hasTable('posts')) {
                DB::table('posts')->where('user_id', $u->id)->where('is_first', false)->delete();
            }

            DB::table('users')->where('id', $u->id)->update([
                'name' => 'Deleted user '.$u->id,
                'email' => 'deleted+'.$u->id.'@deleted.invalid',
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'bio' => null,
                'avatar_path' => null,
                'cover_path' => null,
                'last_ip' => null,
                'registration_ip' => null,
                'updated_at' => now(),
            ]);
        });

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been erased.');
    }

    private static function privacyPage(): string
    {
        $csrf = csrf_token();

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your data &amp; privacy · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:680px;margin:0 auto;padding:48px 20px}a{color:#8b8bf0}
h1{font-size:26px;margin:0 0 6px}.sub{color:#9aa0b8;margin:0 0 28px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:22px;margin-bottom:16px}
.card h2{font-size:16px;margin:0 0 6px}.card p{color:#9aa0b8;font-size:14px;margin:0 0 14px;line-height:1.5}
.btn{display:inline-block;border:0;border-radius:9px;padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff;text-decoration:none}
.btn.danger{background:transparent;color:#f87171;border:1px solid rgba(248,113,113,.4)}
label{display:flex;align-items:center;gap:8px;font-size:14px;color:#c7cbe0;margin:10px 0}
</style></head><body><div class="wrap">
<h1>Your data &amp; privacy</h1><p class="sub">Manage the personal data this community holds about you.</p>

<div class="card">
  <h2>Export your data</h2>
  <p>Download a machine-readable copy of your account, posts, topics, messages, reactions, and consent history (GDPR Art. 15 &amp; 20).</p>
  <a class="btn" href="/privacy/export">Download my data (JSON)</a>
</div>

<div class="card">
  <h2>Erase your account</h2>
  <p>Permanently remove your personal information. Your name and email are anonymized and your profile is cleared (GDPR Art. 17). This cannot be undone.</p>
  <form method="POST" action="/privacy/erase" onsubmit="return confirm('Erase your account? This permanently anonymizes your personal data and signs you out. This cannot be undone.');">
    <input type="hidden" name="_token" value="{$csrf}">
    <label><input type="checkbox" name="delete_posts" value="1"> Also delete all my replies</label>
    <button class="btn danger" type="submit">Erase my account</button>
  </form>
</div>

<p style="text-align:center"><a href="/">← Back to the community</a></p>
</div></body></html>
HTML;
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $c = self::config();
        $heading = htmlspecialchars($c['heading'], ENT_QUOTES);
        $message = htmlspecialchars($c['message'], ENT_QUOTES);
        $privacy = htmlspecialchars($c['privacyUrl'], ENT_QUOTES);
        $days = (int) Settings::get('gdpr.ip_retention_days', 0);
        $aChecked = $c['analytics'] ? 'checked' : '';
        $mChecked = $c['marketing'] ? 'checked' : '';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>GDPR &amp; Privacy · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:720px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin:0 0 12px}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:12px 0 4px}
input[type=text],textarea,input[type=number]{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
label.chk{display:flex;align-items:center;gap:8px;font-size:14px;color:#c7cbe0;margin:10px 0}
.btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff}
.btn.ghost{background:transparent;color:#9aa0b8;border:1px solid rgba(255,255,255,.15)}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
.ok{color:#34d399;font-size:13px;margin-left:10px}
</style></head><body><div class="wrap">
<div class="top"><div><h1>GDPR &amp; Privacy</h1><p class="sub">Consent banner, member data rights, and IP-log retention.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card"><h2>Cookie consent banner</h2>
<label class="f">Heading</label><input type="text" id="banner_heading" value="{$heading}">
<label class="f">Message</label><textarea id="banner_message" rows="3">{$message}</textarea>
<label class="chk"><input type="checkbox" id="analytics_enabled" {$aChecked}> Offer an “Analytics” consent category</label>
<label class="chk"><input type="checkbox" id="marketing_enabled" {$mChecked}> Offer a “Marketing” consent category</label>
<label class="f">Privacy policy / data page URL</label><input type="text" id="privacy_url" value="{$privacy}">
</div>

<div class="card"><h2>IP-log retention</h2>
<label class="f">Automatically forget IP addresses older than (days, 0 = keep indefinitely)</label>
<input type="number" id="ip_retention_days" min="0" max="3650" value="{$days}">
<div style="margin-top:12px"><button class="btn ghost" id="prune">Prune IP logs now</button><span class="ok" id="pruneMsg"></span></div>
</div>

<div><button class="btn" id="save">Save settings</button><span class="ok" id="saveMsg"></span></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
document.getElementById('save').addEventListener('click',async()=>{
  const body={
    banner_heading:document.getElementById('banner_heading').value,
    banner_message:document.getElementById('banner_message').value,
    analytics_enabled:document.getElementById('analytics_enabled').checked,
    marketing_enabled:document.getElementById('marketing_enabled').checked,
    ip_retention_days:parseInt(document.getElementById('ip_retention_days').value||'0',10),
    privacy_url:document.getElementById('privacy_url').value,
  };
  await fetch('/admin/ext/gdpr',{method:'POST',headers:h,body:JSON.stringify(body)});
  const m=document.getElementById('saveMsg');m.textContent='Saved ✓';setTimeout(()=>m.textContent='',2000);
});
document.getElementById('prune').addEventListener('click',async()=>{
  const r=await fetch('/admin/ext/gdpr/prune',{method:'POST',headers:h});const d=await r.json();
  document.getElementById('pruneMsg').textContent=d.message||'Done';
});
</script></body></html>
HTML;
    }
}
