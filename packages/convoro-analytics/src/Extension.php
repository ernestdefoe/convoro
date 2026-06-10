<?php

namespace Convoro\Ext\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Admin analytics dashboard — a self-contained page (no Inertia) served from
 * the extension, reading the core tables. Linked from the Marketplace card.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'admin'])->get('/admin/ext/analytics', fn () => response(self::dashboard()));
    }

    private static function dashboard(): string
    {
        $users = DB::table('users')->count();
        $topics = DB::table('topics')->count();
        $posts = DB::table('posts')->count();
        $reactions = DB::table('reactions')->count();
        $online = DB::table('users')->where('last_seen_at', '>=', now()->subMinutes(5))->count();

        // New signups per day, last 14 days.
        $since = now()->subDays(13)->startOfDay();
        $rows = DB::table('users')->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        $days = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $days[$d] = (int) ($rows[$d] ?? 0);
        }
        $max = max(1, max($days));

        $bars = '';
        foreach ($days as $d => $c) {
            $h = (int) round(($c / $max) * 90) + 2;
            $label = Carbon::parse($d)->format('M j');
            $bars .= '<div class="bar"><div class="fill" style="height:'.$h.'px" title="'.$label.': '.$c.'"></div><span>'.Carbon::parse($d)->format('j').'</span></div>';
        }

        $topPosters = DB::table('posts')
            ->join('users', 'users.id', '=', 'posts.user_id')
            ->select('users.name', DB::raw('COUNT(*) c'))
            ->groupBy('users.id', 'users.name')->orderByDesc('c')->limit(5)->get();

        $activeTopics = DB::table('topics')->orderByDesc('reply_count')->limit(5)->get(['title', 'slug', 'reply_count', 'view_count']);

        $stat = fn ($n, $l) => '<div class="card stat"><b>'.number_format($n).'</b><span>'.$l.'</span></div>';
        $statsHtml = $stat($users, 'Members').$stat($online, 'Online now').$stat($topics, 'Topics').$stat($posts, 'Posts').$stat($reactions, 'Reactions');
        $posterRows = $topPosters->map(fn ($p) => '<li><span>'.e($p->name).'</span><b>'.$p->c.'</b></li>')->implode('') ?: '<li class="muted">No posts yet</li>';
        $topicRows = $activeTopics->map(fn ($t) => '<li><a href="/t/'.e($t->slug).'">'.e($t->title).'</a><b>'.$t->reply_count.' replies</b></li>')->implode('') ?: '<li class="muted">No topics yet</li>';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Analytics · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:920px;margin:0 auto;padding:36px 20px}a{color:#8b8bf0}
.top{display:flex;align-items:center;margin-bottom:20px}.top h1{font-size:24px;margin:0}.top .sp{flex:1}
.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px}
@media(max-width:720px){.grid{grid-template-columns:repeat(2,1fr)}}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:18px}
.stat b{display:block;font-size:26px;font-weight:800}.stat span{font-size:12px;color:#9aa0b8}
.chart{display:flex;align-items:flex-end;gap:6px;height:120px;padding-top:8px}
.bar{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px}
.bar .fill{width:100%;max-width:26px;background:linear-gradient(180deg,#6366f1,#8b5cf6);border-radius:5px 5px 0 0}
.bar span{font-size:10px;color:#6b7194}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
@media(max-width:720px){.cols{grid-template-columns:1fr}}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:#9aa0b8;margin:0 0 10px}
ul{list-style:none;margin:0;padding:0}li{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:14px}
li:last-child{border-bottom:0}li b{color:#c7cbe0;font-weight:700;white-space:nowrap}.muted{color:#6b7194}
</style></head><body><div class="wrap">
<div class="top"><h1>Analytics</h1><div class="sp"></div><a href="/admin/marketplace">← Marketplace</a></div>
<div class="grid">{$statsHtml}</div>
<div class="card"><h2>New members · last 14 days</h2><div class="chart">{$bars}</div></div>
<div class="cols">
  <div class="card"><h2>Top posters</h2><ul>{$posterRows}</ul></div>
  <div class="card"><h2>Most active topics</h2><ul>{$topicRows}</ul></div>
</div>
</div></body></html>
HTML;
    }
}
