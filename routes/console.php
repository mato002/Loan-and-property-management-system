<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bulksms:dispatch-schedules')->everyFiveMinutes();

// Rent automation (enabled when workflow_auto_reminders=1 in property portal settings)
Schedule::command('rent:generate-invoices')->dailyAt('00:15');
Schedule::command('rent:send-reminders')->dailyAt('08:00');
