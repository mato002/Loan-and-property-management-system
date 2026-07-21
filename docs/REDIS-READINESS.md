# Redis Readiness — Phase 8

**Date:** 2026-05-28  
**Scope:** Prepare Redis infrastructure and diagnostics. **Do not switch production to Redis until `php artisan ops:redis-check` passes and you follow the cutover checklist below.**  
**Related:** `docs/CACHE-STRATEGY.md`, `docs/QUEUE-WORKER-SETUP.md`, `docs/PRODUCTION-READINESS-PHASE-1.md`

---

## Executive summary

This phase adds **documentation and diagnostics only**. The app continues to use **`CACHE_STORE=database`** (or `file` locally) and **`QUEUE_CONNECTION=database`** until you manually change `.env` after all checks pass.

Redis improves:

- Shared cache and locks across PHP-FPM workers (scheduler mutexes, Equity sync locks, dashboard version bumps)
- Lower-latency queue dispatch vs polling the `jobs` table
- Optional session storage on a dedicated VPS

Redis introduces **new failure modes** (single point of availability, memory limits, persistence misconfiguration). Treat cutover as a planned maintenance step.

---

## Prerequisites checklist

| Requirement | Status in repo | Notes |
|-------------|----------------|-------|
| Redis server | **Ops / hosting** | Install Redis 6+ on VPS; bind to localhost or private network; set `requirepass` in production |
| PHP client | **Configured** | `ext-redis` (phpredis) **or** `predis/predis` (Composer) — see `config/database.php` `REDIS_CLIENT` |
| `config/database.php` | **Present** | `default` (DB 0) + `cache` (DB 1) connections |
| `config/cache.php` | **Present** | `redis` store → `REDIS_CACHE_CONNECTION=cache`, locks on `default` |
| `config/queue.php` | **Present** | `redis` connection → `REDIS_QUEUE_CONNECTION=default`, queue name `default` |
| `config/session.php` | **Supports redis** | Optional; keep `database` until cache/queue are stable |

### PHP client choice

| Client | Pros | Cons |
|--------|------|------|
| **phpredis** (`REDIS_CLIENT=phpredis`) | Fastest; Laravel default | Requires PECL/extension on server |
| **predis** (`REDIS_CLIENT=predis`) | Pure PHP; works without extension | Slightly higher CPU; fine for typical portal load |

The project includes **`predis/predis`** in `composer.json` as a fallback when phpredis is unavailable (common on shared hosting). Match `REDIS_CLIENT` to what is actually installed.

---

## Environment profiles (recommended)

**Do not edit `.env` automatically.** Use these as targets when you are ready.

### Local development (XAMPP / single machine)

```dotenv
CACHE_STORE=file
# or CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Redis is **optional** locally. Run `ops:redis-check` only if you install Redis (e.g. Memurai/WSL/Docker) and want to rehearse production.

### Production VPS (dedicated VM, Redis installed)

**After** `ops:redis-check` passes on the server:

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
# or SESSION_DRIVER=database if you prefer DB sessions first
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<strong-secret>
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_CACHE_CONNECTION=cache
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
```

Then: `php artisan config:cache`, restart queue workers (`queue:work redis`), verify scheduler mutexes — see cutover checklist.

### Shared / cPanel hosting

**Avoid Redis** unless your host explicitly provides Redis with persistent TCP access and a supported PHP client.

Stay on:

```dotenv
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Database cache is slower but **shared across workers** on hosts without Redis. See Phase 1 production notes.

---

## Configuration reference

### Redis connections (`config/database.php`)

| Connection | Env DB key | Default DB | Used for |
|------------|------------|------------|----------|
| `default` | `REDIS_DB` | 0 | Queue payload connection, cache locks (`lock_connection`) |
| `cache` | `REDIS_CACHE_DB` | 1 | Laravel `Cache::store('redis')` entries |

Prefix: `{app-slug}-database-` from `REDIS_PREFIX` / `APP_NAME` — prevents key collisions if multiple apps share one Redis instance.

### Cache store (`config/cache.php`)

```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
    'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
],
```

When `CACHE_STORE=redis`, scheduler `withoutOverlapping()` / `onOneServer()` and app locks (e.g. Equity sync) use Redis instead of `cache_locks` table.

### Queue connection (`config/queue.php`)

```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
],
```

Workers must use **`php artisan queue:work redis`** (or Supervisor program with `redis` connection) after cutover — not `database`.

### Sessions (optional)

If `SESSION_DRIVER=redis`, sessions use the default Redis connection unless `SESSION_CONNECTION` is set. **Recommendation:** migrate cache + queue first; add session Redis only after 24–48h stable operation, or keep `SESSION_DRIVER=database`.

---

## Diagnostic command

```bash
php artisan ops:redis-check
```

Machine-readable output:

```bash
php artisan ops:redis-check --json
```

### What it tests

| Check | Purpose |
|-------|---------|
| Environment profile | Prints current `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`, `REDIS_CLIENT` (informational) |
| PHP Redis client | phpredis extension or predis/predis package |
| Default connection | Ping `REDIS_DB` (default 0) |
| Read/write | SET/GET/DEL probe key on default connection |
| Cache connection | Ping cache DB (`REDIS_CACHE_DB`, default 1) |
| Cache lock | `Cache::store('redis')->lock()` acquire/release |
| Queue readiness | Ping queue Redis connection; validates `queue.connections.redis` config |

**Important:** The command talks to Redis **directly** — it does not require `CACHE_STORE=redis` or `QUEUE_CONNECTION=redis` in `.env`. Use it to validate infrastructure **before** cutover.

Exit code `0` = all checks passed; non-zero = fix Redis/client/network before changing production env.

---

## Cutover checklist (production VPS only)

Complete in order. **Stop if any step fails.**

1. Install and harden Redis (bind address, password, `maxmemory-policy`, persistence policy documented for your SLA).
2. Install phpredis **or** ensure `composer install` includes `predis/predis`; set `REDIS_CLIENT` accordingly.
3. Set Redis env vars in `.env` (host, password, DB numbers) — **keep** `CACHE_STORE=database` and `QUEUE_CONNECTION=database` for now.
4. Run `php artisan ops:redis-check` — must pass with zero failures.
5. Maintenance window: set `CACHE_STORE=redis`, run `php artisan config:cache`.
6. Smoke-test: login, dashboard, lease form, utility dashboard; confirm cache invalidation still works.
7. Set `QUEUE_CONNECTION=redis`; update Supervisor/systemd to `queue:work redis`; drain old `jobs` table if needed.
8. Monitor Redis memory, connected clients, and failed queue jobs for 24–48h.
9. Optionally set `SESSION_DRIVER=redis` (or keep database).
10. Document rollback: revert `.env` to database drivers, `config:cache`, restart workers.

**Phase 9 cutover runbook:** `docs/REDIS-CUTOVER.md` — switch `CACHE_STORE` / `QUEUE_CONNECTION`, worker command, and acceptance via `php artisan ops:redis-cutover-verify`.

---

## Risks and mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Redis down | Cache misses → more DB load; queue stops; sessions lost if on Redis | Health checks; keep rollback env handy; consider Redis persistence/replica on critical VPS |
| Wrong `REDIS_CLIENT` | Connection errors at boot | Run `ops:redis-check`; align client with extension/package |
| Shared Redis, no DB split | Cache flush wipes queue keys | Keep `REDIS_DB=0` and `REDIS_CACHE_DB=1` (defaults in this repo) |
| `CACHE_STORE=redis` without worker restart | Stale config, mixed drivers | Always `config:cache` + restart PHP-FPM and queue workers |
| cPanel “Redis” plugin | May be socket-only or non-persistent | Prefer database cache/queue unless TCP Redis is confirmed |
| Memory eviction | Silent cache loss | Set `maxmemory-policy`; monitor `used_memory`; size TTLs per `CACHE-STRATEGY.md` |
| Auth scope in queued jobs | Utility warm job may lack user context | Documented in `CACHE-STRATEGY.md` — fix before relying on Redis queue for auth-scoped jobs |

---

## What this phase did **not** do

- Did **not** change `.env` or production `CACHE_STORE` / `QUEUE_CONNECTION`
- Did **not** enable Laravel Horizon (Phase 10 — see `docs/QUEUE-MONITORING.md`)
- Did **not** migrate existing `cache` / `cache_locks` table data to Redis (version keys rebuild naturally)
- Did **not** change application business logic

---

## Quick reference

```bash
# Verify readiness (safe anytime)
php artisan ops:redis-check

# After manual .env cutover only
php artisan config:cache
php artisan queue:restart
```

For cache key inventory and invalidation rules, see **`docs/CACHE-STRATEGY.md`**.  
For worker processes after queue cutover, see **`docs/QUEUE-WORKER-SETUP.md`**.
