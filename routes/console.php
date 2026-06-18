<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 3-day reminder: events whose starts_at falls within ±30 min of (now + 72h)
Schedule::command('events:send-reminders --hours=72')->hourly();

// 24-hour reminder: events whose starts_at falls within ±30 min of (now + 24h)
Schedule::command('events:send-reminders --hours=24')->hourly();
