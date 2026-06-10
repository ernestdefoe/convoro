<?php

namespace Convoro\Ext\Calendar;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Events — first-party Convoro extension.
 *
 * Community events with RSVPs and iCal export. Members create events; others
 * RSVP going/maybe. A themed /events page lists upcoming events, with an
 * upcoming-events forum sidebar widget and admin moderation.
 */
class Extension extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/events', fn (Request $r) => response(self::page($r)));
            Route::get('/events/{id}.ics', fn (int $id) => self::ical($id));

            Route::get('/api/ext/events/upcoming', function () {
                $rows = DB::table('events')->where('starts_at', '>=', now()->subHours(6))
                    ->orderBy('starts_at')->limit(5)
                    ->get(['id', 'title', 'starts_at', 'location']);

                return response()->json(['events' => $rows]);
            });
        });

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/events', function (Request $request) {
                $data = $request->validate([
                    'title' => ['required', 'string', 'max:160'],
                    'description' => ['nullable', 'string', 'max:4000'],
                    'location' => ['nullable', 'string', 'max:200'],
                    'url' => ['nullable', 'url', 'max:300'],
                    'starts_at' => ['required', 'date'],
                    'ends_at' => ['nullable', 'date', 'after:starts_at'],
                ]);
                DB::table('events')->insert([
                    'user_id' => $request->user()->id,
                    'title' => $data['title'], 'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null, 'url' => $data['url'] ?? null,
                    'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return redirect('/events');
            });

            Route::post('/api/ext/events/{id}/rsvp', function (Request $request, int $id) {
                $data = $request->validate(['status' => ['required', 'in:going,maybe,none']]);
                abort_unless(DB::table('events')->where('id', $id)->exists(), 404);

                if ($data['status'] === 'none') {
                    DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $request->user()->id)->delete();
                } else {
                    DB::table('event_rsvps')->updateOrInsert(
                        ['event_id' => $id, 'user_id' => $request->user()->id],
                        ['status' => $data['status'], 'created_at' => now()],
                    );
                }

                return response()->json([
                    'going' => DB::table('event_rsvps')->where('event_id', $id)->where('status', 'going')->count(),
                    'maybe' => DB::table('event_rsvps')->where('event_id', $id)->where('status', 'maybe')->count(),
                    'mine' => $data['status'] === 'none' ? null : $data['status'],
                ]);
            });

            Route::delete('/events/{id}', function (Request $request, int $id) {
                $ev = DB::table('events')->find($id);
                abort_if(! $ev, 404);
                abort_unless($ev->user_id === $request->user()->id || $request->user()->is_admin, 403);
                DB::table('events')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
        });

        Route::middleware(['web', 'auth', 'admin'])->get('/admin/ext/events', fn () => response(self::adminPage()));
    }

    /** Per-event iCal download. */
    public static function ical(int $id)
    {
        $ev = DB::table('events')->find($id);
        abort_if(! $ev, 404);

        $fmt = fn ($t) => Carbon::parse($t)->utc()->format('Ymd\THis\Z');
        $esc = fn ($s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', (string) $s), ",;\\");
        $start = $fmt($ev->starts_at);
        $end = $fmt($ev->ends_at ?: Carbon::parse($ev->starts_at)->addHour());
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'convoro';
        $summary = $esc($ev->title);
        $organizer = $esc(Settings::get('site.name', 'Convoro'));
        $location = $ev->location ? 'LOCATION:'.$esc($ev->location)."\r\n" : '';
        $description = $ev->description ? 'DESCRIPTION:'.$esc($ev->description)."\r\n" : '';

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Convoro//Events//EN\r\nBEGIN:VEVENT\r\n"
            ."UID:convoro-event-{$ev->id}@{$host}\r\n"
            ."DTSTAMP:{$start}\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\n"
            ."SUMMARY:{$summary}\r\n"
            .$location
            .$description
            ."ORGANIZER;CN={$organizer}:MAILTO:noreply@{$host}\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-'.$ev->id.'.ics"',
        ]);
    }

    private static function page(Request $request): string
    {
        $user = Auth::user();
        $csrf = csrf_token();
        $theme = \App\Support\Theme::css();
        $font = \App\Support\Theme::fontStack((string) Settings::get('theme.font', 'Inter'));
        $name = htmlspecialchars((string) Settings::get('site.name', 'Convoro'), ENT_QUOTES);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);

        $rows = DB::table('events')->join('users', 'users.id', '=', 'events.user_id')
            ->where('starts_at', '>=', now()->subHours(6))
            ->orderBy('starts_at')->limit(100)
            ->get(['events.*', 'users.name as organizer']);

        $myRsvps = $user
            ? DB::table('event_rsvps')->where('user_id', $user->id)->pluck('status', 'event_id')->all()
            : [];

        $cards = '';
        foreach ($rows as $ev) {
            $start = Carbon::parse($ev->starts_at);
            $going = DB::table('event_rsvps')->where('event_id', $ev->id)->where('status', 'going')->count();
            $maybe = DB::table('event_rsvps')->where('event_id', $ev->id)->where('status', 'maybe')->count();
            $mine = $myRsvps[$ev->id] ?? '';
            $canDel = $user && ($ev->user_id === $user->id || $user->is_admin);
            $del = $canDel ? '<button class="del" data-id="'.$ev->id.'" title="Delete">✕</button>' : '';
            $rsvp = $user
                ? '<div class="rsvp" data-id="'.$ev->id.'"><button class="r going'.($mine === 'going' ? ' on' : '').'" data-s="going">Going</button>'
                    .'<button class="r maybe'.($mine === 'maybe' ? ' on' : '').'" data-s="maybe">Maybe</button></div>'
                : '<a class="r" href="/">Log in to RSVP</a>';

            $cards .= '<div class="ev">'.$del
                .'<div class="date"><span class="mo">'.$start->format('M').'</span><span class="day">'.$start->format('j').'</span></div>'
                .'<div class="body"><div class="t">'.$e($ev->title).'</div>'
                .'<div class="meta">'.$start->format('D, M j · g:ia')
                .($ev->location ? ' · '.$e($ev->location) : '').' · by '.$e($ev->organizer).'</div>'
                .($ev->description ? '<p class="desc">'.nl2br($e($ev->description)).'</p>' : '')
                .'<div class="foot">'.$rsvp
                .'<span class="counts">'.$going.' going'.($maybe ? ' · '.$maybe.' maybe' : '').'</span>'
                .'<a class="ics" href="/events/'.$ev->id.'.ics">＋ iCal</a>'
                .($ev->url ? '<a class="visit" href="'.$e($ev->url).'" target="_blank" rel="noopener nofollow">Details ↗</a>' : '')
                .'</div></div></div>';
        }
        if (! $cards) {
            $cards = '<div class="empty">No upcoming events yet.</div>';
        }

        $form = '';
        if ($user) {
            $form = <<<FORM
<details class="submit"><summary>+ Create an event</summary>
<div class="fcard">
  <label>Title</label><input id="e_title" maxlength="160">
  <div class="two"><div><label>Starts</label><input id="e_start" type="datetime-local"></div>
  <div><label>Ends (optional)</label><input id="e_end" type="datetime-local"></div></div>
  <label>Location (optional)</label><input id="e_loc">
  <label>Link (optional)</label><input id="e_url" placeholder="https://">
  <label>Description</label><textarea id="e_desc" rows="3"></textarea>
  <div style="margin-top:12px"><button class="btn" id="e_submit">Create event</button><span id="e_msg" class="msg"></span></div>
</div></details>
FORM;
        }

        return <<<HTML
<!DOCTYPE html><html lang="en" data-theme="{$e(Settings::get('theme.mode', 'light'))}"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Events · {$name}</title>
<style>{$theme}
*{box-sizing:border-box}body{margin:0;font-family:{$font};background:rgb(var(--c-bg));color:rgb(var(--c-text))}
a{color:rgb(var(--c-primary))}
.bar{position:sticky;top:0;display:flex;align-items:center;gap:14px;padding:14px 20px;background:rgb(var(--c-surface));border-bottom:1px solid rgb(var(--c-border));z-index:10}
.bar b{font-weight:800}.bar .sp{flex:1}
.wrap{max-width:760px;margin:0 auto;padding:32px 20px}
h1{font-size:28px;margin:0 0 4px}.sub{color:rgb(var(--c-muted));margin:0 0 24px}
.ev{position:relative;display:flex;gap:16px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);padding:16px;margin-bottom:14px}
.date{flex:none;width:58px;text-align:center;background:rgb(var(--c-primary) / .10);border-radius:10px;padding:8px 0;height:fit-content}
.date .mo{display:block;font-size:11px;font-weight:800;text-transform:uppercase;color:rgb(var(--c-primary-700,66 66 181))}
.date .day{display:block;font-size:22px;font-weight:800;color:rgb(var(--c-text))}
.body{min-width:0;flex:1}.t{font-weight:800;font-size:17px}.meta{color:rgb(var(--c-muted));font-size:13px;margin-top:2px}
.desc{color:rgb(var(--c-text-2));font-size:14px;margin:10px 0 0}
.foot{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:14px}
.rsvp{display:inline-flex;border:1px solid rgb(var(--c-border));border-radius:9px;overflow:hidden}
.r{border:0;background:transparent;padding:7px 14px;font-weight:700;font-size:13px;cursor:pointer;color:rgb(var(--c-text-2))}
.r.on{background:rgb(var(--c-primary));color:#fff}
.counts{color:rgb(var(--c-muted));font-size:13px}.ics,.visit{font-size:13px;font-weight:700}.visit{margin-left:auto}
.del{position:absolute;right:10px;top:10px;border:0;border-radius:50%;width:26px;height:26px;cursor:pointer;background:rgb(var(--c-surface-2));color:rgb(var(--c-muted))}
.empty{padding:60px;text-align:center;color:rgb(var(--c-muted));border:1px dashed rgb(var(--c-border));border-radius:var(--c-radius,12px)}
.submit{margin:0 0 22px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:var(--c-radius,12px);padding:8px 16px}
.submit summary{cursor:pointer;font-weight:700;padding:8px 0}.fcard label{display:block;font-size:13px;color:rgb(var(--c-text-2));margin:10px 0 4px}
.fcard input,.fcard textarea{width:100%;background:rgb(var(--c-bg));border:1px solid rgb(var(--c-border));border-radius:9px;color:rgb(var(--c-text));padding:9px 11px;font:inherit}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{border:0;border-radius:var(--c-radius-btn,9px);padding:10px 18px;font-weight:700;cursor:pointer;background:rgb(var(--c-primary));color:#fff}
.msg{margin-left:10px;color:rgb(var(--c-muted));font-size:13px}
</style></head><body>
<div class="bar"><b>{$name}</b><span class="sp"></span><a href="/">← Community</a></div>
<div class="wrap"><h1>Events</h1><p class="sub">Upcoming community events.</p>
{$form}
{$cards}
</div>
<script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const h={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
document.querySelectorAll('.del').forEach(b=>b.addEventListener('click',async()=>{
  if(!confirm('Delete this event?'))return;
  await fetch('/events/'+b.dataset.id,{method:'DELETE',headers:h});location.reload();
}));
document.querySelectorAll('.rsvp').forEach(box=>{
  const id=box.dataset.id;
  box.querySelectorAll('.r').forEach(btn=>btn.addEventListener('click',async()=>{
    const on=btn.classList.contains('on');
    const status=on?'none':btn.dataset.s;
    const r=await fetch('/api/ext/events/'+id+'/rsvp',{method:'POST',headers:h,body:JSON.stringify({status})});
    if(!r.ok)return;const d=await r.json();
    box.querySelectorAll('.r').forEach(x=>x.classList.toggle('on',d.mine===x.dataset.s));
    const c=box.parentElement.querySelector('.counts');
    if(c)c.textContent=d.going+' going'+(d.maybe?' · '+d.maybe+' maybe':'');
  }));
});
const es=document.getElementById('e_submit');
if(es)es.addEventListener('click',async()=>{
  const v=id=>document.getElementById(id).value;
  const body={title:v('e_title').trim(),starts_at:v('e_start'),ends_at:v('e_end')||null,location:v('e_loc').trim(),url:v('e_url').trim()||null,description:v('e_desc').trim()};
  if(!body.title||!body.starts_at){document.getElementById('e_msg').textContent='Title and start time are required';return;}
  const r=await fetch('/events',{method:'POST',headers:h,body:JSON.stringify(body)});
  if(r.ok||r.redirected)location.href='/events';
});
</script></body></html>
HTML;
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $rows = DB::table('events')->join('users', 'users.id', '=', 'events.user_id')
            ->orderByDesc('events.starts_at')->limit(200)
            ->get(['events.id', 'events.title', 'events.starts_at', 'users.name as organizer']);
        $list = $rows->map(fn ($ev) => '<div class="row"><div class="b"><b>'.htmlspecialchars($ev->title).'</b>'
            .'<div class="tag">'.Carbon::parse($ev->starts_at)->format('M j, Y g:ia').' · by '.htmlspecialchars($ev->organizer).'</div></div>'
            .'<button class="btn danger" onclick="del('.$ev->id.')">Delete</button></div>')->implode('') ?: '<p style="color:#9aa0b8">No events yet.</p>';

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Events · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:720px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 24px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:8px 18px}
.row{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}.row:last-child{border-bottom:0}
.b{flex:1;min-width:0}.tag{font-size:12px;color:#9aa0b8}.btn{border:0;border-radius:9px;padding:8px 14px;font-weight:700;cursor:pointer}.btn.danger{background:transparent;color:#f87171}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.sp{flex:1}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Events</h1><p class="sub">Moderate community events. <a href="/events" target="_blank">View page ↗</a></p></div><span class="sp"></span><a href="/admin/marketplace">← Marketplace</a></div>
<div class="card">{$list}</div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
async function del(id){if(!confirm('Delete this event?'))return;
await fetch('/events/'+id,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});location.reload();}
</script></body></html>
HTML;
    }
}
