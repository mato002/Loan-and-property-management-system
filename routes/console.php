<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bulksms:dispatch-schedules')->everyFiveMinutes();

// Equity Bank API sync: only register the schedule when the integration is
// actually configured. SMS-forwarder-only deployments leave EQUITY_API_*
// blank in .env and the scheduler never touches Equity at all (no HTTP call,
// no failed sync rows, no log noise).
if (trim((string) config('equity.base_url')) !== ''
    && trim((string) config('equity.username')) !== ''
    && trim((string) config('equity.api_key')) !== ''
) {
    Schedule::command('fetch:equity-transactions')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

// Property rent/water automation: PropertyPortalSetting granular flags + legacy workflow_auto_reminders;
// optional .env PROPERTY_WORKFLOW_AUTOMATION_ENABLED overrides all.
// OS must run `php artisan schedule:run` every minute — see deploy/laravel-scheduler.cron.example
// We run daily to avoid missing a day on small servers; the command itself is idempotent per month+unit.
Schedule::command('invoices:refresh-statuses')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('rent:generate-invoices')->dailyAt('00:15')->withoutOverlapping();
// Rent reminders: 1st of month only (see SendRentReminders; use --force for manual mid-month runs).
Schedule::command('rent:send-reminders')->monthlyOn(1, '08:00');

// Water: safe to run daily because generation checks for duplicates; penalties are applied on overdue balances.
Schedule::command('water:generate-invoices')->dailyAt('00:25')->withoutOverlapping()->onOneServer();
Schedule::command('loan:accrue-penalties')->dailyAt('00:35')->withoutOverlapping()->onOneServer();
Schedule::command('water:apply-penalties')->dailyAt('00:40')->withoutOverlapping()->onOneServer();
Schedule::command('loan:expire-temporary-access')->everyTenMinutes()->withoutOverlapping();
Schedule::command('loan:lead-officer-digest')->dailyAt('07:30');
