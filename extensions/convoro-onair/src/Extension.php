<?php

namespace Convoro\Ext\OnAir;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * OnAir (Lite) — first-party Convoro extension.
 *
 * Embeds a live YouTube or Twitch broadcast in the forum sidebar with a pulsing
 * LIVE badge while you're on air. Settings-only (no table); the frontend builds
 * the embed so Twitch's required `parent` can use the live hostname.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Public: current stream state for the forum widget.
        Route::middleware('web')->get('/api/ext/onair/current', fn () => response()->json(self::current()));

        // Admin: settings page + save.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/onair')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'live' => ['boolean'],
                    'platform' => ['required', 'in:youtube,twitch'],
                    'stream_id' => ['nullable', 'string', 'max:120'],
                    'title' => ['nullable', 'string', 'max:160'],
                ]);
                Settings::setMany([
                    'onair.live' => $request->boolean('live'),
                    'onair.platform' => $data['platform'],
                    'onair.stream_id' => trim((string) ($data['stream_id'] ?? '')),
                    'onair.title' => $data['title'] ?? '',
                ]);

                return response()->json(['ok' => true]);
            });
        });
    }

    public static function current(): array
    {
        $id = trim((string) Settings::get('onair.stream_id', ''));

        return [
            'live' => (bool) Settings::get('onair.live', false) && $id !== '',
            'platform' => (string) Settings::get('onair.platform', 'youtube'),
            'streamId' => $id,
            'title' => (string) (Settings::get('onair.title') ?: 'Live now'),
        ];
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $live = Settings::get('onair.live') ? 'checked' : '';
        $platform = (string) Settings::get('onair.platform', 'youtube');
        $sid = $e(Settings::get('onair.stream_id'));
        $title = $e(Settings::get('onair.title'));
        $sel = fn ($a, $b) => $a === $b ? 'selected' : '';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>OnAir · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:640px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px}
label.f{display:block;font-size:13px;color:#c7cbe0;margin:14px 0 4px}
input,select{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
label.chk{display:flex;align-items:center;gap:8px;font-size:15px;color:#c7cbe0;margin:4px 0 6px}
.btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff;margin-top:18px}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.sp{flex:1}.ok{color:#34d399;font-size:13px;margin-left:10px}
.hint{color:#6b7194;font-size:12px;margin:4px 0 0}
</style></head><body><div class="wrap">
<div class="top"><div><h1>OnAir</h1><p class="sub">Embed a live stream in the community sidebar.</p></div><span class="sp"></span><a href="/admin/marketplace">← Marketplace</a></div>
<div class="card">
<label class="chk"><input type="checkbox" id="live" {$live}> 🔴 We're live — show the player now</label>
<label class="f">Platform</label><select id="platform"><option value="youtube" {$sel($platform,'youtube')}>YouTube</option><option value="twitch" {$sel($platform,'twitch')}>Twitch</option></select>
<label class="f">Stream ID</label><input id="stream_id" value="{$sid}" placeholder="YouTube video ID or Twitch channel name">
<p class="hint">YouTube: the video ID from the watch URL (…/watch?v=<b>ID</b>). Twitch: your channel name.</p>
<label class="f">Title</label><input id="title" value="{$title}" placeholder="Live now">
<button class="btn" id="save">Save</button><span class="ok" id="msg"></span>
</div></div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
document.getElementById('save').addEventListener('click',async()=>{
  const body={live:document.getElementById('live').checked,platform:document.getElementById('platform').value,
    stream_id:document.getElementById('stream_id').value,title:document.getElementById('title').value};
  const r=await fetch('/admin/ext/onair',{method:'POST',headers:h,body:JSON.stringify(body)});
  const m=document.getElementById('msg');m.textContent=r.ok?'Saved ✓':'Error';setTimeout(()=>m.textContent='',2000);
});
</script></body></html>
HTML;
    }
}
