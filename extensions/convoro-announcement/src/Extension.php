<?php

namespace Convoro\Ext\Announcement;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Sample first-party extension. Demonstrates an extension that contributes:
 *  - a public forum API endpoint (the live banner data)
 *  - a full, authenticated admin management page + CRUD endpoints
 *  - a migration + a permission
 * …all without Composer and without touching core.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Public: the most recent active announcement (feeds the forum banner).
        Route::middleware('web')->get('/api/ext/announcement', function () {
            $row = DB::table('ext_announcements')->where('active', true)->orderByDesc('id')->first();

            return response()->json(['body' => $row->body ?? null]);
        });

        // Admin: management page + JSON CRUD (gated by auth + admin).
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/announcements')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));

            Route::get('/list', fn () => response()->json(
                DB::table('ext_announcements')->orderByDesc('id')->get()
            ));

            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'body' => ['required', 'string', 'max:2000'],
                    'active' => ['boolean'],
                ]);
                $id = DB::table('ext_announcements')->insertGetId([
                    'body' => $data['body'],
                    'active' => $request->boolean('active', true),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return response()->json(['id' => $id]);
            });

            Route::post('/{id}/toggle', function (int $id) {
                $row = DB::table('ext_announcements')->find($id);
                abort_if(! $row, 404);
                DB::table('ext_announcements')->where('id', $id)->update(['active' => ! $row->active, 'updated_at' => now()]);

                return response()->json(['active' => ! $row->active]);
            });

            Route::delete('/{id}', function (int $id) {
                DB::table('ext_announcements')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
        });
    }

    /** Self-contained admin page (Convoro-dark styled, vanilla JS CRUD). */
    private static function adminPage(): string
    {
        $csrf = csrf_token();

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Announcements · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}
a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
textarea,input{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit}
label{display:flex;align-items:center;gap:8px;font-size:14px;color:#c7cbe0;margin-top:10px}
.btn{border:0;border-radius:9px;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff}
.btn.ghost{background:transparent;color:#9aa0b8}.btn.danger{background:transparent;color:#f87171}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.row:last-child{border-bottom:0}.row .body{flex:1;min-width:0}.dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.on{background:#34d399}.off{background:#52586e}.tag{font-size:11px;color:#9aa0b8}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Announcements</h1><p class="sub">Banners shown on discussion pages — the newest active one displays.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>
<div class="card"><textarea id="body" rows="3" placeholder="Write an announcement…"></textarea>
<label><input type="checkbox" id="active" checked> Active</label>
<div style="margin-top:12px"><button class="btn" id="add">Add announcement</button></div></div>
<div class="card"><div id="list">Loading…</div></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const esc=s=>s.replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
async function load(){const r=await fetch('/admin/ext/announcements/list',{headers:h});const rows=await r.json();
const el=document.getElementById('list');
if(!rows.length){el.innerHTML='<p style="color:#9aa0b8">No announcements yet.</p>';return;}
el.innerHTML=rows.map(a=>`<div class="row"><span class="dot \${a.active?'on':'off'}"></span>
<div class="body"><div>\${esc(a.body)}</div><span class="tag">\${a.active?'Active':'Hidden'}</span></div>
<button class="btn ghost" onclick="tog(\${a.id})">\${a.active?'Hide':'Show'}</button>
<button class="btn danger" onclick="del(\${a.id})">Delete</button></div>`).join('');}
async function add(){const body=document.getElementById('body').value.trim();if(!body)return;
await fetch('/admin/ext/announcements',{method:'POST',headers:h,body:JSON.stringify({body,active:document.getElementById('active').checked})});
document.getElementById('body').value='';load();}
async function tog(id){await fetch('/admin/ext/announcements/'+id+'/toggle',{method:'POST',headers:h});load();}
async function del(id){if(!confirm('Delete this announcement?'))return;await fetch('/admin/ext/announcements/'+id,{method:'DELETE',headers:h});load();}
document.getElementById('add').addEventListener('click',add);load();
</script></body></html>
HTML;
    }
}
