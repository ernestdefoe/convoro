<?php

namespace Convoro\Ext\Leaderboard;

use App\Models\User;
use App\Support\Present;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Leaderboard — first-party Convoro extension.
 *
 * Ranks members by reputation (posts + topics + reactions received) on a themed
 * /leaderboard page. Opt-in: communities that don't want it simply don't install
 * it, so core stays lean.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('/leaderboard', fn () => response(self::page()));
    }

    /** Top 50 members by reputation = posts + topics + reactions received ×2. */
    private static function ranked(): array
    {
        $reactions = DB::table('reactions')
            ->join('posts', 'posts.id', '=', 'reactions.post_id')
            ->select('posts.user_id', DB::raw('count(*) as c'))
            ->groupBy('posts.user_id')->pluck('c', 'user_id');

        return User::query()->withCount(['posts', 'topics'])->get()
            ->map(function (User $u) use ($reactions) {
                $received = (int) ($reactions[$u->id] ?? 0);

                return [
                    'user' => $u,
                    'posts' => (int) $u->posts_count,
                    'topics' => (int) $u->topics_count,
                    'reactions' => $received,
                    'score' => (int) $u->posts_count + $received * 2 + (int) $u->topics_count,
                ];
            })
            ->sortByDesc('score')->take(50)->values()->all();
    }

    private static function page(): string
    {
        $theme = Theme::css();
        $font = Theme::fontStack((string) Settings::get('theme.font', 'Inter'));
        $mode = htmlspecialchars((string) Settings::get('theme.mode', 'light'), ENT_QUOTES);
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $grad = [
            'linear-gradient(135deg,#f472b6,#db2777)', 'linear-gradient(135deg,#60a5fa,#2563eb)',
            'linear-gradient(135deg,#34d399,#059669)', 'linear-gradient(135deg,#fbbf24,#d97706)',
            'linear-gradient(135deg,#a78bfa,#7c3aed)', 'linear-gradient(135deg,#f87171,#dc2626)',
        ];

        $rows = '';
        foreach (self::ranked() as $i => $r) {
            $a = Present::avatar($r['user']);
            $rank = $i + 1;
            $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : (string) $rank));
            $av = ! empty($a['avatar'])
                ? '<img class="av" src="'.$e(str_starts_with($a['avatar'], 'http') ? $a['avatar'] : '/'.ltrim($a['avatar'], '/')).'" alt="">'
                : '<span class="av init" style="background:'.$grad[(((int) ($a['color'] ?? 1)) - 1) % 6].'">'.$e($a['initials'] ?? '?').'</span>';
            $rows .= '<a class="row" href="/u/'.$r['user']->id.'">'
                .'<span class="rank'.($rank <= 3 ? ' top' : '').'">'.$medal.'</span>'
                .$av
                .'<span class="nm">'.$e($a['name'] ?? $r['user']->name).'</span>'
                .'<span class="meta">'.$r['posts'].' posts · '.$r['topics'].' topics · '.$r['reactions'].' reactions</span>'
                .'<span class="score">'.$r['score'].'</span></a>';
        }
        if ($rows === '') {
            $rows = '<div class="empty">No members to rank yet.</div>';
        }

        return <<<HTML
<!DOCTYPE html><html lang="en" data-theme="{$mode}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leaderboard · {$name}</title>
<style>{$theme}
:root,html[data-theme="light"]{--c-bg:243 244 249;--c-surface:255 255 255;--c-surface-2:248 249 252;--c-border:230 232 240;--c-text:27 32 48;--c-text-2:74 81 104;--c-muted:138 144 166}
html[data-theme="dark"]{--c-bg:16 18 30;--c-surface:22 25 41;--c-surface-2:28 32 52;--c-border:42 47 70;--c-text:233 235 243;--c-text-2:174 180 208;--c-muted:120 127 152}
*{box-sizing:border-box}body{margin:0;font-family:{$font};background:rgb(var(--c-bg));color:rgb(var(--c-text))}
a{color:inherit;text-decoration:none}
.bar{display:flex;align-items:center;gap:12px;padding:14px 24px;border-bottom:1px solid rgb(var(--c-border));background:rgb(var(--c-surface))}
.bar b{font-weight:800}.bar .sp{flex:1}.bar .home{color:rgb(var(--c-primary));font-weight:700}
.wrap{max-width:680px;margin:0 auto;padding:32px 20px}
h1{font-size:26px;margin:0 0 4px}.sub{color:rgb(var(--c-muted));margin:0 0 24px}
.card{background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);overflow:hidden}
.row{display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid rgb(var(--c-border));transition:background .12s}
.row:last-child{border-bottom:0}.row:hover{background:rgb(var(--c-surface-2))}
.rank{flex:none;width:34px;text-align:center;font-weight:800;color:rgb(var(--c-muted));font-size:15px}.rank.top{font-size:20px}
.av{flex:none;width:40px;height:40px;border-radius:var(--c-avatar-radius,9999px);object-fit:cover}
.av.init{display:grid;place-items:center;color:#fff;font-weight:800;font-size:15px}
.nm{font-weight:700;flex:none}.meta{flex:1;color:rgb(var(--c-muted));font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.score{flex:none;font-weight:800;color:rgb(var(--c-primary))}
.empty{padding:60px;text-align:center;color:rgb(var(--c-muted))}
@media(max-width:560px){.meta{display:none}}
</style></head><body>
<div class="bar"><b>{$name}</b><span class="sp"></span><a class="home" href="/">← Community</a></div>
<div class="wrap">
<h1>🏆 Leaderboard</h1><p class="sub">Top contributors by reputation — posts, topics and reactions received.</p>
<div class="card">{$rows}</div>
</div></body></html>
HTML;
    }
}
