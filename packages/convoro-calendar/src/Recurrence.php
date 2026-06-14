<?php

namespace Convoro\Ext\Calendar;

use Illuminate\Support\Carbon;

/**
 * Recurrence maths for events. All times are UTC Carbons. The repeat keys are
 * none|daily|weekly|biweekly|monthly with an optional end (recurrence_until).
 */
class Recurrence
{
    public const OPTIONS = [
        'none' => 'Does not repeat',
        'daily' => 'Every day',
        'weekly' => 'Every week',
        'biweekly' => 'Every 2 weeks',
        'monthly' => 'Every month',
    ];

    /** @return array{0:string,1:int}|null  [unit, interval] */
    private static function step(string $rec): ?array
    {
        return match ($rec) {
            'daily' => ['day', 1],
            'weekly' => ['week', 1],
            'biweekly' => ['week', 2],
            'monthly' => ['month', 1],
            default => null,
        };
    }

    public static function isRecurring(?string $rec): bool
    {
        return $rec !== null && $rec !== '' && $rec !== 'none' && self::step($rec) !== null;
    }

    public static function label(string $rec): string
    {
        return self::OPTIONS[$rec] ?? self::OPTIONS['none'];
    }

    /** The next occurrence start at or after $from, or null if the series has ended. */
    public static function next(Carbon $start, string $rec, Carbon $from, ?Carbon $until = null): ?Carbon
    {
        if (! self::isRecurring($rec)) {
            return $start->greaterThanOrEqualTo($from) ? $start->copy() : null;
        }
        [$unit, $interval] = self::step($rec);
        $occ = $start->copy();
        $guard = 0;
        while ($occ->lessThan($from) && $guard++ < 4000) {
            $occ->add($unit, $interval);
        }
        if ($until && $occ->greaterThan($until)) {
            return null;
        }

        return $occ;
    }

    /**
     * All occurrence starts within [$rangeStart, $rangeEnd] (inclusive).
     *
     * @return array<int,Carbon>
     */
    public static function occurrencesIn(Carbon $start, string $rec, Carbon $rangeStart, Carbon $rangeEnd, ?Carbon $until = null): array
    {
        if (! self::isRecurring($rec)) {
            return $start->betweenIncluded($rangeStart, $rangeEnd) ? [$start->copy()] : [];
        }
        [$unit, $interval] = self::step($rec);
        $cap = $until && $until->lessThan($rangeEnd) ? $until : $rangeEnd;
        $occ = self::next($start, $rec, $rangeStart, $until);
        $out = [];
        $guard = 0;
        while ($occ && $occ->lessThanOrEqualTo($cap) && $guard++ < 400) {
            $out[] = $occ->copy();
            $occ->add($unit, $interval);
        }

        return $out;
    }

    /** An iCal RRULE for the series (no leading "RRULE:"), or null for one-offs. */
    public static function rrule(string $rec, ?Carbon $until = null): ?string
    {
        $map = [
            'daily' => 'FREQ=DAILY',
            'weekly' => 'FREQ=WEEKLY',
            'biweekly' => 'FREQ=WEEKLY;INTERVAL=2',
            'monthly' => 'FREQ=MONTHLY',
        ];
        if (! isset($map[$rec])) {
            return null;
        }
        $rule = $map[$rec];
        if ($until) {
            $rule .= ';UNTIL='.$until->copy()->utc()->format('Ymd\THis\Z');
        }

        return $rule;
    }
}
