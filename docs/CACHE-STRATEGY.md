# Cache Strategy — Phase 7

**Date:** 2026-05-28  
**Scope:** Document current Laravel cache usage with **existing drivers only** (`database` / `file` per environment). **Do not switch to Redis in this phase.**  
**Goal:** Define what is cached, how invalidation works, what must stay uncached, and which entries are safe to migrate to Redis later.

---

## Executive summary

The property portal uses a **small, intentional cache surface**:

1. **Version-bump invalidation** for dashboard overview and lease form static config (`PropertyDashboardCache`).
2. **Short TTL read caches** for agent dashboard aggregates (60s) and utility intelligence dashboards (600s).
3. **Operational caches** (invoice idempotency, Equity API token, geo autocomplete, distributed locks).

Reports, audit trail workspace stats, chart-of-accounts balances, and most workspace list pages are **not cached** — they run live queries on every request (correct for financial accuracy and filter freshness).

Production should use **`CACHE_STORE=database`** (not `file`) so scheduler mutexes (`withoutOverlapping`, `onOneServer`), Equity sync locks, and cache entries are shared across PHP workers. See `docs/PRODUCTION-READINESS-PHASE-1.md`.

---

## Current drivers (no Redis yet)

| Setting | Production target | Notes |
|---------|-------------------|--------|
| `CACHE_STORE` | `database` | Shared across workers; backs `cache` + `cache_locks` tables |
| `CACHE_PREFIX` | `{app-slug}-cache-` | From `config/cache.php` — all keys below are logical names; Laravel adds prefix |
| Local dev | Often `file` | OK for single developer; breaks multi-worker mutex semantics |

**Not in scope for Phase 7:** Redis connection, cache tags, Horizon, or new cache layers.

---

## Agent / user scoping rules

All caching must respect workspace boundaries:

| Role | `AgentWorkspaceScope::shouldApply()` | Query behaviour |
|------|-------------------------------------|-----------------|
| Property agent | `true` | Eloquent global scopes on `Property`, `PropertyUnit`, `PmTenant`, `PmInvoice`, `PmPayment`, `PmWaterReading`, etc. restrict rows to `properties.agent_user_id = auth()->id()` |
| Super admin | `false` | Sees portfolio-wide counts (overview key uses `:all` segment) |
| Landlord / tenant / guest | N/A | These portals do not use the caches listed below |

**Rules for new caches:**

- Include **`agent_user_id` or `user_id`** in the key whenever the payload is derived from scoped models.
- Never cache **another agent’s** portfolio under a shared key.
- Do **not** cache raw PII blobs (message bodies, credentials, full export rows) — only aggregates or static config.
- Queue workers that warm cache **must pass explicit agent scope into queries** — do not rely on `Auth::user()` inside jobs (see utility intelligence note below).

---

## Cache map (keys, TTLs, scope)

Logical keys below omit the `CACHE_PREFIX` Laravel adds automatically.

### A. Version metadata (forever until bumped)

| Key | TTL | Purpose |
|-----|-----|---------|
| `property.dashboard.cache_version` | Forever (`Cache::forever`) | Integer version embedded in overview cache keys |
| `property.leases.form_context_version` | Forever | Integer version embedded in lease form static config keys |

Bumping a version **does not delete old rows** in the cache table; old entries expire by TTL or become unreachable because the key string changes.

### B. Property dashboard overview

| Item | Value |
|------|--------|
| **Service** | `PropertyDashboardOverview::forAgent()` |
| **Key pattern** | `property.dashboard.overview:{userId}:{agent\|all}:v{version}` |
| **TTL** | **60 seconds** |
| **Scoped by** | Authenticated `userId` + whether agent filter applies (`agent` vs `all`) |
| **Payload** | KPI counts, YTD chart series, arrears buckets, recent maintenance/payments/leases, landlord link samples, SMS wallet summary, system health flags |
| **Built via** | Many `count()` / `sum()` queries on scoped Eloquent models; nested calls to `PropertyDashboardStats` |

**Nested cache (same request path):**

| Key | TTL | Scoped? | Notes |
|-----|-----|---------|-------|
| `property.dashboard.sms_provider_balance` | **300 seconds** | **Global — not per agent** | External BulkSms `providerBalance()`; cleared on `forgetAll()`. Acceptable only if one provider account serves all agents. |

### C. Lease form static context (not tenant/unit lists)

| Item | Value |
|------|--------|
| **Service** | `PmLeaseWebController::leaseFormStaticContext()` |
| **Key pattern** | `property.leases.form_context:v{version}` |
| **TTL** | **300 seconds** |
| **Scoped by** | **Global portal settings** (not per agent) |
| **Payload** | `leaseTemplate`, `leaseFields` config, `openingArrearsTypeOptions` only |
| **Explicitly not cached** | Tenant list, vacant units, property rules — loaded via AJAX (`formTenants`, `formVacantUnits`, `formPropertyRules`) per request |

This design avoids stale tenant/unit assignment data while caching expensive **settings parsing**.

### D. Utility intelligence dashboard

| Item | Value |
|------|--------|
| **Service** | `UtilityIntelligenceService::dashboard()` |
| **Key pattern** | `utility_intelligence:{agentUserId}:{propertyId\|all}:{months}` |
| **TTL** | **600 seconds** (10 minutes) |
| **Scoped by** | `agentUserId` in key; row scope via `PmWaterReading` global scope when `Auth` is agent |
| **Payload** | Anomaly detection, trends, heatmaps, KPIs over water readings |
| **Warm job** | `RefreshUtilityIntelligenceCacheJob` — forgets then rebuilds via `dashboard()` |

**Invalidation gap (document, do not ignore):** `forgetCache()` only clears keys where the property segment is `'all'`. Keys like `utility_intelligence:{agentId}:{propertyId}:{months}` are **not** forgotten on reading save; they rely on TTL unless manually refreshed.

**Queue worker gap:** `buildDashboard()` loads readings through scoped models. If the queue worker runs **without** an authenticated agent user, scope may not apply and a warm job could cache the wrong portfolio. Fix before relying on async warm in production (pass explicit property-unit filter by `agent_user_id` in the job).

### E. Invoice create idempotency

| Item | Value |
|------|--------|
| **Controller** | `PmInvoiceController` |
| **Key pattern** | `pm_invoice_idem:{userId}:{idempotencyKey}` |
| **TTL** | **10 minutes** |
| **Scoped by** | User ID + client idempotency header |
| **Purpose** | Prevent duplicate invoice rows on double-submit — not a performance cache |

### F. Kenya address autocomplete

| Item | Value |
|------|--------|
| **Controller** | `PropertyGeoController::suggestKenyaAddresses()` |
| **Key pattern** | `ke_addr_suggest:{md5(q\|city)}` |
| **TTL** | **5 minutes** |
| **Scoped by** | None (public Nominatim results) |
| **Safe** | No user or financial data |

### G. Equity Bank integration

| Key | TTL | Purpose |
|-----|-----|---------|
| `equity_api_access_token` | Token lifetime minus 60s | OAuth access token reuse |
| `equity_api_unconfigured_warned_at` | 1 hour | Log noise throttle when API not configured |
| `equity-sync-lock` | 240 seconds (lock) | `Cache::lock` — prevents concurrent Equity sync jobs |

Equity caches are **integration-global**, not agent-scoped (single bank credentials per deployment).

### H. In-process settings cache (not Laravel cache)

| Mechanism | Location | Invalidation |
|-----------|----------|--------------|
| `PropertyPortalSetting::$valueCache` | PHP static array per request/worker | Cleared only on `setValue()` in same process; `getValue()` hits DB once per key per process |

`PropertyPortalSetting::setValue()` also calls `PropertyDashboardCache::forgetAll()` so dashboard + lease form version keys bump.

---

## Invalidation triggers

### Version bump — `PropertyDashboardCache::forgetAll()`

Called from:

| Trigger | Location |
|---------|----------|
| Model saved / deleted / restored | `PropertyDashboardCacheObserver` on: `PmInvoice`, `PmPayment`, `PmLease`, `PmTenant`, `Property`, `PropertyUnit`, `PmMaintenanceRequest`, `PmMaintenanceJob`, `UnassignedPayment`, `ExpenseDefinition`, `DepositDefinition` |
| Portal setting changed | `PropertyPortalSetting::setValue()` |

Effects:

- Increments `property.dashboard.cache_version` → all overview keys rotate.
- Increments `property.leases.form_context_version` → lease static config keys rotate.
- Deletes `property.dashboard.sms_provider_balance`.

### Lease form context only — `PropertyDashboardCache::forgetLeasesFormContext()`

- Bumps `property.leases.form_context_version` only.
- Also invoked inside `forgetAll()`.
- Use when lease **field config** changes without a full dashboard invalidation (today only tests call this directly).

### Utility intelligence

| Trigger | Behaviour |
|---------|-----------|
| Water reading saved / bulk utility action | `PropertyUtilityChargeController::refreshUtilityIntelligence()` → `forgetCache(agentId)` + dispatch `RefreshUtilityIntelligenceCacheJob` |
| TTL expiry | 600s natural expiry for untouched keys |
| Manual | Same controller path |

**Not wired:** `PmWaterReading` model observer does **not** bump utility cache; invalidation depends on controller paths above.

### Operational / short-lived (no version bump)

| Event | Cache action |
|-------|----------------|
| Duplicate invoice submit | Read/write `pm_invoice_idem:*` |
| Equity token fetch | Read/write `equity_api_access_token` |
| Geo suggest repeat query | `ke_addr_suggest:*` hit |

### What does **not** invalidate caches today

- Accounting journal batches / audit trail entries
- Chart of accounts structural changes (unless they touch observed models)
- Report filter changes
- Communication logs
- `php artisan cache:clear` is **not** used in app code — prefer version bumps for property caches to avoid flushing unrelated keys (Equity token, idempotency, locks)

---

## Expensive uncached queries (candidates — not implemented)

These run on every page load or export. **Do not add blind caching** without agent-scoped keys and explicit invalidation.

| Area | Location | Cost driver |
|------|----------|-------------|
| **Agent dashboard** | `PropertyDashboardOverview::buildForAgent()` | 20+ aggregate queries (counts, sums, grouped monthly chart, recent lists) — **already cached 60s** |
| **Revenue workspace stats** | `RevenueController`, `PropertyDashboardStats` | MTD collected, outstanding, arrears buckets — live on payments/invoices pages |
| **Chart of accounts** | `PropertyAccountingController::chartOfAccounts()` | Full account list + `AccountingJournalLine` + `PmAccountingEntry` aggregations per account |
| **Audit trail workspace** | `PropertyAccountingController::auditTrail()` | Paginated batches + **5 separate count clones** of filtered query + `loadBatchLineSummaries()` per page; export loads **5000** batches |
| **Tenant statements** | `PropertyReportsController::tenantStatements()` | Up to **500 tenants** with eager-loaded invoices/payments; per-tenant sums in PHP |
| **Landlord statements** | `PropertyReportsController::landlordStatements()` | `LandlordReportService` allocation + expense breakdown |
| **Report hub** | `PropertyReportsController::reportDefinitions()` | Aging balance, cash book, utility aging, maintenance history, etc. — all uncached builders |
| **Lease list stats** | `PmLeaseWebController` | Filtered lease query stats on leases workspace |
| **Utility intelligence (miss)** | `UtilityIntelligenceService::buildDashboard()` | Full reading history + anomaly engine — **cached 600s on hit** |
| **Communications / maintenance workspaces** | Various controllers | Stats computed from filtered collections in memory |

**Recommendation priority if caching is added later (still on database driver first):**

1. Audit trail **summary stats only** (not row payloads) — key: `audit_trail.stats:{agentId}:{filterHash}`, TTL 30–60s, invalidate on journal batch write.
2. Chart of accounts **summary totals** for filtered agent — key includes filter hash, TTL 120s, invalidate on journal line post.
3. Report hub **heavy builders** only when invoked with identical filters — never cache default “export” streams.

---

## Do not cache (without redesign)

| Data | Reason |
|------|--------|
| Tenant/unit pickers for lease create | Assignment correctness; already AJAX live |
| Payment reversal / invoice detail views | Financial state must be current |
| User-specific permission outcomes | Security |
| Full audit trail / statement **export** bodies | Large, sensitive, filter-specific |
| Session, CSRF, flash messages | Framework-managed |
| Turbo/HTML fragments | Stale UI risk |

---

## Known gaps and risks (fix before scaling cache)

1. **Utility `forgetCache()` incomplete** — Does not clear per-`property_id` keys; stale property-filtered dashboards until TTL.
2. **Utility warm job + Auth** — `RefreshUtilityIntelligenceCacheJob` should scope queries by `$this->agentUserId` explicitly, not only via `Auth`.
3. **SMS provider balance key is global** — Fine for single-tenant SMS; wrong if agents bring their own BulkSms credentials later.
4. **Lease form context key is global** — Fine while `PropertyPortalSetting` is deployment-wide; wrong for per-agent branding/templates.
5. **Overview TTL 60s + broad observer** — Any observed model change busts **all** agents’ overview caches (version bump). Correct for accuracy; noisy under bulk imports — acceptable tradeoff on database cache.
6. **`PropertyPortalSetting::$valueCache`** — Can serve stale setting reads in long-lived queue workers until process restart; only affects code paths that bypass `setValue()`.

---

## Redis migration roadmap (later — Phase 8+)

When Redis is approved, migrate in this order:

| Priority | Cache / lock | Why Redis helps |
|----------|--------------|----------------|
| 1 | Scheduler mutex + `equity-sync-lock` | Lower latency locks; required for reliable `onOneServer()` at scale |
| 2 | `property.dashboard.overview:*` | High read frequency on agent home; version bump stays same pattern |
| 3 | `utility_intelligence:*` | Large payloads; many key variants; fix forget + scope first |
| 4 | `property.dashboard.sms_provider_balance` | Rate-limit external API; optional per-agent keys |
| 5 | Geo suggest | Optional; low priority |

**Keep on database cache (or no cache):**

- `pm_invoice_idem:*` — short TTL, correctness-critical, low volume
- Equity token — works on database; Redis slightly faster but not urgent
- Report exports — never move to shared cache; stream from DB

**Redis features to adopt when migrating:**

- Same key strings (prefix still applied)
- Optional `Cache::tags(['agent:{id}'])` for utility intelligence once property-scoped forget is implemented
- Do **not** use Redis as a session store in the same phase unless planned separately

---

## Operations cheat sheet

| Task | Command / action |
|------|------------------|
| Inspect driver | `php artisan about` → Cache |
| Clear all Laravel cache (disruptive) | `php artisan cache:clear` — also clears Equity token, idempotency |
| Preferred property invalidation | Save any observed model or change portal setting — version bump |
| Lease form config only | `PropertyDashboardCache::forgetLeasesFormContext()` |
| Utility refresh | Save water reading (controller path) or dispatch `RefreshUtilityIntelligenceCacheJob` |
| Prune stale DB cache rows | Laravel 11+ scheduled prune if configured; old version keys expire by TTL |

---

## Acceptance checklist (Phase 7)

| Criterion | Status |
|-----------|--------|
| Cache map exists with keys and TTLs | ✅ This document |
| No blind caching guidance | ✅ Explicit do-not-cache list + scoping rules |
| Expensive uncached queries identified | ✅ Table above |
| Redis migration path documented | ✅ Roadmap section |
| Agent/user scope documented | ✅ Scoping rules + per-key notes |
| Uses current drivers only | ✅ No Redis code changes in Phase 7 |

---

## Related docs

- `docs/PRODUCTION-READINESS-PHASE-1.md` — `CACHE_STORE`, scheduler mutex requirements
- `docs/BACKGROUND-JOBS-ROADMAP.md` — `RefreshUtilityIntelligenceCacheJob`
- `docs/QUEUE-WORKER-SETUP.md` — queue worker + cache driver alignment
- `tests/Unit/Property/LeaseFormContextTest.php` — lease static context must remain arrays-only
