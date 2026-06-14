<?php

namespace Convoro\Ext\Leaderboard;

use App\Models\User;
use App\Support\ExtPage;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Leaderboard — first-party Convoro extension.
 *
 * Ranks members by reputation (posts + topics + reactions received) over a
 * selectable time window on a themed /leaderboard page: a podium for the top 3,
 * a card grid for the next seven contenders, and a ranked honorable-mentions
 * list for the rest. Opt-in, so core stays lean.
 */
class Extension extends ServiceProvider
{
    /** Time-window tabs: query key => label. */
    private const PERIODS = [
        'all' => 'All Time', 'year' => 'Yearly', 'quarter' => 'Quarterly',
        'month' => 'Monthly', 'week' => 'Weekly', 'day' => 'Daily',
    ];

    public function boot(): void
    {
        Route::middleware('web')->get('/leaderboard', function (Request $request) {
            $period = (string) $request->query('period', 'all');
            if (! isset(self::PERIODS[$period])) {
                $period = 'all';
            }

            return self::page($period);
        });
    }

    /** Start of the selected window, or null for all-time. */
    private static function since(string $period): ?Carbon
    {
        return match ($period) {
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subMonths(3),
            'year' => now()->subYear(),
            default => null,
        };
    }

    /**
     * Top members by reputation within the window.
     * score = posts + topics + reactions received ×2 (all counted in-window).
     */
    private static function ranked(string $period): array
    {
        $since = self::since($period);

        $posts = DB::table('posts')->whereNotNull('user_id')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->select('user_id', DB::raw('count(*) as c'))->groupBy('user_id')->pluck('c', 'user_id');

        $topics = DB::table('topics')->whereNotNull('user_id')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->select('user_id', DB::raw('count(*) as c'))->groupBy('user_id')->pluck('c', 'user_id');

        $reactions = DB::table('reactions')
            ->join('posts', 'posts.id', '=', 'reactions.post_id')
            ->when($since, fn ($q) => $q->where('reactions.created_at', '>=', $since))
            ->select('posts.user_id', DB::raw('count(*) as c'))->groupBy('posts.user_id')->pluck('c', 'user_id');

        $ids = collect($posts->keys())->merge($topics->keys())->merge($reactions->keys())->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $users = User::query()->whereIn('id', $ids)->get()->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $u = $users->get($id);
            if (! $u) {
                continue;
            }
            $p = (int) ($posts[$id] ?? 0);
            $t = (int) ($topics[$id] ?? 0);
            $rx = (int) ($reactions[$id] ?? 0);
            $score = $p + $t + $rx * 2;
            if ($score <= 0) {
                continue;
            }
            $out[] = ['user' => $u, 'posts' => $p, 'topics' => $t, 'reactions' => $rx, 'score' => $score];
        }

        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($out, 0, 50);
    }

    private static function page(string $period)
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $grad = [
            'linear-gradient(135deg,#f472b6,#db2777)', 'linear-gradient(135deg,#60a5fa,#2563eb)',
            'linear-gradient(135deg,#34d399,#059669)', 'linear-gradient(135deg,#fbbf24,#d97706)',
            'linear-gradient(135deg,#a78bfa,#7c3aed)', 'linear-gradient(135deg,#f87171,#dc2626)',
        ];

        // Avatar (img or initials) at a given pixel size, plus the escaped name.
        $present = function (array $r, int $size) use ($e, $grad) {
            $a = Present::avatar($r['user']);
            $st = 'width:'.$size.'px;height:'.$size.'px';
            if (! empty($a['avatar'])) {
                $src = str_starts_with((string) $a['avatar'], 'http') ? $a['avatar'] : '/'.ltrim((string) $a['avatar'], '/');
                $av = '<img class="av" style="'.$st.'" src="'.$e($src).'" alt="">';
            } else {
                $bg = $grad[(((int) ($a['color'] ?? 1)) - 1) % 6];
                $av = '<span class="av init" style="'.$st.';font-size:'.(int) ($size / 2.6).'px;background:'.$bg.'">'.$e($a['initials'] ?? '?').'</span>';
            }

            return ['av' => $av, 'name' => $e($a['name'] ?? $r['user']->name)];
        };

        $ranked = self::ranked($period);

        // Period filter tabs.
        $tabs = '';
        foreach (self::PERIODS as $k => $label) {
            $tabs .= '<a class="tab'.($k === $period ? ' on' : '').'" href="/leaderboard?period='.$k.'">'.$e($label).'</a>';
        }

        if (empty($ranked)) {
            $body = '<div class="empty">No activity to rank in this period yet. Check back once members start posting.</div>';
        } else {
            $podium = array_slice($ranked, 0, 3);
            $contenders = array_slice($ranked, 3, 7);
            $mentions = array_slice($ranked, 10);

            // ── Podium (#1 centre, raised; #2 left; #3 right) ──
            $medals = [0 => '🥇', 1 => '🥈', 2 => '🥉'];
            $podiumItem = function (int $idx) use ($podium, $present, $medals) {
                if (! isset($podium[$idx])) {
                    return '';
                }
                $r = $podium[$idx];
                $pr = $present($r, $idx === 0 ? 96 : 80);

                return '<a class="podium p'.($idx + 1).'" href="/u/'.$r['user']->id.'">'
                    .'<span class="crown">'.$medals[$idx].'</span>'
                    .'<span class="avwrap">'.$pr['av'].'</span>'
                    .'<span class="pname">'.$pr['name'].'</span>'
                    .'<span class="pscore">'.number_format($r['score']).' <small>pts</small></span>'
                    .'<span class="pstat">'.$r['posts'].' posts · '.$r['reactions'].' reactions</span>'
                    .'</a>';
            };
            $podiumHtml = '<div class="podium-wrap">'.$podiumItem(1).$podiumItem(0).$podiumItem(2).'</div>';

            // ── Contenders #4–#10 ──
            $contHtml = '';
            foreach ($contenders as $i => $r) {
                $pr = $present($r, 44);
                $contHtml .= '<a class="cont" href="/u/'.$r['user']->id.'">'
                    .'<span class="rk">'.($i + 4).'</span>'.$pr['av']
                    .'<span class="cinfo"><span class="cn">'.$pr['name'].'</span>'
                    .'<span class="cs">'.$r['posts'].' posts · '.$r['reactions'].' reactions</span></span>'
                    .'<span class="cpts">'.number_format($r['score']).'</span></a>';
            }
            $contSec = $contHtml !== ''
                ? '<h2 class="sec-title">Top contenders</h2><div class="cont-grid">'.$contHtml.'</div>'
                : '';

            // ── Honorable mentions #11+ ──
            $hmHtml = '';
            foreach ($mentions as $i => $r) {
                $pr = $present($r, 34);
                $hmHtml .= '<a class="hm" href="/u/'.$r['user']->id.'">'
                    .'<span class="rk">'.($i + 11).'</span>'.$pr['av']
                    .'<span class="nm">'.$pr['name'].'</span>'
                    .'<span class="meta">'.$r['posts'].' posts · '.$r['topics'].' topics · '.$r['reactions'].' reactions</span>'
                    .'<span class="sc">'.number_format($r['score']).'</span></a>';
            }
            $hmSec = $hmHtml !== ''
                ? '<h2 class="sec-title">Honorable mentions</h2><div class="card">'.$hmHtml.'</div>'
                : '';

            $body = $podiumHtml.$contSec.$hmSec;
        }

        $css = <<<'CSS'
.ext-frame a{color:inherit;text-decoration:none}
.wrap{max-width:920px;margin:0 auto}
h1{font-size:28px;margin:0 0 4px;letter-spacing:-.02em}.sub{color:rgb(var(--c-muted));margin:0 0 22px}
.tabs{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 30px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;padding:6px}
.tab{padding:9px 16px;border-radius:9px;font-size:13px;font-weight:700;color:rgb(var(--c-text-2));transition:background .12s}
.tab:hover{background:rgb(var(--c-surface-2))}
.tab.on{background:rgb(var(--c-primary));color:#fff;box-shadow:0 4px 14px rgb(var(--c-primary)/.35)}
.av{border-radius:var(--c-avatar-radius,9999px);object-fit:cover;flex:none}
.av.init{display:grid;place-items:center;color:#fff;font-weight:800}
.sec-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:rgb(var(--c-muted));margin:0 0 14px}
/* Podium */
.podium-wrap{display:flex;justify-content:center;align-items:flex-end;gap:16px;margin:6px 0 40px}
.podium{position:relative;display:flex;flex-direction:column;align-items:center;gap:5px;width:210px;padding:26px 18px 20px;text-align:center;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:20px;transition:transform .15s,box-shadow .15s}
.podium:hover{transform:translateY(-3px)}
.podium .crown{font-size:30px;line-height:1;margin-bottom:2px}
.podium .avwrap{display:grid;place-items:center}
.podium .pname{font-weight:800;font-size:16px;color:rgb(var(--c-text));max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.podium .pscore{font-weight:800;font-size:19px;color:rgb(var(--c-primary))}.podium .pscore small{font-size:11px;font-weight:700;color:rgb(var(--c-muted))}
.podium .pstat{font-size:12px;color:rgb(var(--c-muted))}
.podium.p1{order:2;transform:translateY(-18px);width:228px;padding-top:30px;border-color:#f5c518;background:linear-gradient(180deg,rgb(245 197 24/.12),rgb(var(--c-surface)) 60%);box-shadow:0 14px 44px rgb(245 197 24/.22)}
.podium.p1:hover{transform:translateY(-22px)}
.podium.p1 .crown{font-size:40px}.podium.p1 .pname{font-size:18px}.podium.p1 .pscore{font-size:24px}
.podium.p1 .av{outline:3px solid #f5c518;outline-offset:3px}
.podium.p2{order:1;border-color:#c4cad6}.podium.p2 .av{outline:3px solid #c4cad6;outline-offset:3px}
.podium.p3{order:3;border-color:#e0a978}.podium.p3 .av{outline:3px solid #e0a978;outline-offset:3px}
/* Contenders */
.cont-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px;margin-bottom:38px}
.cont{display:flex;align-items:center;gap:12px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;padding:12px 14px;transition:border-color .12s,transform .12s}
.cont:hover{border-color:rgb(var(--c-primary));transform:translateY(-2px)}
.cont .rk{flex:none;width:28px;height:28px;border-radius:9px;display:grid;place-items:center;font-weight:800;font-size:13px;background:rgb(var(--c-surface-2));color:rgb(var(--c-text-2))}
.cont .cinfo{min-width:0;flex:1;display:flex;flex-direction:column;gap:1px}
.cont .cn{font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cont .cs{font-size:12px;color:rgb(var(--c-muted))}
.cont .cpts{flex:none;font-weight:800;color:rgb(var(--c-primary));font-size:15px}
/* Honorable mentions */
.card{background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;overflow:hidden}
.hm{display:flex;align-items:center;gap:13px;padding:11px 16px;border-bottom:1px solid rgb(var(--c-border));transition:background .12s}
.hm:last-child{border-bottom:0}.hm:hover{background:rgb(var(--c-surface-2))}
.hm .rk{flex:none;width:28px;text-align:center;font-weight:800;color:rgb(var(--c-muted));font-size:14px}
.hm .nm{font-weight:700;font-size:14px;flex:none;max-width:40%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hm .meta{flex:1;color:rgb(var(--c-muted));font-size:12.5px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right}
.hm .sc{flex:none;font-weight:800;color:rgb(var(--c-primary));min-width:48px;text-align:right}
.empty{padding:64px 24px;text-align:center;color:rgb(var(--c-muted));background:rgb(var(--c-surface));border:1px dashed rgb(var(--c-border));border-radius:16px}
@media(max-width:640px){
 .podium-wrap{gap:8px}.podium{width:auto;flex:1;padding:18px 8px 14px;border-radius:16px}.podium.p1{width:auto}
 .podium .pname{font-size:13px}.podium.p1 .pname{font-size:14px}
 .podium .pstat,.hm .meta{display:none}
 .hm .nm{max-width:none;flex:1}
}
CSS;

        $bodyHtml = '<div class="wrap">'
            .'<h1>🏆 Leaderboard</h1><p class="sub">Top contributors by reputation — posts, topics and reactions received.</p>'
            .'<div class="tabs">'.$tabs.'</div>'.$body.'</div>';

        return ExtPage::render('Leaderboard', $bodyHtml, $css);
    }
}
