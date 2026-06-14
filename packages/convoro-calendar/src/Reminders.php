<?php

namespace Convoro\Ext\Calendar;

use App\Models\User;
use App\Support\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sends reminders to event attendees a day and an hour before each occurrence.
 * Works for one-off AND recurring events: for each event we compute the next
 * occurrence and remind once per occurrence per window, tracked by
 * reminded_day_for / reminded_hour_for (the occurrence start last reminded).
 */
class Reminders
{
    public static function run(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasColumn('events', 'reminded_day_for')) {
            return;
        }

        $now = now()->utc();

        // One-offs within the next day, plus all still-active recurring series.
        $events = DB::table('events')
            ->where(function ($q) use ($now) {
                $q->where(function ($q2) use ($now) {
                    $q2->where('recurrence', 'none')
                        ->whereBetween('starts_at', [$now->copy()->subHour()->toDateTimeString(), $now->copy()->addDay()->toDateTimeString()]);
                })->orWhere(function ($q2) use ($now) {
                    $q2->where('recurrence', '!=', 'none')
                        ->where(fn ($q3) => $q3->whereNull('recurrence_until')->orWhere('recurrence_until', '>=', $now));
                });
            })
            ->get(['id', 'title', 'user_id', 'starts_at', 'recurrence', 'recurrence_until', 'reminded_day_for', 'reminded_hour_for']);

        foreach ($events as $ev) {
            $next = Recurrence::next(
                Carbon::parse($ev->starts_at, 'UTC'), $ev->recurrence ?? 'none', $now,
                $ev->recurrence_until ? Carbon::parse($ev->recurrence_until, 'UTC') : null,
            );
            if (! $next) {
                continue;
            }
            $key = $next->toDateTimeString();

            // Day-before: occurrence is within the next 24h, not yet reminded.
            if ($next->lessThanOrEqualTo($now->copy()->addDay()) && (string) $ev->reminded_day_for !== $key) {
                self::notify($ev, $next);
                DB::table('events')->where('id', $ev->id)->update(['reminded_day_for' => $key]);
            }
            // Final reminder: occurrence is within the next hour, not yet reminded.
            if ($next->lessThanOrEqualTo($now->copy()->addHour()) && (string) $ev->reminded_hour_for !== $key) {
                self::notify($ev, $next);
                DB::table('events')->where('id', $ev->id)->update(['reminded_hour_for' => $key]);
            }
        }
    }

    private static function notify(object $ev, Carbon $occurrence): void
    {
        $userIds = DB::table('event_rsvps')->where('event_id', $ev->id)
            ->whereIn('status', ['going', 'maybe'])->pluck('user_id')->all();
        if ($ev->user_id) {
            $userIds[] = (int) $ev->user_id;
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        $when = $occurrence->diffForHumans(['parts' => 1]);
        $text = '📅 “'.trim((string) $ev->title).'” starts '.$when;

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            try {
                Notifier::send($user, new EventReminderNotification((int) $ev->id, $text));
            } catch (\Throwable $e) {
                // One bad recipient must never stall the whole pass.
            }
        }
    }
}
