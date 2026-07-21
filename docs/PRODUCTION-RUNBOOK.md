# Production Runbook — Phase 11

**Date:** 2026-05-28  
**Audience:** Server operators deploying and running this Laravel application (property portal + loan module).  
**Goal:** One guide for deploy, daily ops, incident response, and rollback — no guessing.

**Deep dives (linked, not duplicated):**

| Topic | Document |
|-------|----------|
| Scheduler details | [SCHEDULER-SETUP.md](SCHEDULER-SETUP.md) |
| Queue workers | [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md) |
| Queue monitoring / Horizon | [QUEUE-MONITORING.md](QUEUE-MONITORING.md) |
| Redis readiness | [REDIS-READINESS.md](REDIS-READINESS.md) |
| Redis cutover | [REDIS-CUTOVER.md](REDIS-CUTOVER.md) |
| Cache strategy | [CACHE-STRATEGY.md](CACHE-STRATEGY.md) |
| Phase 1 inspection | [PRODUCTION-READINESS-PHASE-1.md](PRODUCTION-READINESS-PHASE-1.md) |
| **Artisan command cheat sheet** | [OPS-COMMAND-REFERENCE.md](OPS-COMMAND-REFERENCE.md) |

---

## Before you deploy

Confirm on the **target server**:

| Check | Command / action |
|-------|------------------|
| PHP ≥ 8.3, required extensions | `php -v` — `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` |
| Composer | `composer --version` |
| Node (build machine or server) | `node -v` — only needed where `npm run build` runs |
| MySQL/MariaDB reachable | `.env` `DB_*` correct |
| `.env` exists (never commit it) | Copy from `.env.example`, set `APP_KEY` |
| Cron installed | `crontab -l \| grep schedule:run` |
| Queue worker or Horizon running | See [§6 Queue worker setup](#6-queue-worker-setup) |
| `public/` is web root | Not project root |
| No `public/hot` in production | Delete if present — forces compiled `/build/*` assets |

**Production `.env` baseline** (database drivers — default path):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-production-host
APP_TIMEZONE=Africa/Nairobi

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

After Redis Phase 9 cutover: `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` — see [REDIS-CUTOVER.md](REDIS-CUTOVER.md).

---

## Standard deployment procedure

Run from the project root (`/path/to/project`). Replace paths and user as needed.

### 1. Deployment commands

```bash
cd /path/to/project

# --- Code update (pick one) ---
git fetch --tags
git checkout main && git pull origin main
# OR: rsync/scp release artifact into place

# --- Backup before change (see §8) ---
# mysqldump ... && tar storage/app ...

# --- PHP dependencies (production) ---
composer install --no-dev --optimize-autoloader --no-interaction

# --- Frontend assets ---
npm ci
npm run build

# --- Laravel ---
php artisan migrate --force
php artisan storage:link          # first deploy only; safe if link exists
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Restart async workers (pick one) ---
php artisan queue:restart         # database or redis queue:work
# OR after Horizon:
php artisan horizon:terminate

# --- Reload PHP (example) ---
sudo systemctl reload php8.3-fpm
# Apache: sudo systemctl reload apache2
```

**Verify deploy:**

```bash
php artisan about --only=environment,cache,drivers
curl -sf "$APP_URL/up" && echo OK
php artisan ops:queue-status
tail -20 storage/logs/laravel.log
```

Delete **`public/hot`** if it exists — Vite dev server marker breaks production assets.

---

## 2. Migrate command

```bash
php artisan migrate --force
```

| Situation | Command |
|-----------|---------|
| Check pending migrations | `php artisan migrate:status` |
| Production (non-interactive) | Always use `--force` |
| **Never** on production without backup | `migrate:fresh`, `migrate:refresh`, `db:wipe` |

Required tables include: `users`, `sessions`, `cache`, `cache_locks`, `jobs`, `failed_jobs`, `job_batches`, plus application migrations.

If migrate fails: **stop**, restore DB from backup (§8), fix migration, retry — do not leave half-applied schema.

---

## 3. Build assets command

Production uses **compiled** Vite assets, not `npm run dev`.

```bash
npm ci
npm run build
```

Output: `public/build/` (manifest + hashed JS/CSS).

| Problem | Fix |
|---------|-----|
| Unstyled site / 404 on `/build/*` | Run `npm run build`; remove `public/hot` |
| Wrong host loading dev assets | Set `APP_URL` to public HTTPS URL; rebuild |
| Mobile APK / Capacitor | See [MOBILE-ANDROID.md](MOBILE-ANDROID.md) |

---

## 4. Optimize / cache commands

**After every `.env` change:**

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

| Command | Purpose |
|---------|---------|
| `optimize:clear` | Clears config, route, view, event caches + compiled files |
| `config:cache` | Required in production for performance and consistent env |
| `route:cache` | Speeds routing; skip temporarily if route cache errors after deploy |
| `view:cache` | Pre-compiles Blade views |

**Do not run `config:cache` on local dev** if you edit `.env` frequently — operators on production only.

Optional one-liner:

```bash
php artisan optimize
```

(Runs config + route + view cache; still run `optimize:clear` first after env changes.)

---

## 5. Scheduler cron

The scheduler **does not** send queued email/SMS by itself — it only runs Artisan commands on a timetable. You still need a queue worker (§6).

**Add one crontab line** (same `php` as web stack):

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Reference: `deploy/laravel-scheduler.cron.example`

**Verify:**

```bash
crontab -l | grep schedule:run
php artisan schedule:list
php artisan schedule:run -v          # manual test
tail -50 storage/logs/scheduler.log
php artisan property:workflow-automation-status
```

**Important:** Set `APP_TIMEZONE` (e.g. `Africa/Nairobi`) before relying on `dailyAt()` times — see [SCHEDULER-SETUP.md](SCHEDULER-SETUP.md).

If automation appears stuck after a crash:

```bash
php artisan cache:clear    # clears stuck withoutOverlapping mutexes
php artisan schedule:run
```

---

## 6. Queue worker setup

Pick **one** path matching `QUEUE_CONNECTION` in `.env`.

### A — Database queue (default / cPanel)

```bash
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
```

Supervisor: `deploy/laravel-queue-worker.supervisor.example`  
systemd: `deploy/laravel-queue-worker.service.example`

After deploy: `php artisan queue:restart`

### B — Redis queue (Phase 9, without Horizon)

```bash
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=high,default,low
```

Supervisor: `deploy/laravel-queue-worker-redis.supervisor.example`

Pre-flight: `php artisan ops:redis-check` must pass — [REDIS-READINESS.md](REDIS-READINESS.md)

### C — Redis + Horizon (Phase 10, Linux VPS)

Replaces standalone `queue:work`:

```bash
php artisan horizon
```

Supervisor: `deploy/laravel-horizon.supervisor.example`  
Dashboard: `https://{APP_URL}/horizon` (super admin only)

After deploy: `php artisan horizon:terminate`

**Queue priorities:**

| Queue | Jobs |
|-------|------|
| `high` | Email, SMS |
| `default` | Bulk comms, payslip email, utility cache |
| `low` | Rent reminders, Equity sync job |

Full detail: [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md) and [QUEUE-MONITORING.md](QUEUE-MONITORING.md).

---

## 7. Redis checks

Run on the server **before** Redis cutover and **periodically** after.

```bash
php artisan ops:redis-check
php artisan ops:redis-cutover-verify    # after CACHE_STORE=redis + QUEUE_CONNECTION=redis
```

| Exit code | Meaning |
|-----------|---------|
| `0` | All checks passed |
| non-zero | Fix client, connection, or server before switching production drivers |

Common fixes: install Redis, set `REDIS_CLIENT=predis` if phpredis missing, set `REDIS_PASSWORD`, separate `REDIS_DB=0` / `REDIS_CACHE_DB=1`.

Cutover steps: [REDIS-CUTOVER.md](REDIS-CUTOVER.md) — **do not** change `.env` until `ops:redis-check` passes.

---

## 8. Backup commands

No automated backup script ships with the app — schedule these at OS level.

### Database

```bash
# MySQL / MariaDB — adjust credentials and path
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "/backups/${DB_NAME}-$(date +%F-%H%M).sql.gz"
```

Keep off-server copies (S3, second VPS, NAS). Test restore quarterly.

### Uploaded files

```bash
tar -czf "/backups/storage-app-$(date +%F).tar.gz" -C /path/to/project storage/app
```

Include `storage/app/public` (tenant documents, exports) if not on S3.

### Environment and config snapshot

```bash
cp /path/to/project/.env "/backups/env-$(date +%F).bak"
```

**Before every deploy:** DB dump + note current git commit (`git rev-parse HEAD`).

### Restore (summary)

1. Enable maintenance mode (§15).
2. Restore DB: `gunzip -c backup.sql.gz | mysql -u ... dbname`
3. Restore `storage/app` if needed.
4. Checkout known-good code tag.
5. `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force` (only if forward migrations needed).
6. `php artisan optimize:clear && php artisan config:cache`
7. Restart workers; `php artisan up`.

---

## 9. Rollback procedure

Use when a deploy causes errors, failed payments, or broken UI.

### Code rollback (preferred — no schema downgrade)

```bash
cd /path/to/project
php artisan down --secret="ops-$(date +%s)" --refresh=15

git log -5 --oneline                    # find previous good commit/tag
git checkout <previous-tag-or-commit>

composer install --no-dev --optimize-autoloader
npm ci && npm run build                 # if assets changed in bad deploy

php artisan migrate --force             # only adds new migrations; safe if no new migrations in bad deploy
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart               # or horizon:terminate
sudo systemctl reload php8.3-fpm

php artisan up
curl -sf "$APP_URL/up"
php artisan ops:queue-status
```

### Redis / queue driver rollback

Revert `.env` to database drivers:

```env
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Then:

```bash
php artisan optimize:clear && php artisan config:cache
# Stop Horizon; start database queue worker
```

See [REDIS-CUTOVER.md](REDIS-CUTOVER.md) rollback section.

### Database migration rollback

**Avoid** `migrate:rollback` in production unless the migration was just applied and a DBA approved reverse steps.

Safer: restore DB from pre-deploy dump (§8).

### Rollback checklist

- [ ] Maintenance mode on during rollback
- [ ] Previous git tag checked out
- [ ] `.env` restored if changed
- [ ] Assets rebuilt if JS/CSS deploy included
- [ ] Workers restarted
- [ ] `/up` returns 200
- [ ] `ops:queue-status` — no growing backlog
- [ ] `queue:failed` reviewed
- [ ] Maintenance mode off

---

## 10. Log locations

| Log | Path | Contents |
|-----|------|----------|
| Application | `storage/logs/laravel.log` | Exceptions, job failures, integrations |
| Daily rotate | `storage/logs/laravel-YYYY-MM-DD.log` | When `LOG_STACK=daily` |
| Scheduler | `storage/logs/scheduler.log` | Billing/SMS automation stdout |
| Queue worker | `storage/logs/worker.log` | `queue:work` Supervisor output |
| Horizon | `storage/logs/horizon.log` | Horizon master process |
| Web server | `/var/log/nginx/error.log` or Apache equivalent | 502, timeout, TLS |
| PHP-FPM | `/var/log/php8.3-fpm.log` | Slow requests, crashes |

**Live tail (SSH):**

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/scheduler.log
tail -f storage/logs/worker.log
```

**Local dev:** `php artisan pail`

**Search production errors:**

```bash
grep -i "error\|exception\|failed" storage/logs/laravel-$(date +%F).log | tail -50
```

---

## 11. Failed job handling

Failed jobs are stored in the **`failed_jobs`** table (`database-uuids` driver).

```bash
php artisan queue:failed
php artisan queue:failed --json
php artisan queue:retry <uuid>
php artisan queue:retry all
php artisan queue:flush              # deletes all failed records — use with care
php artisan ops:queue-status
```

| Step | Action |
|------|--------|
| 1 | `ops:queue-status` — count and recent UUIDs |
| 2 | `queue:failed` — read exception + payload |
| 3 | Fix root cause (mail creds, SMS API, code bug) |
| 4 | `queue:retry <uuid>` |
| 5 | Confirm job processed — backlog decreases |

**Auto-prune:** scheduler runs `queue:prune-failed --hours=168` daily at 03:15 app time — failures older than 7 days are removed.

**Horizon (Redis):** Failed tab at `/horizon` — same underlying `failed_jobs` table.

---

## 12. Webhook testing

All webhook routes are **POST**, CSRF-exempt, public internet reachable over **HTTPS**.

### Property payment SMS ingest

**URL:** `POST /webhooks/property/payments/sms-ingest`

**Auth (pick one):**

- Header `X-Agent-Forwarder-Token: pm-agent-...` (per-agent token from portal settings)
- Header `X-Property-Sms-Secret: {PROPERTY_SMS_INGEST_SECRET}`

```bash
curl -sS -X POST "https://YOUR_HOST/webhooks/property/payments/sms-ingest" \
  -H "Content-Type: application/json" \
  -H "X-Property-Sms-Secret: YOUR_SECRET" \
  -d '{"provider":"mpesa","payer_phone":"254712345678","amount":1500,"provider_txn_code":"TEST123","raw_sms":"test"}'
```

Expect JSON with `ok: true` or validation error — not 401/500.

### Loan payment SMS ingest

**URL:** `POST /webhooks/loan/payments/sms-ingest`  
**Secret:** `LOAN_SMS_INGEST_SECRET` (falls back to property secret if blank)

### Property STK callback (app format)

**URL:** `POST /webhooks/property/payments/stk-callback`

Used by property-layer STK integrations — test with payload matching pending `PmPayment` meta.

### Bank callbacks

**URL:** `POST /webhooks/property/payments/bank/{provider}`  
Providers: `kcb`, `equity`, `coop`  
Secrets: `PROPERTY_BANK_KCB_WEBHOOK_SECRET`, `PROPERTY_BANK_EQUITY_WEBHOOK_SECRET`, `PROPERTY_BANK_COOP_WEBHOOK_SECRET`

### SMS delivery / inbound (communications)

| URL | Purpose |
|-----|---------|
| `POST /webhooks/property/communications/sms-delivery` | Delivery receipts |
| `POST /webhooks/property/communications/sms-inbound` | Inbound SMS |

Check controller auth in `PropertyCommunicationWebhookController` before testing.

### Webhook checklist

- [ ] URL uses HTTPS (Safaricom and mobile apps require it)
- [ ] No WAF blocking POST body
- [ ] Secret headers match `.env` after `config:cache`
- [ ] Response time &lt; 30s (Safaricom timeout)
- [ ] `storage/logs/laravel.log` shows no 500 on test POST
- [ ] Test row appears in payments / ingest tables as expected

**Local dev:** use ngrok or similar — `APP_URL` must match registered callback URLs.

---

## 13. M-Pesa callback checks

### Daraja STK (pay-in)

| Item | Value |
|------|--------|
| Callback URL | `https://YOUR_HOST/webhooks/mpesa/stk-callback` |
| `.env` | `MPESA_STK_CALLBACK_URL` must match public URL |
| Controller | `MpesaDarajaWebhookController@stkCallback` |

**Verify configuration:**

```bash
grep MPESA_ .env
php artisan tinker --execute="echo config('services.mpesa.stk_callback_url');"
```

**Functional test:**

1. Initiate STK from tenant/landlord payment UI (sandbox or production credentials).
2. Confirm pending `PmPayment` with `channel=mpesa_stk` exists.
3. After M-Pesa prompt, check payment status updated (settled or failed).
4. Log: no unhandled exception in `laravel.log`.

**Simulate callback (sandbox only):**

```bash
curl -sS -X POST "https://YOUR_HOST/webhooks/mpesa/stk-callback" \
  -H "Content-Type: application/json" \
  -d '{"Body":{"stkCallback":{"CheckoutRequestID":"ws_CO_123","ResultCode":0,"ResultDesc":"Success","CallbackMetadata":{"Item":[{"Name":"Amount","Value":1},{"Name":"MpesaReceiptNumber","Value":"TEST001"},{"Name":"TransactionDate","Value":20260528120000}]}}}}'
```

Replace `CheckoutRequestID` with value from pending payment meta (`meta.daraja.checkout_request_id`).

### Daraja B2C (loan disbursement)

| Item | Value |
|------|--------|
| Result URL | `https://YOUR_HOST/webhooks/mpesa/b2c-result` |
| `.env` | `MPESA_B2C_RESULT_URL`, `MPESA_B2C_TIMEOUT_URL` (same endpoint) |

Check loan module **M-Pesa payouts** screen for displayed callback URL.

### M-Pesa incident checklist

- [ ] `MPESA_ENV`, consumer key/secret, shortcode, passkey correct
- [ ] Callback URLs registered in Safaricom portal match `.env`
- [ ] `MPESA_VERIFY_SSL=true` in production
- [ ] Server clock accurate (NTP)
- [ ] Pending payments not stuck — query `pm_payments` where `status=pending` and channel `mpesa_stk`
- [ ] SMS forwarder path still works if STK down (`/webhooks/property/payments/sms-ingest`)

---

## 14. Storage permissions

Web server user (e.g. `www-data`, `apache`, `nginx`) must write:

```
storage/
bootstrap/cache/
```

**Typical Linux VPS:**

```bash
cd /path/to/project
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

**First deploy:**

```bash
php artisan storage:link
```

Creates `public/storage` → `storage/app/public` for user-uploaded files.

| Symptom | Likely cause |
|---------|----------------|
| 500 on every request after deploy | `bootstrap/cache` not writable — run permissions above |
| Uploads fail | `storage/app` not writable |
| Broken profile images | Missing `storage:link` |
| Log not written | `storage/logs` not writable |

**Never** `chmod 777` in production — use group ownership (`www-data` + deploy user in same group if needed).

---

## 15. Emergency maintenance mode

Take the site offline for rollback or DB restore while keeping a bypass URL for operators.

### Enable

```bash
php artisan down --secret="deploy-recovery-token" --refresh=15
```

- Public users see maintenance page.
- Operators with secret: `https://YOUR_HOST/deploy-recovery-token` (sets bypass cookie).

**Render JSON for API clients:**

```bash
php artisan down --secret="deploy-recovery-token" --render="errors::503"
```

### Disable

```bash
php artisan up
```

### When to use

- Failed deploy / white screen
- Database restore in progress
- Security incident investigation
- Redis or queue misconfiguration causing data risk

Always pair with status communication to users. Re-run `/up` and smoke tests before announcing recovery.

---

## Daily / weekly operator routine

| Frequency | Task |
|-----------|------|
| Daily | `curl -sf $APP_URL/up` (or external monitor) |
| Daily | `php artisan ops:queue-status` |
| Daily | `tail` scheduler log around billing windows |
| Weekly | `php artisan queue:failed` |
| Weekly | `php artisan property:workflow-automation-status` |
| Weekly | Disk space on `storage/` and DB server |
| After Redis cutover | `ops:redis-cutover-verify`, Horizon dashboard |
| Monthly | Test DB restore from backup |

---

## Quick command index

```bash
# Deploy
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart

# Health
curl -sf "$APP_URL/up"
php artisan about --only=environment,cache,drivers
php artisan ops:queue-status
php artisan ops:redis-check

# Incidents
php artisan down --secret="TOKEN"
php artisan up
php artisan queue:failed
php artisan queue:retry all
tail -f storage/logs/laravel.log
```

---

## Acceptance

This runbook satisfies Phase 11 when operators can:

- Deploy using §1–§4 without ad-hoc steps
- Configure cron (§5) and workers (§6)
- Validate Redis (§7) before cutover
- Back up and roll back (§8–§9)
- Find logs (§10), fix failed jobs (§11), test webhooks and M-Pesa (§12–§13)
- Fix permissions (§14) and use maintenance mode (§15)

For changes to automation behaviour, toggles, or queue architecture, update the linked phase docs and keep this file’s command paths in sync.
