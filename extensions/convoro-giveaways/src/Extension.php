<?php

namespace Convoro\Ext\Giveaways;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Giveaways — first-party Convoro extension.
 *
 * Members enter an active giveaway with one click; a published seed makes the
 * draw provably fair (winner = lowest sha1(seed:user_id), recomputable by
 * anyone from the seed + entrant list). Ships a forum sidebar widget and a
 * full admin manager.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Public: the current active giveaway (+ whether the viewer has entered).
        Route::middleware('web')->get('/api/ext/giveaways/active', function (Request $request) {
            $g = DB::table('giveaways')->where('active', true)->orderByDesc('id')->first();
            if (! $g) {
                return response()->json(['giveaway' => null]);
            }
            $entries = DB::table('giveaway_entries')->where('giveaway_id', $g->id)->count();
            $entered = $request->user()
                ? DB::table('giveaway_entries')->where('giveaway_id', $g->id)->where('user_id', $request->user()->id)->exists()
                : false;

            return response()->json(['giveaway' => [
                'id' => $g->id, 'title' => $g->title, 'prize' => $g->prize,
                'description' => $g->description, 'endsAt' => $g->ends_at,
                'entries' => $entries, 'entered' => $entered,
                'authed' => (bool) $request->user(),
            ]]);
        });

        // Enter (auth).
        Route::middleware(['web', 'auth'])->post('/api/ext/giveaways/{id}/enter', function (Request $request, int $id) {
            $g = DB::table('giveaways')->where('id', $id)->where('active', true)->first();
            abort_if(! $g, 404);
            if ($g->ends_at && now()->greaterThan($g->ends_at)) {
                return response()->json(['ok' => false, 'message' => 'This giveaway has ended.'], 422);
            }
            DB::table('giveaway_entries')->updateOrInsert(
                ['giveaway_id' => $id, 'user_id' => $request->user()->id],
                ['created_at' => now()],
            );

            return response()->json(['ok' => true, 'entries' => DB::table('giveaway_entries')->where('giveaway_id', $id)->count()]);
        });

        // Admin manager + CRUD.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/giveaways')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));

            Route::get('/list', function () {
                $rows = DB::table('giveaways')->orderByDesc('id')->get();

                return response()->json($rows->map(function ($g) {
                    $entries = DB::table('giveaway_entries')->where('giveaway_id', $g->id)->count();
                    $winner = $g->winner_user_id ? optional(User::find($g->winner_user_id))->name : null;

                    return [
                        'id' => $g->id, 'title' => $g->title, 'prize' => $g->prize,
                        'active' => (bool) $g->active, 'entries' => $entries,
                        'winner' => $winner, 'seed' => $g->seed, 'endsAt' => $g->ends_at,
                        'drawnAt' => $g->drawn_at,
                    ];
                }));
            });

            Route::post('/', function (Request $request) {
                $data = $request->validate([
                    'title' => ['required', 'string', 'max:160'],
                    'prize' => ['required', 'string', 'max:200'],
                    'description' => ['nullable', 'string', 'max:2000'],
                    'ends_at' => ['nullable', 'date'],
                ]);
                $id = DB::table('giveaways')->insertGetId([
                    'title' => $data['title'], 'prize' => $data['prize'],
                    'description' => $data['description'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'seed' => Str::random(40),
                    'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);

                return response()->json(['id' => $id]);
            });

            Route::post('/{id}/draw', function (int $id) {
                $g = DB::table('giveaways')->find($id);
                abort_if(! $g, 404);
                $entries = DB::table('giveaway_entries')->where('giveaway_id', $id)->pluck('user_id')->all();
                if (! $entries) {
                    return response()->json(['ok' => false, 'message' => 'No entries to draw from.'], 422);
                }
                // Provably fair: winner = entrant with the lowest sha1(seed:user_id).
                usort($entries, fn ($a, $b) => strcmp(sha1($g->seed.':'.$a), sha1($g->seed.':'.$b)));
                $winnerId = $entries[0];
                DB::table('giveaways')->where('id', $id)->update([
                    'winner_user_id' => $winnerId, 'drawn_at' => now(), 'active' => false, 'updated_at' => now(),
                ]);

                return response()->json(['ok' => true, 'winner' => optional(User::find($winnerId))->name]);
            });

            Route::post('/{id}/toggle', function (int $id) {
                $g = DB::table('giveaways')->find($id);
                abort_if(! $g, 404);
                DB::table('giveaways')->where('id', $id)->update(['active' => ! $g->active, 'updated_at' => now()]);

                return response()->json(['active' => ! $g->active]);
            });

            Route::delete('/{id}', function (int $id) {
                DB::table('giveaways')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
        });
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();

        return <<<HTML
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Giveaways · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}
h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:20px;margin-bottom:16px}
input,textarea{width:100%;background:#0f1120;border:1px solid rgba(255,255,255,.1);border-radius:9px;color:#e6e8f5;padding:10px 12px;font:inherit;margin-top:6px}
.btn{border:0;border-radius:9px;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer;background:#5b5bd6;color:#fff}
.btn.ghost{background:transparent;color:#9aa0b8;border:1px solid rgba(255,255,255,.15)}.btn.danger{background:transparent;color:#f87171}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}.row:last-child{border-bottom:0}
.row .b{flex:1;min-width:0}.tag{font-size:11px;color:#9aa0b8}.win{color:#34d399;font-weight:700}
.dot{width:9px;height:9px;border-radius:50%}.on{background:#34d399}.off{background:#52586e}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.top .sp{flex:1}
label{display:block;font-size:13px;color:#c7cbe0;margin-top:10px}.seed{font-family:ui-monospace,monospace;font-size:11px;color:#6b7194;word-break:break-all}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Giveaways</h1><p class="sub">One active giveaway shows in the forum sidebar. Draws are provably fair from the published seed.</p></div>
<div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>
<div class="card">
<label>Title</label><input id="title" placeholder="December community giveaway">
<label>Prize</label><input id="prize" placeholder="$50 gift card">
<label>Description (optional)</label><textarea id="description" rows="2"></textarea>
<label>Ends at (optional)</label><input id="ends_at" type="datetime-local">
<div style="margin-top:14px"><button class="btn" id="add">Create giveaway</button></div>
</div>
<div class="card"><div id="list">Loading…</div></div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
const esc=s=>(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
async function load(){const r=await fetch('/admin/ext/giveaways/list',{headers:h});const rows=await r.json();
const el=document.getElementById('list');
if(!rows.length){el.innerHTML='<p style="color:#9aa0b8">No giveaways yet.</p>';return;}
el.innerHTML=rows.map(g=>`<div class="row"><span class="dot \${g.active?'on':'off'}"></span>
<div class="b"><div><b>\${esc(g.title)}</b> — \${esc(g.prize)}</div>
<span class="tag">\${g.entries} entries\${g.winner?` · winner: <span class="win">\${esc(g.winner)}</span>`:''}</span>
<div class="seed">seed: \${esc(g.seed)}</div></div>
\${g.winner?'':`<button class="btn ghost" onclick="draw(\${g.id})">Draw winner</button>`}
<button class="btn ghost" onclick="tog(\${g.id})">\${g.active?'Close':'Reopen'}</button>
<button class="btn danger" onclick="del(\${g.id})">Delete</button></div>`).join('');}
async function add(){const body={title:document.getElementById('title').value.trim(),prize:document.getElementById('prize').value.trim(),
description:document.getElementById('description').value.trim(),ends_at:document.getElementById('ends_at').value||null};
if(!body.title||!body.prize)return;
await fetch('/admin/ext/giveaways',{method:'POST',headers:h,body:JSON.stringify(body)});
document.getElementById('title').value='';document.getElementById('prize').value='';document.getElementById('description').value='';load();}
async function draw(id){if(!confirm('Draw a winner now? This closes the giveaway.'))return;
const r=await fetch('/admin/ext/giveaways/'+id+'/draw',{method:'POST',headers:h});const d=await r.json();
if(!d.ok){alert(d.message||'Could not draw.');return;}alert('Winner: '+d.winner);load();}
async function tog(id){await fetch('/admin/ext/giveaways/'+id+'/toggle',{method:'POST',headers:h});load();}
async function del(id){if(!confirm('Delete this giveaway?'))return;await fetch('/admin/ext/giveaways/'+id,{method:'DELETE',headers:h});load();}
document.getElementById('add').addEventListener('click',add);load();
</script></body></html>
HTML;
    }
}
