<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Digest emails: daily at 08:00, weekly on Monday 08:00.
Schedule::command('convoro:digest daily')->dailyAt('08:00');
Schedule::command('convoro:digest weekly')->weeklyOn(1, '08:00');

// Registry auto-refresh: pull new GitHub releases into the directory hourly
// (the webhook handles instant updates; this is the safety-net poll).
Schedule::command('convoro:refresh-registry')->hourly()->withoutOverlapping();

// Publish scheduled posts the moment their time arrives.
Schedule::command('convoro:publish-scheduled')->everyMinute()->withoutOverlapping();

// Database backups at 03:00 on the admin-chosen cadence. The command no-ops
// when scheduled backups are off; the ->when guards pick the right cadence.
// rescue() keeps a pre-migration install from crashing the scheduler.
Schedule::command('convoro:backup --prune')->dailyAt('03:00')
    ->when(fn () => rescue(fn () => (bool) \App\Support\Settings::get('backups.enabled', false)
        && \App\Support\Settings::get('backups.frequency', 'daily') === 'daily', false))
    ->withoutOverlapping();
Schedule::command('convoro:backup --prune')->weeklyOn(1, '03:00')
    ->when(fn () => rescue(fn () => (bool) \App\Support\Settings::get('backups.enabled', false)
        && \App\Support\Settings::get('backups.frequency', 'daily') === 'weekly', false))
    ->withoutOverlapping();
