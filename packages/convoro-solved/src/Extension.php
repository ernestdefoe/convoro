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

                return redirect('/admin/ext/solved');
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
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $cats = DB::table('categories')->orderBy('position')->orderBy('name')->get(['id', 'name', 'is_qa']);
        $rows = $cats->map(function ($c) {
            $checked = $c->is_qa ? ' checked' : '';

            return '<label class="row"><span class="b">'.htmlspecialchars($c->name).'</span>'
                .'<input type="checkbox" name="qa[]" value="'.$c->id.'"'.$checked.'></label>';
        })->implode('') ?: '<p class="muted">No categories yet. Create one first.</p>';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Accepted Answers · {$name}</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:680px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}
.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}.muted{color:#9aa0b8}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:6px 18px}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}.row:last-child{border-bottom:0}
.b{flex:1;font-weight:600}input[type=checkbox]{width:20px;height:20px;accent-color:#6b6bf0}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.sp{flex:1}
.btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;background:#6b6bf0;color:#fff;margin-top:18px}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Accepted Answers</h1><p class="sub">Pick which categories work like Q&amp;A. In those, the asker or staff can mark a reply as the answer.</p></div><span class="sp"></span><a href="/admin/marketplace">← Marketplace</a></div>
<form method="post" action="/admin/ext/solved">
<input type="hidden" name="_token" value="{$csrf}">
<div class="card">{$rows}</div>
<button class="btn" type="submit">Save</button>
</form>
</div></body></html>
HTML;
    }
}
