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
