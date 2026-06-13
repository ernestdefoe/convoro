<?php

namespace Convoro\Ext\Bookmarks;

use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Bookmarks — first-party Convoro extension.
 *
 * Members save any post to a personal reading list (the `post:actions` slot
 * gets a bookmark toggle) and revisit them on a private /bookmarks page. Opt-in.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Lightweight auth + count probe for the header nav link.
        Route::middleware('web')->get('/api/ext/bookmarks/me', function () {
            $in = Auth::check();

            return response()->json([
                'loggedIn' => $in,
                'count' => $in ? (int) DB::table('bookmarks')->where('user_id', Auth::id())->count() : 0,
            ]);
        });

        // Which posts in a topic the viewer has bookmarked (+ whether they can).
        Route::middleware('web')->get('/api/ext/bookmarks/topic/{topic}', function (int $topic) {
            if (! Auth::check()) {
                return response()->json(['canBookmark' => false, 'ids' => []]);
            }
            $ids = DB::table('bookmarks')->where('user_id', Auth::id())
                ->whereIn('post_id', DB::table('posts')->where('topic_id', $topic)->pluck('id'))
                ->pluck('post_id');

            return response()->json(['canBookmark' => true, 'ids' => $ids]);
        });

        Route::middleware(['web', 'auth'])->group(function () {
            // Toggle a bookmark on a post.
            Route::post('/api/ext/bookmarks/post/{post}', function (Request $request, int $post) {
                abort_unless(DB::table('posts')->where('id', $post)->exists(), 404);
                $uid = Auth::id();
                $q = DB::table('bookmarks')->where('user_id', $uid)->where('post_id', $post);
                if ($q->exists()) {
                    $q->delete();
                    $bookmarked = false;
                } else {
                    DB::table('bookmarks')->insert(['user_id' => $uid, 'post_id' => $post, 'created_at' => now()]);
                    $bookmarked = true;
                }

                return response()->json(['bookmarked' => $bookmarked, 'count' => (int) DB::table('bookmarks')->where('user_id', $uid)->count()]);
            });

            // The member's saved-posts page.
            Route::get('/bookmarks', fn () => response(self::page()));
        });
    }

    private static function page(): string
    {
        $theme = Theme::css();
        $font = Theme::fontStack((string) Settings::get('theme.font', 'Inter'));
        $mode = htmlspecialchars((string) Settings::get('theme.mode', 'light'), ENT_QUOTES);
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $csrf = csrf_token();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);

        $rows = DB::table('bookmarks')
            ->join('posts', 'posts.id', '=', 'bookmarks.post_id')
            ->join('topics', 'topics.id', '=', 'posts.topic_id')
            ->where('bookmarks.user_id', Auth::id())
            ->orderByDesc('bookmarks.id')
            ->limit(200)
            ->get(['posts.id as post_id', 'posts.body_html', 'topics.title', 'topics.slug', 'bookmarks.created_at']);

        $items = '';
        foreach ($rows as $r) {
            $excerpt = trim(Str::limit(strip_tags((string) $r->body_html), 220));
            $items .= '<div class="bm" data-id="'.$r->post_id.'">'
                .'<div class="body"><a class="t" href="/t/'.$e($r->slug).'#post-'.$r->post_id.'">'.$e($r->title).'</a>'
                .($excerpt !== '' ? '<p class="ex">'.$e($excerpt).'</p>' : '').'</div>'
                .'<button class="rm" title="Remove bookmark" data-post="'.$r->post_id.'">✕</button></div>';
        }
        if ($items === '') {
            $items = '<div class="empty">No bookmarks yet. Tap the bookmark icon on any post to save it here.</div>';
        }

        return <<<HTML
<!DOCTYPE html><html lang="en" data-theme="{$mode}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Bookmarks · {$name}</title>
<style>{$theme}
:root,html[data-theme="light"]{--c-bg:243 244 249;--c-surface:255 255 255;--c-surface-2:248 249 252;--c-border:230 232 240;--c-text:27 32 48;--c-text-2:74 81 104;--c-muted:138 144 166}
html[data-theme="dark"]{--c-bg:16 18 30;--c-surface:22 25 41;--c-surface-2:28 32 52;--c-border:42 47 70;--c-text:233 235 243;--c-text-2:174 180 208;--c-muted:120 127 152}
*{box-sizing:border-box}body{margin:0;font-family:{$font};background:rgb(var(--c-bg));color:rgb(var(--c-text))}
a{color:inherit;text-decoration:none}
.bar{display:flex;align-items:center;gap:12px;padding:14px 24px;border-bottom:1px solid rgb(var(--c-border));background:rgb(var(--c-surface))}
.bar b{font-weight:800}.bar .sp{flex:1}.bar .home{color:rgb(var(--c-primary));font-weight:700}
.wrap{max-width:680px;margin:0 auto;padding:32px 20px}
h1{font-size:26px;margin:0 0 4px}.sub{color:rgb(var(--c-muted));margin:0 0 24px}
.bm{display:flex;align-items:flex-start;gap:12px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);padding:14px 16px;margin-bottom:12px}
.body{min-width:0;flex:1}.t{font-weight:700;font-size:16px;color:rgb(var(--c-text))}.t:hover{color:rgb(var(--c-primary))}
.ex{color:rgb(var(--c-muted));font-size:14px;margin:6px 0 0}
.rm{flex:none;border:1px solid rgb(var(--c-border));background:transparent;color:rgb(var(--c-muted));border-radius:8px;width:30px;height:30px;cursor:pointer}
.rm:hover{border-color:#f43f5e;color:#f43f5e}
.empty{padding:60px;text-align:center;color:rgb(var(--c-muted));border:1px dashed rgb(var(--c-border));border-radius:var(--c-radius,12px)}
</style></head><body>
<div class="bar"><b>{$name}</b><span class="sp"></span><a class="home" href="/">← Community</a></div>
<div class="wrap"><h1>🔖 Bookmarks</h1><p class="sub">Posts you've saved to read later.</p>
<div id="list">{$items}</div></div>
<script>
var csrf=document.querySelector('meta[name=csrf-token]').content;
document.querySelectorAll('.rm').forEach(function(b){b.addEventListener('click',function(){
  fetch('/api/ext/bookmarks/post/'+b.dataset.post,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'}})
    .then(function(){ var el=b.closest('.bm'); if(el) el.remove(); if(!document.querySelectorAll('.bm').length){document.getElementById('list').innerHTML='<div class="empty">No bookmarks yet.</div>';} });
});});
</script></body></html>
HTML;
    }
}
