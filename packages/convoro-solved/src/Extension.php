<?php

namespace Convoro\Ext\Solved;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Accepted Answers — first-party Convoro extension.
 *
 * Marks categories as Q&A. In a Q&A topic the asker (or staff) can mark a reply
 * as the accepted answer (topics.solved_post_id). The forum bundle renders the
 * button into the core `post:actions` slot and reads state from this API.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            // Per-topic state for the forum bundle: is the category Q&A, which
            // post is the answer, and may the current viewer mark one.
            Route::get('/api/ext/solved/topic/{topic}', function (int $topic) {
                $t = DB::table('topics')->find($topic);
                if (! $t) {
                    return response()->json(['isQa' => false, 'solvedPostId' => null, 'canMark' => false]);
                }

                return response()->json([
                    'isQa' => self::isQa($t),
                    'solvedPostId' => $t->solved_post_id ? (int) $t->solved_post_id : null,
                    'canMark' => self::canMark(Auth::user(), $t),
                ]);
            });
        });

        Route::middleware(['web', 'auth'])->group(function () {
            // Toggle a reply as the accepted answer.
            Route::post('/api/ext/solved/topic/{topic}/post/{post}', function (Request $request, int $topic, int $post) {
                $t = DB::table('topics')->find($topic);
                abort_if(! $t, 404);
                abort_unless(self::canMark($request->user(), $t), 403);
                abort_unless(self::isQa($t), 422);

                $p = DB::table('posts')->where('id', $post)->where('topic_id', $topic)->first();
                abort_if(! $p || $p->is_first, 404);

                $next = ((int) $t->solved_post_id === $post) ? null : $post;
                DB::table('topics')->where('id', $topic)->update(['solved_post_id' => $next]);

                return response()->json(['solvedPostId' => $next]);
            });
        });

        Route::middleware(['web', 'auth', 'admin'])->group(function () {
            Route::get('/admin/ext/solved', fn () => response(self::adminPage()));
            Route::post('/admin/ext/solved', function (Request $request) {
                $ids = collect($request->input('qa', []))->map(fn ($v) => (int) $v)->filter()->all();
                DB::table('categories')->update(['is_qa' => false]);
                if ($ids) {
                    DB::table('categories')->whereIn('id', $ids)->update(['is_qa' => true]);
                }

                return response()->json(['ok' => true, 'count' => count($ids)]);
            });
        });
    }

    /** Is the topic in a Q&A category? */
    private static function isQa(object $topic): bool
    {
        if (! $topic->category_id) {
            return false;
        }

        return (bool) DB::table('categories')->where('id', $topic->category_id)->value('is_qa');
    }

    /** May $user mark/clear the answer? The asker or a moderator/admin. */
    private static function canMark($user, object $topic): bool
    {
        if (! $user) {
            return false;
        }

        return (int) $topic->user_id === (int) $user->id
            || (bool) ($user->is_admin ?? false)
            || (bool) $user->hasPermission('topic.lock');
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $theme = \App\Support\Theme::css();
        $font = \App\Support\Theme::fontStack((string) Settings::get('theme.font', 'Inter'));
        $mode = htmlspecialchars((string) Settings::get('theme.mode', 'light'), ENT_QUOTES);
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $cats = DB::table('categories')->orderBy('position')->orderBy('name')->get(['id', 'name', 'is_qa']);
        $rows = $cats->map(function ($c) {
            $checked = $c->is_qa ? ' checked' : '';

            return '<label class="row"><span class="nm">'.htmlspecialchars($c->name).'</span>'
                .'<input type="checkbox" data-id="'.$c->id.'"'.$checked.'></label>';
        })->implode('') ?: '<p class="muted">No categories yet — create one in Admin → Categories &amp; Tags first.</p>';

        return <<<HTML
<!DOCTYPE html><html lang="en" data-theme="{$mode}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Accepted Answers · {$name}</title>
<style>{$theme}
:root,html[data-theme="light"]{--c-bg:243 244 249;--c-surface:255 255 255;--c-surface-2:248 249 252;--c-border:230 232 240;--c-text:27 32 48;--c-text-2:74 81 104;--c-muted:138 144 166}
html[data-theme="dark"]{--c-bg:16 18 30;--c-surface:22 25 41;--c-surface-2:28 32 52;--c-border:42 47 70;--c-text:233 235 243;--c-text-2:174 180 208;--c-muted:120 127 152}
*{box-sizing:border-box}body{margin:0;font-family:{$font};background:rgb(var(--c-bg));color:rgb(var(--c-text))}
a{color:rgb(var(--c-primary));text-decoration:none}a:hover{text-decoration:underline}
.bar{display:flex;align-items:center;gap:12px;padding:14px 24px;border-bottom:1px solid rgb(var(--c-border));background:rgb(var(--c-surface))}
.bar .dot{display:grid;place-items:center;width:30px;height:30px;border-radius:8px;background:rgb(var(--c-primary));color:#fff;font-weight:800}
.bar b{font-weight:800}.bar .sp{flex:1}
.wrap{max-width:640px;margin:0 auto;padding:32px 24px}
h1{font-size:22px;margin:0 0 4px}.sub{color:rgb(var(--c-muted));margin:0 0 22px;font-size:14px}
.card{background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);padding:2px 18px}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgb(var(--c-border))}.row:last-child{border-bottom:0}
.nm{flex:1;font-weight:600}.muted{color:rgb(var(--c-muted));padding:18px 0;margin:0}
input[type=checkbox]{width:20px;height:20px;accent-color:rgb(var(--c-primary))}
.actions{display:flex;align-items:center;gap:14px;margin-top:18px}
.btn{border:0;border-radius:var(--c-radius-btn,9px);padding:10px 20px;font:inherit;font-weight:700;cursor:pointer;background:rgb(var(--c-primary));color:#fff}
.btn:disabled{opacity:.6;cursor:default}.msg{color:rgb(var(--c-muted));font-size:14px}
</style></head><body>
<div class="bar"><span class="dot">C</span><b>{$name}</b><span class="sp"></span><a href="/admin">← Back to Admin</a></div>
<div class="wrap">
<h1>Accepted Answers</h1>
<p class="sub">Choose which categories work like Q&amp;A. In those, the person who asked — or your staff — can mark a reply as the accepted answer.</p>
<div class="card" id="cats">{$rows}</div>
<div class="actions"><button class="btn" id="save">Save</button><span class="msg" id="msg"></span></div>
</div>
<script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const btn=document.getElementById('save'), msg=document.getElementById('msg');
btn.addEventListener('click', async () => {
  const qa=[...document.querySelectorAll('#cats input[type=checkbox]:checked')].map(i=>i.dataset.id);
  btn.disabled=true; msg.textContent='Saving…';
  try {
    const r=await fetch('/admin/ext/solved',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({qa})});
    msg.textContent = r.ok ? 'Saved ✓' : 'Could not save.';
  } catch(e){ msg.textContent='Could not save.'; }
  btn.disabled=false;
  setTimeout(()=>{ msg.textContent=''; }, 2500);
});
</script></body></html>
HTML;
    }
}
