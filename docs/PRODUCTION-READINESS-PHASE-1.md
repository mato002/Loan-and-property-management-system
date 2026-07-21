# Production Readiness Report — Phase 1

**Date:** 2026-05-28  
**Scope:** Database queue + scheduler stabilization (no Redis, no Horizon, no UI/business-logic changes)  
**Environment inspected:** Local dev instance (`APP_ENV=local`) against production targets documented in `.env.example`

**Consolidated operator guide (Phase 11):** [PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md)

---

## Executive summary

The application is structurally ready for a **database-backed queue + cron scheduler** deployment: migrations for queue tables exist and have run, all seven requested Artisan commands are implemented with automation gates and idempotency guards, and workflow automation can be controlled via `.env` or portal toggles.

**Before go-live**, production `.env` must differ from the current local setup on several drivers, OS cron must run `schedule:run` every minute, and a persistent **`queue:work`** process must run (queued email/SMS/communication jobs will not send otherwise). The largest operational risks are **UTC-only scheduling** (`APP_TIMEZONE` is documented but not wired in `config/app.php`), **file cache/session drivers** (breaks `onOneServer()` locks and multi-server sessions), and **no documented queue worker** alongside the scheduler cron example.

---

## 1. Environment drivers

### Production targets (from `.env.example` + Laravel config defaults)

| Variable | Recommended production value | Purpose |
|----------|------------------------------|---------|
| `QUEUE_CONNECTION` | `database` | Persist jobs in `jobs` table |
| `CACHE_STORE` | `database` | Shared cache + `cache_locks` for scheduler mutex / Equity sync lock |
| `SESSION_DRIVER` | `database` | Shared sessions across PHP workers |
| `APP_TIMEZONE` | e.g. `Africa/Nairobi` | Invoice months, due dates, scheduled times |
| `LOG_CHANNEL` | `stack` (with `LOG_STACK=daily` optional) | Structured logging |
| `LOG_LEVEL` | `warning` or `error` | Reduce noise in production |
| `APP_DEBUG` | `false` | Hide stack traces from users |
| `APP_ENV` | `production` | Production mode |

Additional production flags referenced by automation:

| Variable | Notes |
|----------|-------|
| `PROPERTY_WORKFLOW_AUTOMATION_ENABLED` | When set to `true`/`false`, overrides **all** portal workflow checkboxes for scheduled commands only. When unset, database toggles control each job. |

### Current local `.env` (inspected)

| Variable | Local value | vs production target |
|----------|-------------|----------------------|
| `APP_ENV` | `local` | Change to `production` |
| `APP_DEBUG` | `true` | **Risk** — must be `false` |
| `APP_TIMEZONE` | *(not set)* | **Gap** — see timezone section below |
| `LOG_CHANNEL` | `stack` | OK |
| `LOG_LEVEL` | `warning` | OK |
| `QUEUE_CONNECTION` | `database` | OK |
| `CACHE_STORE` | `file` | **Risk** — use `database` |
| `SESSION_DRIVER` | `file` | **Risk** — use `database` |
| `PROPERTY_WORKFLOW_AUTOMATION_ENABLED` | `true` | Overrides UI toggles; all automation ON |

Verified via `php artisan about`:

```
Queue .............. database
Cache .............. file
Session ............ file
Timezone ........... UTC (hardcoded in config/app.php)
Debug Mode ......... ENABLED
```

### Timezone gap (important)

`.env.example` documents `APP_TIMEZONE=UTC`, but `config/app.php` hardcodes:

```php
'timezone' => 'UTC',
```

Scheduled jobs in `routes/console.php` run at **UTC** midnight (`00:10`, `00:15`, etc.), not local business time, unless the server OS timezone or this config is aligned intentionally. **Phase 2 fix:** wire `env('APP_TIMEZONE', 'UTC')` into `config/app.php` and set the correct region in production `.env`.

---

## 2. Queue tables

### Migrations

| Table | Migration | Status (local DB) |
|-------|-----------|-------------------|
| `jobs` | `0001_01_01_000002_create_jobs_table` | Ran |
| `failed_jobs` | same migration | Ran |
| `job_batches` | same migration | Ran |

Related infrastructure tables (also required for recommended drivers):

| Table | Migration | Status (local DB) |
|-------|-----------|-------------------|
| `cache` | `0001_01_01_000001_create_cache_table` | Ran |
| `cache_locks` | same migration | Ran |
| `sessions` | `0001_01_01_000000_create_users_table` | Ran |

### Queue health (local)

| Metric | Value |
|--------|-------|
| Pending jobs (`jobs`) | 0 |
| Failed jobs (`failed_jobs`) | 0 |

**Missing on server if migrations not run:** run `php artisan migrate --force` before starting workers.

---

## 3. Scheduler commands

Registered in `routes/console.php`. Verified with `php artisan schedule:list`.

| Command | Schedule | Registered locally | Automation gate | Safety notes |
|---------|----------|-------------------|-----------------|--------------|
| `bulksms:dispatch-schedules` | Every 5 minutes | Yes | None (always runs) | Only sends `SmsSchedule` rows with `status=pending` and `scheduled_at <= now()` |
| `invoices:refresh-statuses` | Daily 00:10 UTC | Yes | None | Recomputes stale statuses only; `--limit=2000` default |
| `rent:generate-invoices` | Daily 00:15 UTC | Yes | `PropertyPortalSetting::isRentInvoiceAutomationEnabled()` | Skips when off; duplicate guard per lease+unit+month; posts to GL |
| `rent:send-reminders` | 1st of month 08:00 UTC | Yes | `isRentReminderAutomationEnabled()` | Exits unless day=1 (unless `--force`); idempotency keys on email/SMS |
| `water:generate-invoices` | Daily 00:25 UTC | Yes | `isWaterInvoiceAutomationEnabled()` | Duplicate invoice check; `lockForUpdate` on readings; `onOneServer()` |
| `water:apply-penalties` | Daily 00:40 UTC | Yes | `isWaterPenaltyAutomationEnabled()` | Dedup via `PmInvoicePenaltyApplication` (rule + threshold); `--preview` for dry run |
| `fetch:equity-transactions` | Every 5 min *(conditional)* | **No** (Equity not configured) | Registered only when `EQUITY_API_BASE_URL`, `EQUITY_API_USERNAME`, and `EQUITY_API_KEY` are all non-empty | Command uses `dispatchSync`; job has cache lock + short-circuit when not configured |

**Also scheduled (outside Phase 1 task list):** `loan:accrue-penalties`, `loan:expire-temporary-access`, `loan:lead-officer-digest`.

### Command class locations

| Signature | Class |
|-----------|-------|
| `rent:generate-invoices` | `App\Console\Commands\GenerateMonthlyRentInvoices` |
| `rent:send-reminders` | `App\Console\Commands\SendRentReminders` |
| `water:generate-invoices` | `App\Console\Commands\GenerateMonthlyWaterInvoices` |
| `water:apply-penalties` | `App\Console\Commands\ApplyOverdueWaterPenalties` |
| `invoices:refresh-statuses` | `App\Console\Commands\RefreshInvoiceStatuses` |
| `bulksms:dispatch-schedules` | `App\Console\Commands\DispatchScheduledBulkSms` |
| `fetch:equity-transactions` | `App\Console\Commands\SyncEquityTransactions` |

### Queued jobs requiring a worker

These implement `ShouldQueue` and will sit in the `jobs` table until `queue:work` runs:

- `SendEmailJob`, `SendSmsJob`, `SendBulkCommunicationJob`
- `SendPayrollPayslipEmailJob`
- `SendRentReminderJob` *(wraps `rent:send-reminders`)*
- `FetchEquityTransactionsJob` *(only if dispatched async; scheduler command uses `dispatchSync`)*
- `RefreshUtilityIntelligenceCacheJob`

**Risk:** Scheduler cron alone is insufficient if any of these are dispatched during normal operation.

---

## 4. Workflow toggles

Diagnostic command: `php artisan property:workflow-automation-status`

### Local effective state (2026-05-28)

```
PROPERTY_WORKFLOW_AUTOMATION_ENABLED: true (overrides all flags below)
Legacy workflow_auto_reminders: off
Rent invoices: ON
Water invoices: ON
Rent reminders: ON
Water penalties: ON
Any scheduled automation ON: yes
```

### Toggle hierarchy

1. **`PROPERTY_WORKFLOW_AUTOMATION_ENABLED`** in `.env` — when set, overrides every granular flag.
2. **Granular DB keys** in `property_portal_settings` (set via Property → System setup → Workflow adjustments):
   - `workflow_auto_rent_invoices`
   - `workflow_auto_water_invoices`
   - `workflow_auto_rent_reminders`
   - `workflow_auto_water_penalties`
3. **Legacy fallback:** `workflow_auto_reminders` when a granular key has never been saved.

Local DB has **no saved granular keys**; automation is ON solely because of the `.env` override.

### Portal UI references

- `resources/views/property/agent/settings/system_setup/workflows.blade.php` (and v2/legacy variants)
- Store handler: `PropertySettingsStoreWebController`

---

## 5. What is configured ✅

- Database queue driver selected (`QUEUE_CONNECTION=database`).
- Queue table migrations exist and have run locally.
- All seven scheduler commands implemented with skip gates and idempotency patterns.
- Scheduler definitions use `withoutOverlapping()` (and `onOneServer()` for water jobs).
- Equity sync schedule is **conditionally registered** — no HTTP noise when credentials are blank.
- Cron example exists: `deploy/laravel-scheduler.cron.example`.
- Workflow status diagnostic command exists: `property:workflow-automation-status`.
- Queue backlog clean locally (0 pending, 0 failed).

---

## 6. What is missing ⚠️

| Item | Impact |
|------|--------|
| OS cron entry for `schedule:run` | No scheduled automation runs |
| Persistent `queue:work` process (Supervisor/systemd) | Email, SMS, bulk comms jobs never execute |
| Production `.env` values (`APP_DEBUG=false`, `APP_ENV=production`) | Security / error exposure |
| `CACHE_STORE=database` on server | `onOneServer()` mutex unreliable with `file` cache on multiple app servers |
| `SESSION_DRIVER=database` on server | Session loss / inconsistency across PHP-FPM workers |
| `APP_TIMEZONE` wired in config | Midnight batch jobs run at UTC, not local business time |
| Supervisor/unit file for queue worker | Only scheduler cron is documented today |
| Production log rotation config (`LOG_STACK=daily`) | Optional but recommended |
| Equity API credentials (if paybill sync needed) | `fetch:equity-transactions` never schedules |

---

## 7. What is risky 🔴

| Risk | Severity | Detail |
|------|----------|--------|
| No queue worker | **High** | `PropertyCommunicationService` dispatches queued email/SMS jobs; reminders may queue but not send |
| `APP_DEBUG=true` in production | **High** | Exposes stack traces and internals |
| UTC scheduling vs business timezone | **Medium** | Rent/water batches at 00:10–00:40 UTC may not match intended local midnight |
| `PROPERTY_WORKFLOW_AUTOMATION_ENABLED=true` in `.env` | **Medium** | Disables portal kill-switch; accidental billing if cron starts before data review |
| `CACHE_STORE=file` + `onOneServer()` | **Medium** | File locks are per-server; duplicate water runs possible on multi-node deploy |
| `SESSION_DRIVER=file` | **Medium** | Not suitable for horizontal scaling or multiple workers |
| `bulksms:dispatch-schedules` always on | **Low** | Safe when no pending schedules; ensure provider credentials configured before use |
| Equity `dispatchSync` in scheduler | **Low** | Runs inline in scheduler process; long API calls block that cron tick |

---

## 8. Exact server commands

Run from the project root after deploy. Replace `/path/to/project` and PHP binary as needed.

### One-time deploy setup

```bash
cd /path/to/project

# Install dependencies (production)
composer install --no-dev --optimize-autoloader

# Environment — edit .env for production values (see section 1)
# APP_ENV=production
# APP_DEBUG=false
# QUEUE_CONNECTION=database
# CACHE_STORE=database
# SESSION_DRIVER=database
# LOG_CHANNEL=stack
# LOG_LEVEL=warning

php artisan key:generate   # only on first deploy if APP_KEY empty
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link     # if not already linked
```

### Cron — scheduler (required)

Add to crontab (`crontab -e`):

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Reference: `deploy/laravel-scheduler.cron.example`

### Queue worker (required for async jobs)

Foreground (testing only):

```bash
cd /path/to/project
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
```

Production (Supervisor example — adjust paths/user):

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### Verification commands

```bash
cd /path/to/project

php artisan about --only=environment,cache,drivers
php artisan schedule:list
php artisan property:workflow-automation-status
php artisan migrate:status | grep -E 'jobs|cache|sessions'

# Optional dry runs (non-destructive previews)
php artisan water:apply-penalties --preview
php artisan rent:send-reminders --force --date=$(date +%F)   # only if intentional test send

# Monitor queue
php artisan queue:monitor database:default
# Inspect failures
php artisan queue:failed
```

### After `.env` changes

```bash
php artisan config:clear
php artisan config:cache
```

---

## 9. Recommended production `.env` snippet

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-host
APP_TIMEZONE=Africa/Nairobi

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
# DB_HOST=...
# DB_DATABASE=...
# DB_USERNAME=...
# DB_PASSWORD=...

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

# Leave unset to use portal toggles; set true/false to override all automation
# PROPERTY_WORKFLOW_AUTOMATION_ENABLED=

# Equity — leave blank to disable scheduled sync
# EQUITY_API_BASE_URL=
# EQUITY_API_USERNAME=
# EQUITY_API_KEY=
```

---

## 10. Phase 2 follow-ups (out of scope for Phase 1)

- Wire `APP_TIMEZONE` into `config/app.php`.
- Add Supervisor unit file to `deploy/` alongside scheduler cron example.
- Align local `.env` with production driver targets for staging parity.
- Consider Redis + Horizon only after database queue + worker are stable in production.

---

*Generated by Phase 1 inspection. No application code was modified.*
