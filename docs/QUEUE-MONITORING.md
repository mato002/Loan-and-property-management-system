# Queue Monitoring — Phase 10

**Date:** 2026-05-28  
**Prerequisite:** Phase 9 Redis queue cutover stable (`ops:redis-cutover-verify` passing for 24–48h on production).  
**Related:** `docs/QUEUE-WORKER-SETUP.md`, `docs/REDIS-CUTOVER.md`, `docs/REDIS-READINESS.md`

---

## Executive summary

Operations need three signals:

| Signal | Question |
|--------|----------|
| **Backlog** | Are jobs piling up? |
| **Failures** | What broke and when? |
| **Worker health** | Is something processing the queue? |

This project supports **two monitoring paths**:

1. **VPS + Redis (recommended after Phase 9):** Laravel **Horizon** dashboard + `ops:queue-status`
2. **Database queue / shared hosting:** CLI + logs + `ops:queue-status` (no Horizon)

---

## Is Horizon suitable?

| Environment | Horizon? | Why |
|-------------|----------|-----|
| **Linux VPS** + Redis + Supervisor | **Yes** | Full dashboard, metrics, failed-job UI, auto-scaling supervisors |
| **Windows / XAMPP local** | **No** | Requires `ext-pcntl` and `ext-posix` (not available on Windows PHP) |
| **cPanel / shared hosting** | **No** | No long-lived `horizon` process; often no Redis |
| **Database queue only** | **No** | Horizon requires Redis for queues and metadata |

Horizon is **installed in this repo** for production Linux deploys. It is **not run** on Windows dev machines — use `ops:queue-status` and `queue:failed` locally.

### Horizon requirements (production)

- PHP extensions: **`pcntl`**, **`posix`**
- `QUEUE_CONNECTION=redis`
- Redis server reachable
- Supervisor (or systemd) running `php artisan horizon`
- Super-admin web login for `/horizon`

---

## Quick reference — where to check

| Check | Command / URL | Who |
|-------|---------------|-----|
| **Backlog + failures snapshot** | `php artisan ops:queue-status` | Ops / SSH |
| **JSON for scripts / cron** | `php artisan ops:queue-status --json` | Monitoring automation |
| **Failed job details** | `php artisan queue:failed` | Ops / SSH |
| **Retry failures** | `php artisan queue:retry all` or `queue:retry {uuid}` | Ops |
| **Horizon dashboard** | `https://{APP_URL}/horizon` | Super admin browser |
| **Horizon process** | `php artisan horizon:status` | Ops / SSH |
| **Worker log (non-Horizon)** | `storage/logs/worker.log` | Ops |
| **Horizon log** | `storage/logs/horizon.log` | Ops |
| **App errors** | `storage/logs/laravel.log` | Ops |
| **Scheduler log** | `storage/logs/scheduler.log` | Ops |
| **HTTP uptime** | `GET /up` | External monitor (UptimeRobot, etc.) |
| **Redis cutover health** | `php artisan ops:redis-cutover-verify` | Ops (post Phase 9) |

**Primary doc for on-call:** run `ops:queue-status` first; drill into Horizon or `queue:failed` as needed.

---

## Path A — Horizon (VPS + stable Redis)

### Install (already in repo)

```bash
composer install   # includes laravel/horizon
php artisan migrate
```

Config: `config/horizon.php` — supervisors for **`high`**, **`default`**, **`low`**.

### Secure dashboard

- URL: `{APP_URL}/horizon` (override with `HORIZON_PATH`)
- Middleware: `web`, `auth`
- Access: **`is_super_admin === true`** only (`App\Providers\HorizonServiceProvider`)
- Non-super-admins receive 403 even in `local`

Do **not** expose `/horizon` publicly without HTTPS and strong admin accounts.

### Start Horizon (replaces `queue:work`)

Stop standalone Redis workers first. Use Supervisor:

```bash
# deploy/laravel-horizon.supervisor.example
php artisan horizon
```

After code deploy:

```bash
php artisan horizon:terminate
```

Horizon gracefully restarts workers.

### Queue priorities

| Queue | Jobs | Supervisor |
|-------|------|------------|
| **high** | `SendEmailJob`, `SendSmsJob` | `supervisor-high` |
| **default** | `SendBulkCommunicationJob`, `SendPayrollPayslipEmailJob`, `RefreshUtilityIntelligenceCacheJob` | `supervisor-default` |
| **low** | `SendRentReminderJob`, `FetchEquityTransactionsJob` | `supervisor-low` |

### Metrics

Scheduler runs `horizon:snapshot` every 5 minutes when `QUEUE_CONNECTION=redis` (see `routes/console.php`). Powers throughput/wait graphs in the dashboard.

Long-wait alerts configured in `config/horizon.php`:

- `redis:high` — 30s  
- `redis:default` — 60s  
- `redis:low` — 120s  

---

## Path B — Without Horizon (database queue or pre-Horizon Redis)

Use when on shared hosting, before Horizon rollout, or running `queue:work` manually.

### Backlog

```bash
php artisan ops:queue-status
```

Shows pending counts for `high`, `default`, `low` on the active queue connection.

For **database** driver, pending rows live in the `jobs` table. For **Redis**, Laravel list length via `Queue::size()`.

### Failed jobs

All failed jobs are stored in **`failed_jobs`** (driver `database-uuids`):

```bash
php artisan queue:failed
php artisan queue:retry {uuid}
php artisan queue:flush          # clear all failed records — use with care
```

Stale failures are pruned after **7 days** by scheduled `queue:prune-failed`.

There is **no built-in web UI** for failed jobs outside Horizon. Options:

- SSH + `queue:failed` (primary)
- Horizon dashboard (Path A)
- External log aggregation searching for `Failed` in `laravel.log`

### Worker health (database)

Verify a long-running process exists:

```bash
# Supervisor
sudo supervisorctl status laravel-worker:*

# systemd
systemctl status laravel-queue-worker

# Manual
ps aux | grep "queue:work"
```

Worker command (database):

```bash
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
```

See `docs/QUEUE-WORKER-SETUP.md`.

### Worker health (Redis without Horizon)

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
```

Monitor `storage/logs/worker.log`. Prefer migrating to Horizon on VPS when Phase 9 is stable.

---

## External uptime monitoring

Monitor these from outside the server:

| Target | Purpose |
|--------|---------|
| `GET https://{domain}/up` | App + DB reachable |
| Optional: `GET https://{domain}/horizon` | Expect 302/401/403 when not logged in — **not** 500 |
| Cron heartbeat | External cron pings if `schedule:run` must run every minute |

Alert on:

- `/up` non-200 for > 5 minutes
- `ops:queue-status --json` showing `failed_jobs.total` > 0 (via SSH cron script)
- `horizon:status` not running on production VPS
- Redis memory near `maxmemory` limit

Example cron on VPS (alerting hook — adjust notify command):

```bash
0 * * * * cd /path/to/project && php artisan ops:queue-status --json | jq -e '.failed_jobs.total == 0' >/dev/null || echo "failed jobs present" | mail -s "Queue alert" ops@example.com
```

---

## Log monitoring

| Log file | Contents |
|----------|----------|
| `storage/logs/laravel.log` | Exceptions, job failures stack traces |
| `storage/logs/worker.log` | `queue:work` stdout/stderr |
| `storage/logs/horizon.log` | Horizon master supervisor |
| `storage/logs/scheduler.log` | Scheduled command output |

Use `php artisan pail` in local dev for live tail.

Search production logs for:

- `Illuminate\\Queue\\MaxAttemptsExceededException`
- `job failed`
- `FetchEquityTransactionsJob`, `SendSmsJob`, etc.

---

## Rollout checklist (Phase 10 on VPS)

Complete **after** Phase 9 stable.

- [ ] `ops:redis-cutover-verify` passes
- [ ] Stop `queue:work redis` Supervisor program
- [ ] Configure `deploy/laravel-horizon.supervisor.example` → start `php artisan horizon`
- [ ] `php artisan horizon:status` → running
- [ ] Log in as super admin → `/horizon` loads
- [ ] Dispatch test email/SMS → appears in Horizon → completes
- [ ] `ops:queue-status` → zero backlog after test, zero failed jobs
- [ ] Confirm `horizon:snapshot` in scheduler (every 5 min)
- [ ] Document on-call: this file + `ops:queue-status`

---

## Rollback from Horizon

1. `php artisan horizon:terminate`
2. Stop Horizon Supervisor program
3. Restart standalone worker: `queue:work redis --queue=high,default,low`
4. Monitoring reverts to Path B (`ops:queue-status`, `queue:failed`)

Horizon can remain installed; only the master process needs stopping.

---

## Environment variables (optional)

```dotenv
# Horizon (production VPS only)
HORIZON_PATH=horizon
HORIZON_PREFIX=
HORIZON_DOMAIN=
```

See `.env.example`. Do not enable Horizon on hosts without Redis + Linux pcntl.

---

## Acceptance mapping

| Requirement | Solution |
|-------------|----------|
| Queue backlog visible | `ops:queue-status`, Horizon dashboard (VPS) |
| Failed jobs visible | `queue:failed`, `ops:queue-status`, Horizon Failed tab |
| Worker health visible | `horizon:status`, Supervisor/systemd, `ops:queue-status` worker section |
| Ops team knows where to check | **This document** + `ops:queue-status` as first command |
