<?php

namespace Convoro\Ext\Calendar;

use App\Support\ExtPage;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Events — first-party Convoro extension.
 *
 * A month calendar + list, rich event pages, going/maybe/can't-go RSVPs with
 * optional capacity, attendee lists, add-to-calendar (iCal + Google), automatic
 * reminders, admin-managed coloured categories, a community timezone, event
 * editing, a header nav link and an upcoming-events sidebar widget.
 */
class Extension extends ServiceProvider
{
    private const GRADS = [
        '#f472b6,#db2777', '#60a5fa,#2563eb', '#34d399,#059669',
        '#fbbf24,#d97706', '#a78bfa,#7c3aed', '#f87171,#dc2626',
    ];

    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/events', fn (Request $r) => self::indexPage($r));
            Route::get('/events/{id}.ics', fn (int $id) => self::ical($id))->whereNumber('id');
            Route::get('/events/{id}', fn (int $id) => self::detailPage($id))->whereNumber('id');

            Route::get('/api/ext/events/upcoming', function () {
                $tz = self::tz();
                $now = now()->utc();
                $rows = DB::table('events')
                    ->where(function ($q) use ($now) {
                        $q->where(fn ($q2) => $q2->where('recurrence', 'none')->where('starts_at', '>=', $now->copy()->subHours(3)))
                            ->orWhere(fn ($q2) => $q2->where('recurrence', '!=', 'none')->where(fn ($q3) => $q3->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $now)));
                    })
                    ->limit(60)
                    ->get(['id', 'title', 'starts_at', 'location', 'is_online', 'recurrence', 'recurrence_until']);
                $events = [];
                foreach ($rows as $e) {
                    $next = Recurrence::next(Carbon::parse($e->starts_at, 'UTC'), $e->recurrence ?? 'none', $now, $e->recurrence_until ? Carbon::parse($e->recurrence_until, 'UTC') : null);
                    if (! $next) {
                        continue;
                    }
                    $d = $next->copy()->setTimezone($tz);
                    $events[] = ['id' => $e->id, 'title' => $e->title, 'location' => $e->location, 'is_online' => (bool) $e->is_online,
                        '_ts' => $next->timestamp, 'day' => $d->format('j'), 'mon' => $d->format('M'), 'time' => $d->format('g:ia')];
                }
                usort($events, fn ($a, $b) => $a['_ts'] <=> $b['_ts']);

                return response()->json(['events' => array_slice($events, 0, 6)]);
            });
        });

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('/events', fn (Request $r) => self::save($r, null));
            Route::post('/events/{id}', fn (Request $r, int $id) => self::save($r, $id))->whereNumber('id');

            Route::post('/api/ext/events/{id}/rsvp', function (Request $request, int $id) {
                $data = $request->validate(['status' => ['required', 'in:going,maybe,no,none']]);
                $ev = DB::table('events')->find($id);
                abort_unless($ev, 404);
                $uid = $request->user()->id;
                $current = DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $uid)->value('status');

                if ($data['status'] === 'going' && ! empty($ev->capacity) && $current !== 'going') {
                    $going = DB::table('event_rsvps')->where('event_id', $id)->where('status', 'going')->count();
                    if ($going >= (int) $ev->capacity) {
                        return response()->json(['error' => 'This event is full.'], 422);
                    }
                }

                if ($data['status'] === 'none') {
                    DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $uid)->delete();
                    $mine = null;
                } else {
                    DB::table('event_rsvps')->updateOrInsert(
                        ['event_id' => $id, 'user_id' => $uid],
                        ['status' => $data['status'], 'created_at' => now()],
                    );
                    $mine = $data['status'];
                }

                return response()->json(self::counts($id) + ['mine' => $mine]);
            });

            Route::delete('/events/{id}', function (Request $request, int $id) {
                $ev = DB::table('events')->find($id);
                abort_if(! $ev, 404);
                abort_unless(self::canManage($ev), 403);
                DB::table('event_rsvps')->where('event_id', $id)->delete();
                DB::table('events')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
        });

        // Admin: page + category CRUD + timezone setting.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/events')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));
            Route::post('/categories', function (Request $r) {
                $d = $r->validate(['name' => ['required', 'string', 'max:60'], 'color' => ['nullable', 'string', 'max:9']]);
                DB::table('event_categories')->insert([
                    'name' => $d['name'], 'color' => $d['color'] ?: '#5b5bd6',
                    'position' => (int) DB::table('event_categories')->max('position') + 1, 'created_at' => now(),
                ]);

                return response()->json(['ok' => true]);
            });
            Route::delete('/categories/{id}', function (int $id) {
                DB::table('events')->where('category_id', $id)->update(['category_id' => null]);
                DB::table('event_categories')->where('id', $id)->delete();

                return response()->json(['ok' => true]);
            });
            Route::post('/timezone', function (Request $r) {
                $tz = (string) $r->input('timezone', 'UTC');
                if (in_array($tz, timezone_identifiers_list(), true)) {
                    Settings::set('ext.events.timezone', $tz);
                }

                return response()->json(['ok' => true, 'timezone' => self::tz()]);
            });
        });

        $this->app->booted(function () {
            try {
                $this->app->make(Schedule::class)
                    ->call(fn () => Reminders::run())
                    ->everyFiveMinutes()->name('convoro-event-reminders')->withoutOverlapping();
            } catch (\Throwable $e) {
                // Scheduler unavailable here.
            }
        });
    }

    /** The community's event timezone (admin-set), used for input + display. */
    private static function tz(): string
    {
        $tz = (string) Settings::get('ext.events.timezone', '');

        return $tz !== '' && in_array($tz, timezone_identifiers_list(), true) ? $tz : (string) config('app.timezone', 'UTC');
    }

    private static function canManage(object $ev): bool
    {
        $u = Auth::user();

        return $u && ((int) $ev->user_id === (int) $u->id || $u->is_admin);
    }

    private static function categories(): \Illuminate\Support\Collection
    {
        return DB::table('event_categories')->orderBy('position')->orderBy('id')->get();
    }

    /** Create or update an event (input times are in the community timezone → stored UTC). */
    private static function save(Request $request, ?int $id)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'location' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:300'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_online' => ['boolean'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'category_id' => ['nullable', 'integer'],
            'recurrence' => ['nullable', 'string', 'in:none,daily,weekly,biweekly,monthly'],
            'recurrence_until' => ['nullable', 'date'],
        ]);

        $tz = self::tz();
        $toUtc = fn ($v) => $v ? Carbon::parse($v, $tz)->utc()->toDateTimeString() : null;
        $catId = $data['category_id'] ?? null;
        if ($catId && ! DB::table('event_categories')->where('id', $catId)->exists()) {
            $catId = null;
        }
        $rec = $data['recurrence'] ?? 'none';
        // The "until" date is a calendar day in the community tz → end of that day, UTC.
        $until = ! empty($data['recurrence_until']) && $rec !== 'none'
            ? Carbon::parse($data['recurrence_until'], $tz)->endOfDay()->utc()->toDateTimeString() : null;

        $fields = [
            'category_id' => $catId,
            'title' => $data['title'], 'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null, 'url' => $data['url'] ?? null,
            'is_online' => $request->boolean('is_online'),
            'capacity' => $data['capacity'] ?? null,
            'starts_at' => $toUtc($data['starts_at']), 'ends_at' => $toUtc($data['ends_at'] ?? null),
            'recurrence' => $rec, 'recurrence_until' => $until,
            'updated_at' => now(),
        ];

        if ($id === null) {
            $newId = DB::table('events')->insertGetId($fields + [
                'user_id' => $request->user()->id, 'reminded_day' => false, 'reminded_hour' => false,
                'reminded_day_for' => null, 'reminded_hour_for' => null, 'created_at' => now(),
            ]);

            return response()->json(['id' => $newId]);
        }

        $ev = DB::table('events')->find($id);
        abort_if(! $ev, 404);
        abort_unless(self::canManage($ev), 403);
        // If the schedule changed, let reminders fire again for the new occurrence.
        if ($fields['starts_at'] !== $ev->starts_at || $rec !== ($ev->recurrence ?? 'none')) {
            $fields['reminded_day_for'] = null;
            $fields['reminded_hour_for'] = null;
        }
        DB::table('events')->where('id', $id)->update($fields);

        return response()->json(['id' => $id]);
    }

    private static function counts(int $id): array
    {
        $ev = DB::table('events')->find($id);
        $going = DB::table('event_rsvps')->where('event_id', $id)->where('status', 'going')->count();
        $maybe = DB::table('event_rsvps')->where('event_id', $id)->where('status', 'maybe')->count();

        return [
            'going' => $going, 'maybe' => $maybe,
            'spotsLeft' => ! empty($ev->capacity) ? max(0, (int) $ev->capacity - $going) : null,
            'capacity' => $ev->capacity ? (int) $ev->capacity : null,
        ];
    }

    private static function hue(int $id): int
    {
        return (int) (crc32('convoro-event-'.$id) % 360);
    }

    private static function googleUrl(object $ev): string
    {
        $fmt = fn ($t) => Carbon::parse($t, 'UTC')->format('Ymd\THis\Z');
        $start = $fmt($ev->starts_at);
        $end = $fmt($ev->ends_at ?: Carbon::parse($ev->starts_at, 'UTC')->addHour());

        $params = [
            'action' => 'TEMPLATE', 'text' => $ev->title, 'dates' => $start.'/'.$end,
            'details' => (string) ($ev->description ?? ''), 'location' => (string) ($ev->location ?? ''),
        ];
        $rrule = Recurrence::rrule($ev->recurrence ?? 'none', ! empty($ev->recurrence_until) ? Carbon::parse($ev->recurrence_until, 'UTC') : null);
        if ($rrule) {
            $params['recur'] = 'RRULE:'.$rrule;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    private static function avatar(object $u, int $size = 34): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $st = 'width:'.$size.'px;height:'.$size.'px';
        if (! empty($u->avatar_path)) {
            $src = str_starts_with((string) $u->avatar_path, 'http') ? $u->avatar_path : '/'.ltrim((string) $u->avatar_path, '/');

            return '<img class="av" style="'.$st.'" src="'.$e($src).'" title="'.$e($u->name).'" alt="">';
        }
        $bg = 'linear-gradient(135deg,'.self::GRADS[((int) $u->id) % 6].')';
        $ini = strtoupper(Str::substr(trim((string) $u->name), 0, 1));

        return '<span class="av init" style="'.$st.';font-size:'.(int) ($size / 2.5).'px;background:'.$bg.'" title="'.$e($u->name).'">'.$e($ini ?: '?').'</span>';
    }

    private static function catChip(?object $cat): string
    {
        if (! $cat) {
            return '';
        }
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);

        return '<span class="cat" style="--cc:'.$e($cat->color).'">'.$e($cat->name).'</span>';
    }

    /* ───────────────────────── Index (calendar + list) ───────────────────── */

    private static function indexPage(Request $request)
    {
        $user = Auth::user();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $view = $request->query('view') === 'calendar' ? 'calendar' : 'list';
        $cat = (int) $request->query('cat', 0);
        $cats = self::categories();

        $tabs = '<div class="tabs">'
            .'<a class="tab'.($view === 'list' ? ' on' : '').'" href="/events?view=list'.($cat ? '&cat='.$cat : '').'">📋 List</a>'
            .'<a class="tab'.($view === 'calendar' ? ' on' : '').'" href="/events?view=calendar'.($cat ? '&cat='.$cat : '').'">🗓 Calendar</a></div>';

        $catFilter = '';
        if ($cats->isNotEmpty()) {
            $catFilter = '<div class="catfilter"><a class="cf'.($cat === 0 ? ' on' : '').'" href="/events?view='.$view.'">All</a>';
            foreach ($cats as $c) {
                $catFilter .= '<a class="cf'.($cat === (int) $c->id ? ' on' : '').'" style="--cc:'.$e($c->color).'" href="/events?view='.$view.'&cat='.$c->id.'">'.$e($c->name).'</a>';
            }
            $catFilter .= '</div>';
        }

        $content = $view === 'calendar' ? self::calendarHtml($request, $cat) : self::listHtml($user, $cat);

        $body = '<div class="head"><div><h1>📅 Events</h1><p class="sub">What’s coming up in the community.</p></div>'
            .($user ? '<button class="btn" onclick="openCreate()">+ New event</button>' : '').'</div>'
            .$tabs.$catFilter.$content
            .($user ? self::formModal($cats) : '');

        return self::shell('Events', $body, self::indexCss(), self::pageJs($cats));
    }

    private static function listHtml(?object $user, int $cat): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $tz = self::tz();
        $now = now()->utc();
        // Upcoming one-offs PLUS still-active recurring series (whose base start
        // may be in the past). The next occurrence is computed + sorted in PHP.
        $rows = DB::table('events')->join('users', 'users.id', '=', 'events.user_id')
            ->leftJoin('event_categories', 'event_categories.id', '=', 'events.category_id')
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->where('events.recurrence', 'none')->where('starts_at', '>=', $now->copy()->subHours(3));
                })->orWhere(function ($q2) use ($now) {
                    $q2->where('events.recurrence', '!=', 'none')
                        ->where(fn ($q3) => $q3->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $now));
                });
            })
            ->when($cat, fn ($q) => $q->where('events.category_id', $cat))
            ->limit(200)
            ->get(['events.*', 'users.name as organizer', 'event_categories.name as cat_name', 'event_categories.color as cat_color']);

        $upcoming = [];
        foreach ($rows as $ev) {
            $next = Recurrence::next(
                Carbon::parse($ev->starts_at, 'UTC'), $ev->recurrence ?? 'none', $now,
                $ev->recurrence_until ? Carbon::parse($ev->recurrence_until, 'UTC') : null,
            );
            if (! $next) {
                continue;
            }
            $ev->_next = $next;
            $upcoming[] = $ev;
        }
        usort($upcoming, fn ($a, $b) => $a->_next->timestamp <=> $b->_next->timestamp);
        $upcoming = array_slice($upcoming, 0, 100);

        $myRsvps = $user
            ? DB::table('event_rsvps')->where('user_id', $user->id)->pluck('status', 'event_id')->all()
            : [];

        $cards = '';
        foreach ($upcoming as $ev) {
            $start = $ev->_next->copy()->setTimezone($tz);
            $c = self::counts($ev->id);
            $mine = $myRsvps[$ev->id] ?? '';
            $countsTxt = $c['going'].' going'.($c['maybe'] ? ' · '.$c['maybe'].' maybe' : '').($c['spotsLeft'] !== null ? ' · '.$c['spotsLeft'].' left' : '');
            $rsvp = $user ? self::rsvpButtons($ev->id, $mine, $c) : '';
            $where = $ev->is_online ? '🌐 Online' : ($ev->location ? '📍 '.$e($ev->location) : '');
            $catObj = $ev->cat_name ? (object) ['name' => $ev->cat_name, 'color' => $ev->cat_color] : null;
            $repeat = Recurrence::isRecurring($ev->recurrence ?? 'none') ? '<span class="rep">🔁 '.$e(Recurrence::label($ev->recurrence)).'</span>' : '';

            $cards .= '<div class="ev">'
                .'<div class="date" style="--h:'.self::hue($ev->id).'"><span class="mo">'.$start->format('M').'</span><span class="day">'.$start->format('j').'</span></div>'
                .'<div class="body"><div class="trow"><a class="t" href="/events/'.$ev->id.'">'.$e($ev->title).'</a>'.self::catChip($catObj).$repeat.'</div>'
                .'<div class="meta">'.$start->format('D, M j · g:ia').($where ? ' · '.$where : '').' · by '.$e($ev->organizer).'</div>'
                .($ev->description ? '<p class="desc">'.$e(Str::limit(strip_tags((string) $ev->description), 160)).'</p>' : '')
                .'<div class="foot">'.$rsvp
                .'<span class="counts">'.$countsTxt.'</span>'
                .'<a class="more" href="/events/'.$ev->id.'">Details →</a>'
                .'</div></div></div>';
        }

        return $cards !== '' ? '<div class="list">'.$cards.'</div>'
            : '<div class="empty">No upcoming events yet. Be the first to create one.</div>';
    }

    private static function rsvpButtons(int $id, string $mine, array $c): string
    {
        $full = $c['spotsLeft'] !== null && $c['spotsLeft'] <= 0 && $mine !== 'going';
        $goAttr = $full ? ' disabled title="Event is full"' : '';

        return '<div class="rsvp" data-id="'.$id.'">'
            .'<button class="r going'.($mine === 'going' ? ' on' : '').'" data-s="going"'.$goAttr.'>Going</button>'
            .'<button class="r maybe'.($mine === 'maybe' ? ' on' : '').'" data-s="maybe">Maybe</button>'
            .'<button class="r no'.($mine === 'no' ? ' on' : '').'" data-s="no">Can’t go</button></div>';
    }

    private static function calendarHtml(Request $request, int $cat): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $tz = self::tz();
        $month = (string) $request->query('month', '');
        try {
            $cursor = $month !== '' ? Carbon::createFromFormat('Y-m', $month, $tz)->startOfMonth() : now()->setTimezone($tz)->startOfMonth();
        } catch (\Throwable $ex) {
            $cursor = now()->setTimezone($tz)->startOfMonth();
        }

        $rangeStart = $cursor->copy()->startOfMonth()->utc();
        $rangeEnd = $cursor->copy()->endOfMonth()->utc();
        // One-offs in the month, plus recurring series that started on/before the
        // month's end and haven't ended — their occurrences are expanded below.
        $rows = DB::table('events')->leftJoin('event_categories', 'event_categories.id', '=', 'events.category_id')
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereBetween('starts_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($q2) use ($rangeStart, $rangeEnd) {
                        $q2->where('events.recurrence', '!=', 'none')
                            ->where('starts_at', '<=', $rangeEnd)
                            ->where(fn ($q3) => $q3->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $rangeStart));
                    });
            })
            ->when($cat, fn ($q) => $q->where('events.category_id', $cat))
            ->get(['events.id', 'events.title', 'events.starts_at', 'events.recurrence', 'events.recurrence_until', 'event_categories.color as cat_color']);
        $byDay = [];
        foreach ($rows as $ev) {
            $occs = Recurrence::occurrencesIn(
                Carbon::parse($ev->starts_at, 'UTC'), $ev->recurrence ?? 'none', $rangeStart, $rangeEnd,
                $ev->recurrence_until ? Carbon::parse($ev->recurrence_until, 'UTC') : null,
            );
            foreach ($occs as $occ) {
                $local = $occ->copy()->setTimezone($tz);
                $byDay[(int) $local->day][] = (object) ['id' => $ev->id, 'title' => $ev->title, 'starts_at' => $occ->toDateTimeString(), 'cat_color' => $ev->cat_color];
            }
        }

        $cu = $cat ? '&cat='.$cat : '';
        $prev = $cursor->copy()->subMonth()->format('Y-m');
        $next = $cursor->copy()->addMonth()->format('Y-m');
        $nav = '<div class="calnav"><a class="navbtn" href="/events?view=calendar&month='.$prev.$cu.'">←</a>'
            .'<b>'.$cursor->format('F Y').'</b>'
            .'<a class="navbtn" href="/events?view=calendar&month='.$next.$cu.'">→</a></div>';

        $dows = '';
        foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d) {
            $dows .= '<div class="dow">'.$d.'</div>';
        }

        $cells = '';
        $lead = (int) $cursor->dayOfWeek;
        for ($i = 0; $i < $lead; $i++) {
            $cells .= '<div class="cell out"></div>';
        }
        $today = now()->setTimezone($tz);
        for ($day = 1; $day <= $cursor->daysInMonth; $day++) {
            $isToday = $today->year === $cursor->year && $today->month === $cursor->month && $today->day === $day;
            $chips = '';
            foreach ($byDay[$day] ?? [] as $ev) {
                $t = Carbon::parse($ev->starts_at, 'UTC')->setTimezone($tz)->format('g:ia');
                $color = $ev->cat_color ? 'background:'.$e($ev->cat_color) : 'background:hsl('.self::hue($ev->id).' 70% 52%)';
                $chips .= '<a class="chip" style="'.$color.'" href="/events/'.$ev->id.'" title="'.$e($ev->title).'">'
                    .'<span class="ct">'.$e($t).'</span> '.$e(Str::limit($ev->title, 18)).'</a>';
            }
            $cells .= '<div class="cell'.($isToday ? ' today' : '').'"><span class="dn">'.$day.'</span>'.$chips.'</div>';
        }

        return $nav.'<div class="cal"><div class="dows">'.$dows.'</div><div class="grid">'.$cells.'</div></div>';
    }

    private static function formModal(\Illuminate\Support\Collection $cats): string
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $opts = '<option value="">No category</option>';
        foreach ($cats as $c) {
            $opts .= '<option value="'.$c->id.'">'.$e($c->name).'</option>';
        }
        $tz = $e(self::tz());

        return <<<HTML
<dialog id="create" class="modal">
  <form method="dialog" class="mhead"><b id="f_heading">Create an event</b><button class="x" value="cancel">✕</button></form>
  <div class="fcard">
    <label>Title</label><input id="f_title" maxlength="160" placeholder="Community meetup">
    <label>Category</label><select id="f_cat">{$opts}</select>
    <div class="two"><div><label>Starts</label><input id="f_start" type="datetime-local"></div>
    <div><label>Ends (optional)</label><input id="f_end" type="datetime-local"></div></div>
    <p class="tznote">Times are in <b>{$tz}</b></p>
    <div class="two"><div><label>Repeats</label><select id="f_rec"><option value="none">Does not repeat</option><option value="daily">Every day</option><option value="weekly">Every week</option><option value="biweekly">Every 2 weeks</option><option value="monthly">Every month</option></select></div>
    <div><label>Repeat until (optional)</label><input id="f_until" type="date"></div></div>
    <label class="ck"><input type="checkbox" id="f_online"> This is an online event</label>
    <label>Location / join link label (optional)</label><input id="f_loc" placeholder="Town Hall, or Zoom">
    <label>Event link (optional)</label><input id="f_url" placeholder="https://">
    <div class="two"><div><label>Capacity (optional)</label><input id="f_cap" type="number" min="1" placeholder="Unlimited"></div><div></div></div>
    <label>Description</label><textarea id="f_desc" rows="3" placeholder="What’s it about?"></textarea>
    <div class="frow"><button class="btn" id="f_submit">Save event</button><span id="f_msg" class="msg"></span></div>
  </div>
</dialog>
HTML;
    }

    private static function pageJs(\Illuminate\Support\Collection $cats): string
    {
        return <<<'JS'
var EDIT_ID=null;
function gv(id){var el=document.getElementById(id);return el?el.value:'';}
function sv(id,v){var el=document.getElementById(id);if(el)el.value=(v==null?'':v);}
function openCreate(){EDIT_ID=null;document.getElementById('f_heading').textContent='Create an event';
  sv('f_title','');sv('f_cat','');sv('f_start','');sv('f_end','');sv('f_loc','');sv('f_url','');sv('f_cap','');sv('f_desc','');sv('f_rec','none');sv('f_until','');
  var o=document.getElementById('f_online');if(o)o.checked=false;document.getElementById('f_msg').textContent='';
  document.getElementById('create').showModal();}
function openEdit(ev){EDIT_ID=ev.id;document.getElementById('f_heading').textContent='Edit event';
  sv('f_title',ev.title);sv('f_cat',ev.category_id||'');sv('f_start',ev.starts);sv('f_end',ev.ends);
  sv('f_loc',ev.location);sv('f_url',ev.url);sv('f_cap',ev.capacity);sv('f_desc',ev.description);sv('f_rec',ev.recurrence||'none');sv('f_until',ev.until||'');
  var o=document.getElementById('f_online');if(o)o.checked=!!ev.is_online;document.getElementById('f_msg').textContent='';
  document.getElementById('create').showModal();}
(function(){
  function bindRsvp(box){var id=box.getAttribute('data-id');
    box.querySelectorAll('.r').forEach(function(btn){btn.addEventListener('click',function(){
      var on=btn.classList.contains('on');var status=on?'none':btn.getAttribute('data-s');
      fetch('/api/ext/events/'+id+'/rsvp',{method:'POST',headers:H,body:JSON.stringify({status:status})})
       .then(function(r){return r.ok?r.json():r.json().then(function(d){throw new Error(d.error||'error');});})
       .then(function(d){box.querySelectorAll('.r').forEach(function(x){x.classList.toggle('on',d.mine===x.getAttribute('data-s'));});
         var c=(box.closest('.ev')||box.closest('.side')||document).querySelector('.counts');
         if(c)c.textContent=d.going+' going'+(d.maybe?' · '+d.maybe+' maybe':'')+(d.capacity!=null?' · '+d.spotsLeft+' of '+d.capacity+' spots left':(d.spotsLeft!=null?' · '+d.spotsLeft+' left':''));})
       .catch(function(err){alert(err.message);});
    });});}
  document.querySelectorAll('.rsvp').forEach(bindRsvp);
  document.querySelectorAll('.del').forEach(function(b){b.addEventListener('click',function(){
    if(!confirm('Delete this event?'))return;
    fetch('/events/'+b.getAttribute('data-id'),{method:'DELETE',headers:H}).then(function(){location.href='/events';});
  });});
  var es=document.getElementById('f_submit');
  if(es)es.addEventListener('click',function(){
    var body={title:gv('f_title').trim(),category_id:gv('f_cat')?parseInt(gv('f_cat'),10):null,starts_at:gv('f_start'),ends_at:gv('f_end')||null,
      location:gv('f_loc').trim()||null,url:gv('f_url').trim()||null,description:gv('f_desc').trim()||null,
      is_online:document.getElementById('f_online').checked,capacity:gv('f_cap')?parseInt(gv('f_cap'),10):null,
      recurrence:gv('f_rec')||'none',recurrence_until:gv('f_until')||null};
    if(!body.title||!body.starts_at){document.getElementById('f_msg').textContent='Title and start time are required';return;}
    var url=EDIT_ID?'/events/'+EDIT_ID:'/events';
    fetch(url,{method:'POST',headers:H,body:JSON.stringify(body)})
     .then(function(r){return r.ok?r.json():null;})
     .then(function(d){if(d&&d.id){location.href='/events/'+d.id;}else{document.getElementById('f_msg').textContent='Could not save event';}});
  });
})();
JS;
    }

    /* ───────────────────────────── Detail page ───────────────────────────── */

    private static function detailPage(int $id)
    {
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $tz = self::tz();
        $ev = DB::table('events')->join('users', 'users.id', '=', 'events.user_id')
            ->leftJoin('event_categories', 'event_categories.id', '=', 'events.category_id')
            ->where('events.id', $id)->first(['events.*', 'users.name as organizer', 'event_categories.name as cat_name', 'event_categories.color as cat_color']);
        if (! $ev) {
            return self::shell('Event not found', '<div class="empty">This event no longer exists. <a href="/events">Back to events</a></div>');
        }

        $user = Auth::user();
        // Recurring events show their NEXT upcoming occurrence; the edit form keeps
        // the series' original start.
        $base = Carbon::parse($ev->starts_at, 'UTC');
        $baseLocal = $base->copy()->setTimezone($tz);
        $baseEndLocal = $ev->ends_at ? Carbon::parse($ev->ends_at, 'UTC')->setTimezone($tz) : null;
        $rec = $ev->recurrence ?? 'none';
        $until = $ev->recurrence_until ? Carbon::parse($ev->recurrence_until, 'UTC') : null;
        $nextUtc = Recurrence::next($base, $rec, now()->utc(), $until) ?? $base;
        $start = $nextUtc->copy()->setTimezone($tz);
        $end = null;
        if ($ev->ends_at) {
            $dur = $base->diffInSeconds(Carbon::parse($ev->ends_at, 'UTC'));
            $end = $nextUtc->copy()->addSeconds($dur)->setTimezone($tz);
        }
        $c = self::counts($id);
        $mine = $user ? (string) DB::table('event_rsvps')->where('event_id', $id)->where('user_id', $user->id)->value('status') : '';

        $attendees = DB::table('event_rsvps')->join('users', 'users.id', '=', 'event_rsvps.user_id')
            ->where('event_id', $id)->whereIn('status', ['going', 'maybe'])
            ->orderByRaw("event_rsvps.status = 'going' desc")->orderBy('event_rsvps.created_at')
            ->limit(60)->get(['users.id', 'users.name', 'users.avatar_path', 'event_rsvps.status']);
        $goingAv = '';
        $maybeAv = '';
        foreach ($attendees as $a) {
            $cell = '<span class="att">'.self::avatar($a, 38).'<span class="an">'.$e(Str::limit($a->name, 14)).'</span></span>';
            $a->status === 'going' ? $goingAv .= $cell : $maybeAv .= $cell;
        }

        $when = $start->format('l, F j, Y');
        $time = $start->format('g:ia').($end ? ' – '.$end->format('g:ia') : '').' ('.$tz.')';
        $recLabel = '';
        if (Recurrence::isRecurring($rec)) {
            $recLabel = '🔁 '.$e(Recurrence::label($rec)).($until ? ' · until '.$until->copy()->setTimezone($tz)->format('M j, Y') : '');
        }
        $where = $ev->is_online
            ? '🌐 Online event'
            : ($ev->location ? '📍 '.$e($ev->location).' · <a target="_blank" rel="noopener" href="https://maps.google.com/?q='.urlencode((string) $ev->location).'">Map ↗</a>' : '');

        $rsvp = $user ? self::rsvpButtons($id, $mine, $c) : '<a class="btn" href="/">Sign in to RSVP</a>';
        $countsTxt = $c['going'].' going'.($c['maybe'] ? ' · '.$c['maybe'].' maybe' : '')
            .($c['capacity'] !== null ? ' · '.$c['spotsLeft'].' of '.$c['capacity'].' spots left' : '');

        $catObj = $ev->cat_name ? (object) ['name' => $ev->cat_name, 'color' => $ev->cat_color] : null;
        $manage = self::canManage($ev);
        $editBtn = $manage ? '<button class="cbtn" onclick="openEdit(EV)">✏️ Edit event</button>' : '';
        $del = $manage ? '<button class="del solo" data-id="'.$id.'">🗑 Delete event</button>' : '';
        $linkOut = $ev->url ? '<a class="btn ghost" target="_blank" rel="noopener nofollow" href="'.$e($ev->url).'">Open event link ↗</a>' : '';

        // Event payload for the edit form (datetime in the community tz). The
        // JSON_HEX_* flags make it safe inside a <script> block — do NOT also
        // htmlspecialchars it (entities aren't decoded in raw-text script).
        $evJson = json_encode([
            'id' => $ev->id, 'title' => $ev->title, 'category_id' => $ev->category_id,
            'starts' => $baseLocal->format('Y-m-d\TH:i'), 'ends' => $baseEndLocal ? $baseEndLocal->format('Y-m-d\TH:i') : '',
            'location' => $ev->location, 'url' => $ev->url, 'capacity' => $ev->capacity,
            'description' => $ev->description, 'is_online' => (bool) $ev->is_online,
            'recurrence' => $rec, 'until' => $until ? $until->copy()->setTimezone($tz)->format('Y-m-d') : '',
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $body = '<a class="back" href="/events">← All events</a>'
            .'<div class="hero" style="--h:'.self::hue($id).'"><div class="hdate"><span class="hmo">'.$start->format('M').'</span><span class="hday">'.$start->format('j').'</span></div>'
            .'<div><div class="htop">'.self::catChip($catObj).'</div><h1>'.$e($ev->title).'</h1><div class="hmeta">'.$when.' · '.$time.'</div></div></div>'
            .'<div class="cols"><div class="main">'
            .'<div class="row"><b>When</b><span>'.$when.'<br>'.$time.'</span></div>'
            .($recLabel ? '<div class="row"><b>Repeats</b><span>'.$recLabel.'</span></div>' : '')
            .'<div class="row"><b>Where</b><span>'.($where ?: '—').'</span></div>'
            .'<div class="row"><b>Host</b><span>'.$e($ev->organizer).'</span></div>'
            .($ev->description ? '<div class="about"><b>About</b><p>'.nl2br($e($ev->description)).'</p></div>' : '')
            .($goingAv ? '<div class="atts"><b>Going ('.$c['going'].')</b><div class="avrow">'.$goingAv.'</div></div>' : '')
            .($maybeAv ? '<div class="atts"><b>Maybe ('.$c['maybe'].')</b><div class="avrow">'.$maybeAv.'</div></div>' : '')
            .'</div>'
            .'<aside class="side"><div class="rsvpcard"><div class="rl">RSVP</div>'.$rsvp
            .'<div class="counts">'.$countsTxt.'</div></div>'
            .'<div class="addcal"><div class="rl">Add to calendar</div>'
            .'<a class="cbtn" href="/events/'.$id.'.ics">🍎 Apple / Outlook (.ics)</a>'
            .'<a class="cbtn" target="_blank" rel="noopener" href="'.$e(self::googleUrl($ev)).'">📆 Google Calendar</a></div>'
            .($linkOut ? '<div class="addcal">'.$linkOut.'</div>' : '')
            .($manage ? '<div class="addcal"><div class="rl">Manage event</div>'.$editBtn.$del.'</div>' : '')
            .'</aside></div>'
            .($manage ? self::formModal(self::categories()) : '');

        $js = 'var EV=JSON.parse(document.getElementById("ev-data").textContent);'.self::pageJs(self::categories());
        $body .= '<script type="application/json" id="ev-data">'.$evJson.'</script>';

        return self::shell($ev->title, $body, self::indexCss().self::detailCss(), $js);
    }

    /* ───────────────────────────── iCal ──────────────────────────────────── */

    public static function ical(int $id)
    {
        $ev = DB::table('events')->find($id);
        abort_if(! $ev, 404);

        $fmt = fn ($t) => Carbon::parse($t, 'UTC')->format('Ymd\THis\Z');
        $esc = fn ($s) => addcslashes(str_replace(["\r\n", "\n"], '\\n', (string) $s), ",;\\");
        $start = $fmt($ev->starts_at);
        $end = $fmt($ev->ends_at ?: Carbon::parse($ev->starts_at, 'UTC')->addHour());
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'convoro';
        $location = $ev->location ? 'LOCATION:'.$esc($ev->location)."\r\n" : '';
        $description = $ev->description ? 'DESCRIPTION:'.$esc($ev->description)."\r\n" : '';
        // Native recurrence rule so the whole series imports into the calendar app.
        $rrule = Recurrence::rrule($ev->recurrence ?? 'none', $ev->recurrence_until ? Carbon::parse($ev->recurrence_until, 'UTC') : null);
        $recur = $rrule ? 'RRULE:'.$rrule."\r\n" : '';

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Convoro//Events//EN\r\nBEGIN:VEVENT\r\n"
            ."UID:convoro-event-{$ev->id}@{$host}\r\n"
            ."DTSTAMP:{$start}\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\n".$recur
            ."SUMMARY:".$esc($ev->title)."\r\n".$location.$description
            ."ORGANIZER;CN=".$esc(Settings::get('site.name', 'Convoro')).":MAILTO:noreply@{$host}\r\n"
            ."END:VEVENT\r\nEND:VCALENDAR\r\n";

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event-'.$ev->id.'.ics"',
        ]);
    }

    /* ───────────────────────────── Shared shell + CSS ────────────────────── */

    private static function shell(string $title, string $body, string $css = '', string $js = '')
    {
        return ExtPage::render($title, '<div class="wrap">'.$body.'</div>', self::baseCss().$css, $js);
    }

    private static function baseCss(): string
    {
        return <<<'CSS'
.ext-frame a{color:inherit;text-decoration:none}
.wrap{max-width:920px;margin:0 auto}
.head{display:flex;align-items:flex-end;gap:16px;margin-bottom:18px}
h1{font-size:27px;margin:0;letter-spacing:-.02em}.sub{color:rgb(var(--c-muted));margin:4px 0 0}
.head .btn{margin-left:auto}
.btn{border:0;border-radius:var(--c-radius-btn,10px);padding:10px 18px;font-weight:700;font-size:14px;cursor:pointer;background:rgb(var(--c-primary));color:#fff}
.btn:hover{background:rgb(var(--c-primary-600,var(--c-primary)))}
.btn.ghost{background:rgb(var(--c-surface-2));color:rgb(var(--c-text-2))}
.empty{padding:64px 24px;text-align:center;color:rgb(var(--c-muted));background:rgb(var(--c-surface));border:1px dashed rgb(var(--c-border));border-radius:16px}
.tabs{display:inline-flex;gap:4px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:12px;padding:5px;margin-bottom:16px}
.tab{padding:8px 16px;border-radius:8px;font-size:14px;font-weight:700;color:rgb(var(--c-text-2))}
.tab.on{background:rgb(var(--c-primary));color:#fff}
.catfilter{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:22px}
.cf{padding:5px 12px;border-radius:999px;font-size:13px;font-weight:700;color:rgb(var(--c-text-2));border:1px solid rgb(var(--c-border))}
.cf.on{background:rgb(var(--c-primary));color:#fff;border-color:rgb(var(--c-primary))}
.cf[style*="--cc"]{border-color:var(--cc)}.cf[style*="--cc"].on{background:var(--cc);border-color:var(--cc);color:#fff}
.cat{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:800;color:#fff;background:var(--cc,rgb(var(--c-primary)));vertical-align:middle}
.rsvp{display:inline-flex;border:1px solid rgb(var(--c-border));border-radius:10px;overflow:hidden}
.r{border:0;background:transparent;padding:8px 14px;font-weight:700;font-size:13px;cursor:pointer;color:rgb(var(--c-text-2));border-right:1px solid rgb(var(--c-border))}
.r:last-child{border-right:0}.r:hover{background:rgb(var(--c-surface-2))}
.r.going.on{background:#10b981;color:#fff}.r.maybe.on{background:#f59e0b;color:#fff}.r.no.on{background:rgb(var(--c-surface-2));color:rgb(var(--c-text))}
.r[disabled]{opacity:.4;cursor:not-allowed}
.counts{color:rgb(var(--c-muted));font-size:13px}
.av{flex:none;border-radius:999px;object-fit:cover}.av.init{display:grid;place-items:center;color:#fff;font-weight:800}
CSS;
    }

    private static function indexCss(): string
    {
        return <<<'CSS'
.list .ev{position:relative;display:flex;gap:16px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;padding:16px;margin-bottom:14px;transition:border-color .12s,transform .12s}
.list .ev:hover{border-color:rgb(var(--c-primary));transform:translateY(-2px)}
.date{flex:none;width:60px;text-align:center;border-radius:12px;padding:9px 0;height:fit-content;background:hsl(var(--h,250) 80% 96%);border:1px solid hsl(var(--h,250) 70% 88%)}
html[data-theme="dark"] .date{background:hsl(var(--h,250) 40% 18%);border-color:hsl(var(--h,250) 40% 28%)}
.date .mo{display:block;font-size:11px;font-weight:800;text-transform:uppercase;color:hsl(var(--h,250) 70% 45%)}
html[data-theme="dark"] .date .mo{color:hsl(var(--h,250) 80% 75%)}
.date .day{display:block;font-size:23px;font-weight:800;color:rgb(var(--c-text))}
.body{min-width:0;flex:1}.trow{display:flex;align-items:center;gap:9px}.t{font-weight:800;font-size:17px;color:rgb(var(--c-text))}.t:hover{color:rgb(var(--c-primary))}
.meta{color:rgb(var(--c-muted));font-size:13px;margin-top:3px}
.desc{color:rgb(var(--c-text-2));font-size:14px;margin:9px 0 0;line-height:1.5}
.foot{display:flex;align-items:center;flex-wrap:wrap;gap:14px;margin-top:14px}.more{margin-left:auto;font-weight:700;font-size:13px;color:rgb(var(--c-primary))}
.del{position:absolute;right:12px;top:12px;border:0;border-radius:50%;width:26px;height:26px;cursor:pointer;background:rgb(var(--c-surface-2));color:rgb(var(--c-muted));font-size:12px}
.calnav{display:flex;align-items:center;justify-content:center;gap:18px;margin-bottom:14px;font-size:17px}
.navbtn{width:36px;height:36px;display:grid;place-items:center;border-radius:9px;background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));font-weight:800}
.navbtn:hover{border-color:rgb(var(--c-primary));color:rgb(var(--c-primary))}
.cal{background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;overflow:hidden}
.dows{display:grid;grid-template-columns:repeat(7,1fr);background:rgb(var(--c-surface-2))}
.dow{padding:9px;text-align:center;font-size:11px;font-weight:800;text-transform:uppercase;color:rgb(var(--c-muted))}
.grid{display:grid;grid-template-columns:repeat(7,1fr)}
.cell{min-height:104px;border-top:1px solid rgb(var(--c-border));border-left:1px solid rgb(var(--c-border));padding:6px}
.cell:nth-child(7n+1){border-left:0}.cell.out{background:rgb(var(--c-surface-2)/.4)}
.dn{font-size:12px;font-weight:700;color:rgb(var(--c-muted))}
.cell.today .dn{background:rgb(var(--c-primary));color:#fff;border-radius:50%;width:22px;height:22px;display:inline-grid;place-items:center}
.chip{display:block;margin-top:4px;padding:3px 6px;border-radius:7px;font-size:11px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chip .ct{opacity:.85;font-weight:800}
@media(max-width:640px){.cell{min-height:78px}.chip{font-size:0}.chip .ct{font-size:9px}.chip:before{content:'•'}}
.modal{border:0;border-radius:16px;padding:0;width:min(540px,92vw);background:rgb(var(--c-surface));color:rgb(var(--c-text));box-shadow:0 24px 70px rgba(0,0,0,.35)}
.modal::backdrop{background:rgba(8,10,20,.55)}
.mhead{display:flex;align-items:center;padding:16px 20px;border-bottom:1px solid rgb(var(--c-border))}.mhead b{flex:1;font-size:16px}
.mhead .x{border:0;background:transparent;font-size:16px;cursor:pointer;color:rgb(var(--c-muted))}
.fcard{padding:18px 20px}
.fcard label{display:block;font-size:13px;color:rgb(var(--c-text-2));margin:12px 0 5px;font-weight:600}
.fcard label.ck{display:flex;align-items:center;gap:8px;font-weight:600}.fcard label.ck input{width:auto;margin:0}
.fcard input,.fcard textarea,.fcard select{width:100%;background:rgb(var(--c-bg));border:1px solid rgb(var(--c-border));border-radius:9px;color:rgb(var(--c-text));padding:9px 11px;font:inherit;font-size:14px}
.tznote{font-size:12px;color:rgb(var(--c-muted));margin:8px 0 0}
.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.frow{display:flex;align-items:center;gap:12px;margin-top:16px}.msg{color:rgb(var(--c-muted));font-size:13px}
CSS;
    }

    private static function detailCss(): string
    {
        return <<<'CSS'
.back{display:inline-block;margin-bottom:16px;font-weight:700;color:rgb(var(--c-text-2))}.back:hover{color:rgb(var(--c-primary))}
.hero{display:flex;align-items:center;gap:20px;padding:26px;border-radius:18px;color:#fff;margin-bottom:24px;background:linear-gradient(135deg,hsl(var(--h,250) 75% 55%),hsl(calc(var(--h,250) + 40) 75% 45%))}
.hero h1{font-size:26px;margin:0;color:#fff}.hmeta{margin-top:6px;font-weight:600;opacity:.92}.htop{margin-bottom:6px}
.hero .cat{background:rgba(255,255,255,.25)}
.hdate{flex:none;width:74px;text-align:center;background:rgba(255,255,255,.2);border-radius:14px;padding:10px 0}
.hmo{display:block;font-size:13px;font-weight:800;text-transform:uppercase}.hday{display:block;font-size:30px;font-weight:800;line-height:1}
.cols{display:grid;grid-template-columns:1fr 300px;gap:24px}
@media(max-width:760px){.cols{grid-template-columns:1fr}}
.main{min-width:0}
.row{display:flex;gap:16px;padding:13px 0;border-bottom:1px solid rgb(var(--c-border))}
.row b{flex:none;width:64px;color:rgb(var(--c-muted));font-size:13px;text-transform:uppercase;letter-spacing:.04em}
.row span{color:rgb(var(--c-text))}
.about{padding:18px 0;border-bottom:1px solid rgb(var(--c-border))}.about b{display:block;color:rgb(var(--c-muted));font-size:13px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px}
.about p{margin:0;line-height:1.6;color:rgb(var(--c-text-2))}
.atts{padding:18px 0}.atts b{display:block;color:rgb(var(--c-muted));font-size:13px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px}
.avrow{display:flex;flex-wrap:wrap;gap:14px}
.att{display:flex;flex-direction:column;align-items:center;gap:5px;width:56px}.an{font-size:11px;color:rgb(var(--c-text-2));text-align:center;line-height:1.1}
.side{display:flex;flex-direction:column;gap:14px}
.rsvpcard,.addcal{background:rgb(var(--c-surface));border:1px solid rgb(var(--c-border));border-radius:14px;padding:16px}
.rl{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:rgb(var(--c-muted));margin-bottom:11px}
.rsvpcard .rsvp{display:flex;width:100%}.rsvpcard .r{flex:1;text-align:center}
.rsvpcard .counts{margin-top:11px}
.addcal{display:flex;flex-direction:column;gap:9px}
.cbtn{display:block;padding:10px 14px;border-radius:10px;border:1px solid rgb(var(--c-border));font-weight:700;font-size:14px;color:rgb(var(--c-text-2));background:transparent;cursor:pointer;text-align:left;width:100%}
.cbtn:hover{border-color:rgb(var(--c-primary));color:rgb(var(--c-primary))}
.addcal .btn,.addcal .ghost{width:100%;text-align:center}
.del.solo{display:block;width:100%;text-align:left;border:1px solid rgb(var(--c-border));background:transparent;color:#ef4444;border-radius:10px;padding:10px 14px;font-weight:700;font-size:14px;cursor:pointer;position:static;height:auto}
.del.solo:hover{border-color:#ef4444;background:rgb(239 68 68/.08)}
CSS;
    }

    private static function adminPage(): string
    {
        $csrf = csrf_token();
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
        $tz = self::tz();
        $rows = DB::table('events')->join('users', 'users.id', '=', 'events.user_id')
            ->orderByDesc('events.starts_at')->limit(200)
            ->get(['events.id', 'events.title', 'events.starts_at', 'users.name as organizer']);
        $list = $rows->map(fn ($ev) => '<div class="row"><div class="b"><b>'.$e($ev->title).'</b>'
            .'<div class="tag">'.Carbon::parse($ev->starts_at, 'UTC')->setTimezone($tz)->format('M j, Y g:ia').' · by '.$e($ev->organizer).' · <a href="/events/'.$ev->id.'" target="_blank">view ↗</a></div></div>'
            .'<button class="btn danger" onclick="del('.$ev->id.')">Delete</button></div>')->implode('') ?: '<p style="color:#9aa0b8">No events yet.</p>';

        $cats = self::categories();
        $catList = $cats->map(fn ($c) => '<div class="crow"><span class="dot" style="background:'.$e($c->color).'"></span><b>'.$e($c->name).'</b>'
            .'<button class="btn danger" onclick="delCat('.$c->id.')">Remove</button></div>')->implode('') ?: '<p style="color:#9aa0b8">No categories yet.</p>';

        $tzOpts = '';
        foreach (timezone_identifiers_list() as $z) {
            $tzOpts .= '<option value="'.$e($z).'"'.($z === $tz ? ' selected' : '').'>'.$e($z).'</option>';
        }

        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{$csrf}"><title>Events · Convoro</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui,sans-serif;background:#0f1120;color:#e6e8f5}
.wrap{max-width:760px;margin:0 auto;padding:40px 20px}a{color:#8b8bf0}h1{font-size:24px;margin:0 0 4px}.sub{color:#9aa0b8;margin:0 0 22px;font-size:14px}
.card{background:#14172a;border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:8px 18px;margin-bottom:18px}
.card.pad{padding:18px}
.card h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#9aa0b8;margin:0 0 14px;padding:0}
.row,.crow{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(255,255,255,.06)}.row:last-child,.crow:last-child{border-bottom:0}
.b{flex:1;min-width:0}.tag{font-size:12px;color:#9aa0b8}
.dot{width:14px;height:14px;border-radius:50%;flex:none}.crow b{flex:1}
.btn{border:0;border-radius:9px;padding:8px 14px;font-weight:700;cursor:pointer;background:#5b5bd6;color:#fff}.btn.danger{background:transparent;color:#f87171}
input,select{background:#0f1120;border:1px solid rgba(255,255,255,.12);border-radius:9px;color:#e6e8f5;padding:9px 11px;font:inherit;font-size:14px}
.frow{display:flex;gap:10px;align-items:center;margin-top:12px}
.top{display:flex;align-items:center;gap:12px;margin-bottom:20px}.sp{flex:1}
label{display:block;font-size:13px;color:#aeb3cc;margin-bottom:6px}
</style></head><body><div class="wrap">
<div class="top"><div><h1>Events</h1><p class="sub">Categories, timezone &amp; moderation. Reminders go out automatically a day and an hour before. <a href="/events" target="_blank">View page ↗</a></p></div><span class="sp"></span><a href="/admin/marketplace">← Marketplace</a></div>

<div class="card pad"><h2>Timezone</h2>
<label>Event times are entered and shown in this timezone</label>
<div class="frow"><select id="tz">{$tzOpts}</select><button class="btn" onclick="saveTz()">Save</button><span id="tzmsg" style="color:#9aa0b8;font-size:13px"></span></div></div>

<div class="card pad"><h2>Categories</h2>
<div id="cats">{$catList}</div>
<div class="frow"><input id="cname" placeholder="Category name" maxlength="60" style="flex:1"><input id="ccolor" type="color" value="#5b5bd6" style="width:54px;padding:4px"><button class="btn" onclick="addCat()">Add</button></div></div>

<div class="card"><h2 style="padding:14px 0 0">All events</h2>{$list}</div>
</div><script>
const csrf=document.querySelector('meta[name=csrf-token]').content;
const H={'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'};
async function del(id){if(!confirm('Delete this event?'))return;await fetch('/events/'+id,{method:'DELETE',headers:H});location.reload();}
async function addCat(){var n=document.getElementById('cname').value.trim();if(!n)return;
  await fetch('/admin/ext/events/categories',{method:'POST',headers:H,body:JSON.stringify({name:n,color:document.getElementById('ccolor').value})});location.reload();}
async function delCat(id){if(!confirm('Remove this category? Events keep but lose the category.'))return;
  await fetch('/admin/ext/events/categories/'+id,{method:'DELETE',headers:H});location.reload();}
async function saveTz(){var v=document.getElementById('tz').value;
  await fetch('/admin/ext/events/timezone',{method:'POST',headers:H,body:JSON.stringify({timezone:v})});
  document.getElementById('tzmsg').textContent='Saved ✓';}
</script></body></html>
HTML;
    }
}
