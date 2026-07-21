# Redis Cutover — Phase 9

**Date:** 2026-05-28  
**Prerequisite:** `php artisan ops:redis-check` must exit **0** on the target server before any step below.  
**Related:** `docs/REDIS-READINESS.md`, `docs/CACHE-STRATEGY.md`, `docs/QUEUE-WORKER-SETUP.md`

---

## Executive summary

Phase 9 switches **cache** and **queues** from database/file drivers to Redis. **Do not start Phase 9** until Phase 8 diagnostics pass on production.

**Session driver:** keep **`SESSION_DRIVER=database`** until Redis has run stable for 24–48 hours with monitored memory and persistence. Only then consider `SESSION_DRIVER=redis`.

This document is the operator runbook. Acceptance is verified with:

```bash
php artisan ops:redis-cutover-verify
```

---

## Pre-flight (must pass)

On the **production VPS** (not local XAMPP unless Redis is installed):

```bash
php artisan ops:redis-check
```

Fix any failure before continuing. Common fixes:

| Failure | Fix |
|---------|-----|
| `ext-redis` missing, predis installed | Set `REDIS_CLIENT=predis` in `.env` |
| Connection refused | Start Redis (`systemctl start redis`), check bind/password |
| Cache lock fail | Confirm `REDIS_CACHE_DB=1` separate from queue DB 0 |

---

## Step 1 — Update `.env` (manual)

Edit production `.env` only after pre-flight passes. **Do not commit `.env`.**

```dotenv
# Redis client — match what is installed
REDIS_CLIENT=phpredis
# or REDIS_CLIENT=predis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<your-redis-password>
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CACHE_CONNECTION=cache
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default

# Phase 9 cutover
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Session: keep database until Redis is proven stable
SESSION_DRIVER=database
```

**Why keep sessions on database initially:** Redis restart or memory eviction without persistence can log users out en masse. Cache and queue tolerate brief Redis unavailability differently than sessions.

**When to move sessions to Redis:** after 48h stable operation, persistence policy documented, and `ops:redis-cutover-verify` still passes daily.

---

## Step 2 — Clear and rebuild config

Run on the server during a short maintenance window:

```bash
php artisan optimize:clear
php artisan config:cache
```

Confirm cached config:

```bash
php artisan tinker --execute="echo config('cache.default').' '.config('queue.default');"
# Expected: redis redis
```

Restart PHP-FPM (or Apache mod_php) so web workers pick up config:

```bash
sudo systemctl reload php8.3-fpm
# or your stack equivalent
```

---

## Step 3 — Stop database worker, start Redis worker

If a **database** queue worker is running, stop it first to avoid double-processing during transition.

**Drain or migrate pending jobs:** jobs already in the `jobs` table are **not** auto-migrated to Redis. Either:

1. Let the old `queue:work database` process finish the `jobs` table, **then** switch `.env` and start Redis worker, or  
2. Accept that pending DB jobs run once more with a temporary DB worker after cutover (keep `QUEUE_CONNECTION=database` until drained, then switch).

Recommended: **drain first**, then cutover.

### Manual worker (testing)

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

### Production (Supervisor)

Copy and edit `deploy/laravel-queue-worker-redis.supervisor.example`:

```bash
sudo supervisorctl stop laravel-worker:*
# update config to queue:work redis
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

After deploys always:

```bash
php artisan queue:restart
```

See `docs/QUEUE-WORKER-SETUP.md` (Redis section) and `deploy/laravel-queue-worker-redis.service.example` for systemd.

---

## Step 4 — Verify acceptance

```bash
php artisan ops:redis-check
php artisan ops:redis-cutover-verify
php artisan queue:failed
```

| Command | Expected |
|---------|----------|
| `ops:redis-check` | Exit 0, all checks pass |
| `ops:redis-cutover-verify` | Exit 0, cache probe OK, no failed jobs |
| `queue:failed` | Empty table (or investigate each failure) |

Smoke-test in browser: login, dashboard, send test email/SMS job, utility dashboard refresh.

---

## Step 5 — Monitor jobs and logs

### First 24–48 hours

| What | How |
|------|-----|
| Worker alive | `supervisorctl status` or `systemctl status laravel-queue-worker` |
| Worker log | `tail -f storage/logs/worker.log` |
| App log | `tail -f storage/logs/laravel.log` |
| Failed jobs | `php artisan queue:failed` (schedule already prunes stale failures) |
| Redis memory | `redis-cli INFO memory` — watch `used_memory_human`, `maxmemory` |
| Queue depth | `redis-cli LLEN queues:default` (prefix may vary — see `REDIS_PREFIX`) |

### Scheduled verification (optional cron)

```cron
0 */6 * * * cd /path/to/project && php artisan ops:redis-cutover-verify >> storage/logs/redis-cutover-verify.log 2>&1
```

Alert if exit code ≠ 0.

---

## Rollback (if cache/queue/redis unstable)

1. Stop Redis queue worker.
2. Revert `.env`:

   ```dotenv
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   SESSION_DRIVER=database
   ```

3. `php artisan optimize:clear && php artisan config:cache`
4. Restart database queue worker: `queue:work database --sleep=3 --tries=3 --max-time=3600`
5. Restart PHP-FPM.

Cache entries rebuild from DB queries; version-bump keys repopulate on next request (see `CACHE-STRATEGY.md`).

---

## Acceptance checklist

- [ ] `ops:redis-check` passed **before** `.env` change
- [ ] `CACHE_STORE=redis` and `QUEUE_CONNECTION=redis` in production `.env`
- [ ] `SESSION_DRIVER=database` (or redis only after stability review)
- [ ] `optimize:clear` + `config:cache` run; PHP-FPM reloaded
- [ ] Redis queue worker running (`queue:work redis`)
- [ ] `ops:redis-cutover-verify` exit 0
- [ ] `queue:failed` empty
- [ ] No session logout spikes / auth errors in logs
- [ ] Monitoring in place for worker + Redis memory

---

## Local development note

XAMPP without a Redis server **cannot complete Phase 9**. Keep local `.env` on `file`/`database` drivers and use `ops:redis-check` only when Redis is installed (Memurai, WSL, or Docker). Production cutover happens on the VPS.

**After Phase 9 is stable:** enable queue monitoring per **`docs/QUEUE-MONITORING.md`** (Horizon on Linux VPS).
