<?php

namespace Convoro\Ext\FacebookAutopost;

use App\Events\TopicPublished;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Facebook Auto Post — first-party Convoro extension.
 *
 * Shares every newly-published topic to a Facebook Page via the Graph API.
 * Dependency-free (Laravel HTTP client only). Listens to the core
 * TopicPublished event, so it never fires for imports/seeds — only genuine
 * member posts. Configure the Page ID + a Page Access Token from the admin
 * settings page.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Share each newly-published topic (queued; never blocks publishing).
        Event::listen(TopicPublished::class, function (TopicPublished $e) {
            if (self::enabled()) {
                PostToFacebookJob::dispatch($e->topic->id)->afterCommit();
            }
        });

        // Admin settings + a test-post button.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/facebook-autopost')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', fn (Request $r) => self::saveSettings($r));
            Route::post('/test', fn () => self::sendTest());
        });
    }

    public static function enabled(): bool
    {
        return (bool) Settings::get('fb_autopost.enabled', false);
    }

    /** Category ids to auto-post (empty = all). */
    public static function categories(): array
    {
        $raw = trim((string) Settings::get('fb_autopost.categories', ''));

        return $raw === '' ? [] : array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $raw))));
    }

    public static function saveSettings(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'page_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:512'],
            'message_template' => ['nullable', 'string', 'max:500'],
            'include_excerpt' => ['boolean'],
            'categories' => ['nullable', 'string', 'max:255'],
        ]);

        Settings::setMany([
            'fb_autopost.enabled' => $request->boolean('enabled'),
            'fb_autopost.page_id' => trim($data['page_id'] ?? ''),
            'fb_autopost.access_token' => trim($data['access_token'] ?? ''),
            'fb_autopost.message_template' => $data['message_template'] ?? '',
            'fb_autopost.include_excerpt' => $request->boolean('include_excerpt'),
            'fb_autopost.categories' => trim($data['categories'] ?? ''),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Post a one-off test message to the Page to verify the credentials. */
    public static function sendTest()
    {
        $pageId = trim((string) Settings::get('fb_autopost.page_id', ''));
        $token = trim((string) Settings::get('fb_autopost.access_token', ''));
        if ($pageId === '' || $token === '') {
            return response()->json(['ok' => false, 'message' => 'Add your Page ID and access token first.'], 422);
        }

        try {
            $resp = Http::asForm()->acceptJson()->timeout(20)->post(
                "https://graph.facebook.com/v19.0/{$pageId}/feed",
                [
                    'message' => '✅ Convoro is connected — new topics will post here automatically.',
                    'link' => url('/'),
                    'access_token' => $token,
                ],
            );
            $body = $resp->json() ?? [];
            if (! $resp->successful() || empty($body['id'])) {
                return response()->json(['ok' => false, 'message' => $body['error']['message'] ?? ('HTTP '.$resp->status())], 422);
            }

            return response()->json(['ok' => true, 'id' => $body['id']]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $e = fn ($k, $d = '') => htmlspecialchars((string) Settings::get("fb_autopost.{$k}", $d), ENT_QUOTES);
        $on = Settings::get('fb_autopost.enabled', false) ? 'checked' : '';
        $exc = Settings::get('fb_autopost.include_excerpt', false) ? 'checked' : '';
        $pageId = $e('page_id');
        $token = $e('access_token');
        $tpl = $e('message_template', 'New on our community: {title}');
        $cats = $e('categories');

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Facebook Auto Post · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
.card h2{font-size:15px;margin:0 0 4px}
.hint{color:#9aa0b8;font-size:12.5px;margin:0 0 14px;line-height:1.5}
.hint code{background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:2px 6px;font-size:12px;user-select:all}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:10px 0 4px}
input[type=text]{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
label.chk{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#c7cbe0;margin:14px 0 0}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
.ok{color:#9aa0b8;font-size:13px}
button{background:#5b5bd6;border:0;color:#fff;border-radius:9px;padding:10px 16px;font:inherit;font-weight:600;cursor:pointer}
button:hover{background:#4f4fce}
.toggle{display:flex;align-items:center;gap:10px;font-size:14px;color:#e6e8f5}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Facebook Auto Post</h1><p class="sub">Automatically share every new topic to your Facebook Page.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card">
  <label class="toggle"><input type="checkbox" id="enabled" {$on}> <b>Enable auto-posting</b></label>
</div>

<div class="card"><h2>Facebook Page</h2>
<p class="hint">Create a Page Access Token in the <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> (or a long-lived System User token) with the <code>pages_manage_posts</code> + <code>pages_read_engagement</code> permissions. Your <b>Page ID</b> is on your Page → About.</p>
<label class="f">Page ID</label><input type="text" id="page_id" value="{$pageId}" placeholder="1234567890">
<label class="f">Page Access Token</label><input type="text" id="access_token" value="{$token}" placeholder="EAAB...">
<div style="margin-top:14px"><button id="testBtn" type="button">Send a test post</button> <span class="ok" id="testMsg"></span></div>
</div>

<div class="card"><h2>What gets posted</h2>
<label class="f">Message template</label>
<input type="text" id="message_template" value="{$tpl}">
<p class="hint" style="margin-top:6px">Placeholders: <code>{title}</code> <code>{author}</code> <code>{category}</code> <code>{excerpt}</code>. The topic link is always attached (Facebook builds a rich preview from it).</p>
<label class="chk"><input type="checkbox" id="include_excerpt" {$exc}> Include an excerpt of the opening post</label>
<label class="f" style="margin-top:14px">Only these categories <span style="color:#9aa0b8;font-weight:normal">(optional — comma-separated category IDs; blank = all)</span></label>
<input type="text" id="categories" value="{$cats}" placeholder="e.g. 1, 4, 7">
</div>

<div><span class="ok" id="saveMsg">Changes save automatically</span></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const msg=document.getElementById('saveMsg'),IDLE='Changes save automatically';let t=null;
const notifyParent=(message,kind='success')=>{try{if(window.parent!==window)window.parent.postMessage({type:'convoro:toast',message,kind},location.origin);}catch(e){}};
function collect(){return{
  enabled:document.getElementById('enabled').checked,
  page_id:document.getElementById('page_id').value,
  access_token:document.getElementById('access_token').value,
  message_template:document.getElementById('message_template').value,
  include_excerpt:document.getElementById('include_excerpt').checked,
  categories:document.getElementById('categories').value};}
async function save(){msg.style.color='#9aa0b8';msg.textContent='Saving…';
  try{const r=await fetch('/admin/ext/facebook-autopost',{method:'POST',headers:h,body:JSON.stringify(collect())});
    msg.style.color=r.ok?'#34d399':'#f87171';msg.textContent=r.ok?'Saved ✓':'Error — will retry';
    notifyParent(r.ok?'Settings saved':"Couldn't save",r.ok?'success':'error');}
  catch(e){msg.style.color='#f87171';msg.textContent='Error — will retry';}
  setTimeout(()=>{if(msg.textContent==='Saved ✓'){msg.style.color='#9aa0b8';msg.textContent=IDLE;}},1800);}
function debounced(){if(t)clearTimeout(t);t=setTimeout(save,700);}
['page_id','access_token','message_template','categories'].forEach(id=>document.getElementById(id).addEventListener('input',debounced));
['enabled','include_excerpt'].forEach(id=>document.getElementById(id).addEventListener('change',save));
document.getElementById('testBtn').addEventListener('click',async()=>{
  const tm=document.getElementById('testMsg');tm.style.color='#9aa0b8';tm.textContent='Posting…';await save();
  try{const r=await fetch('/admin/ext/facebook-autopost/test',{method:'POST',headers:h});const d=await r.json();
    tm.style.color=d.ok?'#34d399':'#f87171';tm.textContent=d.ok?'Posted to your Page ✓':(d.message||'Failed');
    notifyParent(d.ok?'Test posted to Facebook':("Test failed: "+(d.message||'')),d.ok?'success':'error');}
  catch(e){tm.style.color='#f87171';tm.textContent='Failed';}});
</script></body></html>
HTML;
    }
}
