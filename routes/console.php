<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bulksms:dispatch-schedules')->everyFiveMinutes();
Schedule::command('fetch:equity-transactions')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Rent automation (enabled when workflow_auto_reminders=1 in property portal settings)
// We run daily to avoid missing a day on small servers; the command itself is idempotent per month+unit.
Schedule::command('rent:generate-invoices')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('rent:send-reminders')->dailyAt('08:00');

// Water automation (enabled when workflow_auto_reminders=1 in property portal settings)
// Same: safe to run daily because generation checks for duplicates; penalties are applied on overdue balances.
Schedule::command('water:generate-invoices')->dailyAt('00:25')->withoutOverlapping();
Schedule::command('water:apply-penalties')->dailyAt('00:40')->withoutOverlapping();
