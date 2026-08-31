<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Shared scheduler defaults for automation commands.
 *
 * - withoutOverlapping: cache mutex prevents concurrent runs (requires CACHE_STORE=database in production).
 * - onOneServer: only one app server runs the task when multiple hosts share cron.
 * - appendOutputTo: command stdout is appended to storage/logs/scheduler.log for operator review.
 */
if (! function_exists('scheduleAutomation')) {
    function scheduleAutomation(string $command, bool $oneServer = false, int $overlapMinutes = 60): \Illuminate\Console\Scheduling\Event
    {
        $event = Schedule::command($command)
            ->withoutOverlapping($overlapMinutes)
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        if ($oneServer) {
            $event->onOneServer();
        }

        return $event;
    }
}

scheduleAutomation('bulksms:dispatch-schedules', overlapMinutes: 15)->everyFiveMinutes();
scheduleAutomation('communications:dispatch-scheduled', overlapMinutes: 15)->everyFiveMinutes();
scheduleAutomation('communications:retry-failed-sms', overlapMinutes: 10)->everyFifteenMinutes();
scheduleAutomation('sms:monitor-wallet', overlapMinutes: 10)->everyFifteenMinutes();

// Equity Bank API sync: only register the schedule when the integration is
// actually configured. SMS-forwarder-only deployments leave EQUITY_API_*
// blank in .env and the scheduler never touches Equity at all (no HTTP call,
// no failed sync rows, no log noise).
if (trim((string) config('equity.base_url')) !== ''
    && trim((string) config('equity.username')) !== ''
    && trim((string) config('equity.api_key')) !== ''
) {
    scheduleAutomation('fetch:equity-transactions', oneServer: true, overlapMinutes: 10)
        ->everyFiveMinutes();
}

// Property rent/water automation: PropertyPortalSetting granular flags + legacy workflow_auto_reminders;
// optional .env PROPERTY_WORKFLOW_AUTOMATION_ENABLED overrides all.
// OS must run `php artisan schedule:run` every minute — see docs/SCHEDULER-SETUP.md
// We run daily to avoid missing a day on small servers; commands are idempotent per month+unit.
scheduleAutomation('invoices:refresh-statuses', overlapMinutes: 30)->dailyAt('00:10');
// Finance integrity (Batch D): hourly allocation & suspense; daily GL reconciliation layers.
scheduleAutomation('finance:reconcile --scope=allocation --audit --alert', oneServer: true, overlapMinutes: 45)->hourly();
scheduleAutomation('finance:reconcile --scope=suspense --audit --alert', oneServer: true, overlapMinutes: 45)->hourlyAt(30);
scheduleAutomation('finance:reconcile --scope=ar_gl --audit --alert', oneServer: true, overlapMinutes: 45)->dailyAt('01:05');
scheduleAutomation('finance:reconcile --scope=landlord_gl --audit --alert', oneServer: true, overlapMinutes: 45)->dailyAt('01:10');
scheduleAutomation('finance:detect-accounting-drift --audit', oneServer: true, overlapMinutes: 45)->dailyAt('01:15');
scheduleAutomation('finance:reconcile --scope=tenant_credit_gl --audit --alert', oneServer: true, overlapMinutes: 45)->dailyAt('01:20');
scheduleAutomation('finance:reconcile --scope=penalties_gl --audit --alert', oneServer: true, overlapMinutes: 45)->dailyAt('01:25');
scheduleAutomation('finance:reconcile --scope=all --audit', oneServer: true, overlapMinutes: 90)->dailyAt('02:00');
scheduleAutomation('rent:generate-invoices', oneServer: true, overlapMinutes: 90)->dailyAt('00:15');
scheduleAutomation('utility:materialize-attached-charges', oneServer: true, overlapMinutes: 90)->dailyAt('00:22');
// Rent reminders: daily stage evaluation (D-3, D-1, due today, overdue buckets) per invoice due_date.
scheduleAutomation('rent:send-reminders', oneServer: true, overlapMinutes: 120)->dailyAt('08:00');
scheduleAutomation('invoices:deliver-pending', oneServer: true, overlapMinutes: 120)->dailyAt('08:30');

// Water: safe to run daily because generation checks for duplicates; penalties are applied on overdue balances.
scheduleAutomation('water:generate-invoices', oneServer: true, overlapMinutes: 90)->dailyAt('00:25');
scheduleAutomation('loan:refresh-dpd', oneServer: true, overlapMinutes: 90)->dailyAt('00:30');
scheduleAutomation('loan:accrue-penalties', oneServer: true, overlapMinutes: 90)->dailyAt('00:35');
scheduleAutomation('water:apply-penalties', oneServer: true, overlapMinutes: 90)->dailyAt('00:40');
scheduleAutomation('loan:expire-temporary-access', overlapMinutes: 15)->everyTenMinutes();
scheduleAutomation('landlord:send-portal-alerts', oneServer: true, overlapMinutes: 30)->dailyAt('07:00');

// Prune failed queue jobs older than 7 days (requires schedule:run cron).
// Recent failures remain visible via `php artisan queue:failed`.
scheduleAutomation('queue:prune-failed --hours=168', oneServer: true, overlapMinutes: 30)->dailyAt('03:15');

// Horizon metrics (Phase 10 — only meaningful with QUEUE_CONNECTION=redis + horizon running).
if (config('queue.default') === 'redis') {
    scheduleAutomation('horizon:snapshot', oneServer: true, overlapMinutes: 5)->everyFiveMinutes();
}
