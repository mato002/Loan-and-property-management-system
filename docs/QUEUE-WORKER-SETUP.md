# Queue Worker Setup

**Phase 2 — database queue workers** | **Phase 9 — Redis queue workers** (after `ops:redis-check` passes)

This application dispatches jobs to either the **`jobs` database table** or **Redis**, depending on `QUEUE_CONNECTION`. A long-running `queue:work` process must match that driver. The Laravel scheduler (`schedule:run` cron) does **not** replace a queue worker — it only triggers scheduled Artisan commands.

| Phase | Driver | When |
|-------|--------|------|
| 2 | `database` | Default until workers stable |
| 9 | `redis` | After `php artisan ops:redis-check` passes — see `docs/REDIS-CUTOVER.md` |

---

## Prerequisites

### 1. Environment (`.env`)

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

APP_ENV=production
APP_DEBUG=false
```

`CACHE_STORE=database` is recommended so cache locks (e.g. Equity sync, scheduler `onOneServer()`) work across PHP processes.

### 2. Database tables

Ensure migrations have run:

```bash
php artisan migrate --force
```

Required tables: `jobs`, `failed_jobs`, `job_batches`.

Verify:

```bash
php artisan migrate:status | grep -E 'jobs|failed'
```

### 3. Scheduler cron (separate from queue worker)

The scheduler handles invoice automation, SMS schedule dispatch, and **failed-job pruning**. It still needs its own cron entry — see `deploy/laravel-scheduler.cron.example`:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Queued jobs in this application

All jobs use the **`default`** queue unless you change dispatch code. One worker command processes everything.

| Job | Purpose | `$tries` | `$timeout` (sec) | Backoff (sec) |
|-----|---------|----------|------------------|---------------|
| `SendEmailJob` | Property portal email to one recipient | 3 | 90 | 30, 60, 120 |
| `SendSmsJob` | Property portal SMS to one recipient | 3 | 90 | 30, 60, 120 |
| `SendBulkCommunicationJob` | Fan-out bulk message to per-recipient jobs | 3 | 180 | 60, 120, 300 |
| `SendPayrollPayslipEmailJob` | Payroll payslip email | 3 | 90 | 30, 60, 120 |
| `SendRentReminderJob` | Wrapper for `rent:send-reminders` | 2 | 600 | 300, 600 |
| `FetchEquityTransactionsJob` | Equity paybill sync (when dispatched async) | 2 | 240 | 60, 180 |
| `RefreshUtilityIntelligenceCacheJob` | Rebuild utility dashboard cache | 2 | 120 | 30, 60 |

**Dispatched from:**

- `PropertyCommunicationService` — email/SMS/bulk jobs
- `PropertyAccountingController` — payslip emails
- `PropertyUtilityChargeController` — utility cache refresh
- `SyncEquityTransactions` command — uses `dispatchSync` today (runs inline in scheduler, not via worker)

Worker-level `--tries=3` is a ceiling; each job’s `$tries` property takes precedence when lower.

---

## Recommended worker command

Use this as the production worker process (Supervisor, systemd, or manual):

```bash
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
```

| Flag | Meaning |
|------|---------|
| `database` | Use the database queue connection |
| `--sleep=3` | Wait 3 seconds when the queue is empty |
| `--tries=3` | Default max attempts (job `$tries` may override) |
| `--max-time=3600` | Restart worker after 1 hour (memory hygiene; process manager restarts it) |

Run from the project root with the **same PHP binary** as the web server.

After deploy or `.env` changes:

```bash
php artisan config:cache
php artisan queue:restart
```

`queue:restart` signals workers to exit gracefully after the current job so they reload code/config.

---

## Option A — Supervisor (recommended for VPS / dedicated server)

Supervisor keeps the worker running and restarts it on failure.

### 1. Install Supervisor

```bash
# Debian/Ubuntu
sudo apt install supervisor

# RHEL/CentOS
sudo yum install supervisor
```

### 2. Create program config

File: `/etc/supervisor/conf.d/laravel-worker.conf`

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
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

Replace:

- `/path/to/project` — absolute path to this repo
- `www-data` — user your web/PHP process runs as
- `php` — full path if needed (e.g. `/usr/bin/php8.4`)

For higher throughput, set `numprocs=2` (two parallel workers). Database queue handles this safely for independent jobs.

### 3. Enable and start

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*

# Status
sudo supervisorctl status laravel-worker:*
```

### 4. Deploy hook

After each deploy:

```bash
cd /path/to/project
php artisan config:cache
php artisan queue:restart
```

---

## Option B — systemd (recommended for modern Linux without Supervisor)

### 1. Create unit file

File: `/etc/systemd/system/laravel-queue-worker.service`

```ini
[Unit]
Description=Laravel queue worker (database)
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low --queue=high,default,low
StandardOutput=append:/path/to/project/storage/logs/worker.log
StandardError=append:/path/to/project/storage/logs/worker.log

[Install]
WantedBy=multi-user.target
```

Adjust `User`, paths, and PHP binary.

### 2. Enable and start

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-queue-worker
sudo systemctl start laravel-queue-worker

# Status / logs
sudo systemctl status laravel-queue-worker
journalctl -u laravel-queue-worker -f
```

### 3. After deploy

```bash
php artisan queue:restart
sudo systemctl restart laravel-queue-worker
```

---

## Option C — cPanel cron fallback (shared hosting)

Shared hosts often **cannot** run persistent workers. Use a cron job that processes **one job per minute** as a fallback. This is slower than a dedicated worker but ensures jobs eventually run.

### Requirements

- SSH or cPanel **Cron Jobs**
- Same PHP CLI as the app
- `QUEUE_CONNECTION=database` in production `.env`

### Cron entry (every minute)

```cron
* * * * * cd /home/USER/public_html/project && /usr/local/bin/php artisan queue:work database --stop-when-empty --sleep=3 --tries=3 --queue=high,default,low >> storage/logs/worker-cron.log 2>&1
```

| Flag | Why |
|------|-----|
| `--stop-when-empty` | Exit after the queue is drained (required for cron — do not leave a hanging process) |
| `--sleep=3` | Brief pause between jobs in the same run |

**Limitations:**

- High volume (bulk SMS/email) will backlog — upgrade to VPS + Supervisor when traffic grows
- `--max-time` is less relevant; each cron invocation is a short-lived process
- Still add the **scheduler** cron separately (`schedule:run`)

### cPanel steps

1. **Cron Jobs** → add new cron, every minute (`* * * * *`)
2. Command: full `cd ... && php artisan queue:work ...` line above
3. Ensure `storage/logs` is writable
4. Monitor `storage/logs/worker-cron.log` and `failed_jobs` table

---

## Failed job operations

Failed jobs are stored in the `failed_jobs` table (`QUEUE_FAILED_DRIVER=database-uuids` by default).

### List failures

```bash
php artisan queue:failed
```

Shows UUID, connection, queue, failed time, and exception summary.

### Retry one failure

```bash
php artisan queue:retry <uuid>
```

### Retry all failures

```bash
php artisan queue:retry all
```

### Delete all failed job records

```bash
php artisan queue:flush
```

**Warning:** `queue:flush` removes failure records only — it does not re-run the jobs. Use `queue:retry all` if you want them executed again.

### Automatic pruning (scheduled)

The scheduler runs daily at 03:15 UTC:

```bash
php artisan queue:prune-failed --hours=168
```

This deletes failed job rows **older than 7 days**. Recent failures remain for inspection. Requires `schedule:run` cron.

Manual prune:

```bash
php artisan queue:prune-failed --hours=168
```

---

## Verification checklist

Run on the server after setup:

```bash
cd /path/to/project

# Drivers
php artisan about --only=drivers

# Worker is processing (dispatch a test job or check jobs table)
php artisan queue:monitor database:default

# No unexpected backlog
# mysql: SELECT COUNT(*) FROM jobs;

# Failed jobs visible
php artisan queue:failed

# Scheduler includes prune + automation
php artisan schedule:list
```

Expected `about` drivers:

```
Queue .............. database
Cache .............. database  (recommended)
```

---

## Troubleshooting

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| Jobs stay in `jobs` table | No worker running | Start Supervisor/systemd worker or cron fallback |
| Emails/SMS never send | `QUEUE_CONNECTION=sync` on server | Set `database`, run `config:cache`, restart worker |
| Worker dies silently | PHP memory/timeout | Check `storage/logs/worker.log`; reduce batch size or increase `$timeout` on heavy jobs |
| Duplicate reminders | Multiple workers + no idempotency | Prefer one worker; rent reminders use idempotency keys |
| `failed_jobs` growing | Mail/SMS/API errors | `queue:failed`, fix root cause, `queue:retry` |
| Stale code after deploy | Worker not restarted | `php artisan queue:restart` |

---

## Related documentation

- Phase 1 inspection: `docs/PRODUCTION-READINESS-PHASE-1.md`
- Scheduler cron example: `deploy/laravel-scheduler.cron.example`
- Workflow automation status: `php artisan property:workflow-automation-status`

---

## Production operator summary

1. Set `QUEUE_CONNECTION=database` in production `.env`.
2. Run migrations so `jobs` and `failed_jobs` exist.
3. Add **scheduler** cron: `schedule:run` every minute.
4. Run **queue worker** via **Supervisor (A)**, **systemd (B)**, or **cPanel cron fallback (C)** using:

   ```bash
   php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low --queue=high,default,low
   ```

5. Inspect failures with `php artisan queue:failed`; retry with `queue:retry all`; clear records with `queue:flush`.
6. Old failures are pruned automatically after 7 days via `queue:prune-failed` on the scheduler.

---

## Redis queue worker (Phase 9)

**Prerequisite:** `php artisan ops:redis-check` exit 0 on the server. Full runbook: **`docs/REDIS-CUTOVER.md`**.

### Environment

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=database
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<secret>
REDIS_DB=0
REDIS_CACHE_DB=1
```

Run `php artisan optimize:clear && php artisan config:cache` after editing `.env`.

### Worker command

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

Deploy examples:

- Supervisor: `deploy/laravel-queue-worker-redis.supervisor.example`
- systemd: `deploy/laravel-queue-worker-redis.service.example`

Stop any **database** worker before switching to avoid duplicate processing. Drain the `jobs` table first or finish pending DB jobs with a temporary DB worker.

### Verification

```bash
php artisan ops:redis-check
php artisan ops:redis-cutover-verify
php artisan queue:failed
```

Acceptance: cutover verify exit 0, empty `failed_jobs`, worker log shows job processing.

**Phase 10 monitoring:** `docs/QUEUE-MONITORING.md` — Horizon on VPS, `ops:queue-status` for all environments.

### Monitoring

| Signal | Command |
|--------|---------|
| Worker process | `supervisorctl status` / `systemctl status laravel-queue-worker` |
| Worker log | `tail -f storage/logs/worker.log` |
| Failed jobs | `php artisan queue:failed` |
| Redis queue depth | `redis-cli LLEN` on prefixed `queues:default` key |
| Redis memory | `redis-cli INFO memory` |

After deploy: `php artisan queue:restart` then restart Supervisor/systemd program.
