# Scheduler Setup

**Phase 3 — cron and automation hardening**

**Production operator hub:** [PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md) (deploy, rollback, logs, webhooks).

The Laravel scheduler runs automated billing, reminders, loan maintenance, SMS dispatch, and queue housekeeping. It is **separate from the queue worker** — see [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md).

All scheduled tasks are defined in `routes/console.php`.

---

## Cron entry (required)

Add **one line** to the server crontab (`crontab -e`). Use the same `php` binary as the web stack.

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Replace `/path/to/project` with the absolute path to this repository.

Reference copy: `deploy/laravel-scheduler.cron.example`

### What this does

Every minute, Laravel checks which scheduled commands are due and runs them. Individual commands have their own timing (daily at 00:15, every 5 minutes, monthly on the 1st, etc.).

---

## Prerequisites

### Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Africa/Nairobi

CACHE_STORE=database
QUEUE_CONNECTION=database
```

| Setting | Why it matters for the scheduler |
|---------|----------------------------------|
| `APP_TIMEZONE` | `dailyAt()` and `monthlyOn()` use the app timezone (wired via `config/app.php`) |
| `CACHE_STORE=database` | Powers `withoutOverlapping()` mutexes and `onOneServer()` locks across PHP processes / app servers |

After changing `.env`:

```bash
php artisan config:cache
```

### Workflow automation toggles

Property billing commands respect portal toggles and optional env override:

```bash
php artisan property:workflow-automation-status
```

Enable/disable in the UI: **Property → System setup → Workflow adjustments**, or set `PROPERTY_WORKFLOW_AUTOMATION_ENABLED` in `.env`.

---

## Scheduled commands

Verified via `php artisan schedule:list`.

| Command | Schedule | Overlap lock | Single server | Workflow gate | Idempotency |
|---------|----------|--------------|---------------|---------------|-------------|
| `bulksms:dispatch-schedules` | Every 5 min | 15 min | No | None | Pending schedules only; status → `processing` before send |
| `fetch:equity-transactions` | Every 5 min *(if Equity configured)* | 10 min | Yes | Equity creds in `.env` | Cache lock + duplicate transaction IDs |
| `invoices:refresh-statuses` | Daily 00:10 | 30 min | No | None | Recomputes status only; safe to repeat |
| `rent:generate-invoices` | Daily 00:15 | 90 min | Yes | Rent invoice toggle | Duplicate guard per lease+unit+month |
| `rent:send-reminders` | 1st of month 08:00 | 120 min | Yes | Reminder toggle | Day-of-month guard; comms idempotency keys |
| `water:generate-invoices` | Daily 00:25 | 90 min | Yes | Water invoice toggle | Duplicate invoice + reading locks |
| `loan:accrue-penalties` | Daily 00:35 | 90 min | Yes | None | Accrual row per loan/scope/installment |
| `water:apply-penalties` | Daily 00:40 | 90 min | Yes | Water penalty toggle | Penalty application dedup by rule+threshold |
| `loan:expire-temporary-access` | Every 10 min | 15 min | No | None | Idempotent status update |
| `loan:lead-officer-digest` | Daily 07:30 | 30 min | Yes | None | Log-only; overlap causes duplicate log lines |
| `queue:prune-failed --hours=168` | Daily 03:15 | 30 min | Yes | None | Deletes failed job rows older than 7 days |

### Scheduler helper

All entries use `scheduleAutomation()` in `routes/console.php`, which applies:

- `withoutOverlapping($minutes)` — cache mutex
- `onOneServer()` — where multi-server duplicate writes are a risk
- `appendOutputTo(storage/logs/scheduler.log)` — operator-visible summaries

---

## Command output and logs

### Scheduler log

Each run appends stdout to:

```
storage/logs/scheduler.log
```

Example lines (from improved command summaries):

```
Bulk SMS schedules: due=2, sent=2, failed=0.
Rent invoices for 2026-05: created=12, skipped_existing=48.
Water invoices for 2026-05 (due 2026-05-05): created=3, skipped_no_lease=1, skipped_duplicate=0, credit_applied=2.
Rent reminders for 2026-05-01: invoices=42, skipped_paid=10, sent=32.
```

Rotate or truncate this file periodically on busy servers (logrotate, or manual).

### Laravel application log

Some commands also write to `storage/logs/laravel.log` (e.g. `loan:lead-officer-digest` via `Log::info`).

---

## Scheduler health checklist

Run this after deploy or when automation seems stuck.

### 1. Cron installed

```bash
crontab -l | grep schedule:run
```

Expected: one line with `php artisan schedule:run` every minute.

### 2. Commands registered

```bash
cd /path/to/project
php artisan schedule:list
```

Confirm all expected commands appear with correct times. Equity sync appears only when Equity API vars are set.

### 3. Last run logs

```bash
tail -50 storage/logs/scheduler.log
```

Look for recent timestamps and summary lines. Empty log may mean cron is not running or no commands were due yet.

Manual test (runs due commands immediately):

```bash
php artisan schedule:run -v
```

### 4. Workflow toggles active

```bash
php artisan property:workflow-automation-status
```

Confirm rent/water/reminder/penalty flags match operator intent before enabling production cron.

### 5. Timezone correct

```bash
php artisan about --only=environment
```

Verify **Timezone** matches your business region (e.g. `Africa/Nairobi`).

Check `.env`:

```env
APP_TIMEZONE=Africa/Nairobi
```

Then `php artisan config:cache`.

**Important:** Scheduled times in `schedule:list` (e.g. `00:15`, `08:00`) are in the **application timezone**, not necessarily the server OS timezone. Align `APP_TIMEZONE` with when you want billing batches to run.

### 6. Cache driver supports mutex

```bash
php artisan about --only=drivers
```

Expected: `Cache ... database` (recommended for production).

With `CACHE_STORE=file`, `onOneServer()` and `withoutOverlapping()` may not coordinate across multiple app servers.

### 7. Queue worker running (related)

Scheduled commands do not replace the queue worker. Email/SMS jobs dispatched during reminders still need `queue:work` — see [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md).

---

## Overlap risks

| Scenario | Risk | Mitigation in place |
|----------|------|---------------------|
| Cron fires twice before first run finishes | Duplicate writes / double SMS | `withoutOverlapping()` on all automation commands |
| Multiple app servers each run cron | Duplicate invoice generation | `onOneServer()` on write-heavy property/loan jobs |
| Long-running rent reminder batch | Second run starts on the 1st | 120-minute overlap lock + `onOneServer()` |
| Equity sync slower than 5 minutes | Overlapping API pulls | 10-minute overlap lock + job cache lock |
| `loan:expire-temporary-access` on two servers | Harmless duplicate updates | 15-minute overlap lock (no `onOneServer` — low impact) |
| `invoices:refresh-statuses` parallel | Extra DB load, not duplicate billing | Overlap lock only; intentionally no `onOneServer` |

### If a mutex is stuck

After a crash, a cache lock can linger until its expiry (see overlap minutes in table above). To clear:

```bash
php artisan cache:clear
php artisan schedule:run
```

Use only when you are sure no legitimate run is in progress.

---

## Timezone risks

| Risk | Consequence | Action |
|------|-------------|--------|
| `APP_TIMEZONE` unset or wrong | Midnight batches run at unexpected local times | Set region in `.env`, run `config:cache` |
| Server OS timezone differs from `APP_TIMEZONE` | Confusion when reading logs vs `schedule:list` | Treat `APP_TIMEZONE` as source of truth for automation |
| UTC in production for Kenya ops | Rent/water at 00:15 UTC = 03:15 EAT | Set `APP_TIMEZONE=Africa/Nairobi` |
| DST regions | Laravel uses PHP timezone rules | Pick correct IANA zone (e.g. `Africa/Nairobi` — no DST) |

Daily batch timeline (with `APP_TIMEZONE=Africa/Nairobi`):

| Local time | Command |
|------------|---------|
| 00:10 | `invoices:refresh-statuses` |
| 00:15 | `rent:generate-invoices` |
| 00:25 | `water:generate-invoices` |
| 00:35 | `loan:accrue-penalties` |
| 00:40 | `water:apply-penalties` |
| 03:15 | `queue:prune-failed` |
| 07:30 | `loan:lead-officer-digest` |
| 08:00 (1st only) | `rent:send-reminders` |

---

## Manual command reference

Safe inspection commands (non-destructive):

```bash
php artisan property:workflow-automation-status
php artisan water:apply-penalties --preview
php artisan loan:accrue-penalties --dry-run
php artisan rent:send-reminders --force --date=2026-05-01   # test only — sends real reminders
```

---

## Related documentation

- [PRODUCTION-READINESS-PHASE-1.md](PRODUCTION-READINESS-PHASE-1.md) — initial inspection
- [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md) — database queue worker
- `deploy/laravel-scheduler.cron.example` — cron snippet

---

## Production operator summary

1. Set `APP_TIMEZONE` and `CACHE_STORE=database` in production `.env`.
2. Add cron: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`
3. Run `php artisan property:workflow-automation-status` before go-live.
4. Monitor `storage/logs/scheduler.log` and use the health checklist above weekly.
5. Keep the queue worker running separately for async email/SMS jobs.
