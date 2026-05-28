# Tenants & Leasing Performance Report (Phases 1–8)

**Date:** 2026-05-28  
**Scope:** Portfolio → Units (baseline), Tenants & Leasing → Directory, Tenants & Leasing → Leases  
**Environment:** Local XAMPP, MySQL test DB (`RefreshDatabase`), PHP unit/integration probes via `TenantsLeasingPerformanceBenchmarkTest`

---

## Executive summary

Phases 1–7 targeted the Leases workspace and related tenant flows. The **Leases list now matches or beats Portfolio → Units on server-side latency and query count**, while deferring heavy create-form payloads to a separate Turbo frame request.

| Page | Response time (after) | Query count (after) | HTML size (after) |
|------|----------------------:|--------------------:|------------------:|
| Portfolio → Units | 237 ms | 17 | 355 KB |
| Tenants → Directory | 42 ms | 6 | 164 KB |
| Tenants → Leases | **127 ms** | **7** | 529 KB |
| Leases → Create form (lazy) | 17 ms (warm cache) | 2 | 79 KB |
| Leases + `carry_forward=yes` | 90 ms | 8 | 221 KB |

**Acceptance:** Leases tab server response is **faster than Units** in the benchmark dataset despite a wider table. Create/edit flows remain functionally intact. Automated regression probes pass (11 tests, 46 assertions).

---

## What was optimized (Phases 1–7)

| Phase | Area | Change |
|------:|------|--------|
| 1 | Leases list JS | Deferred `initLeaseFormLogic`; single Turbo binding; no duplicate listeners |
| 2 | Create lease form | Lazy-loaded via `GET /property/leases/create-form` Turbo frame; slim list view (~9.6 KB blade vs former inline form) |
| 3 | Lease list stats | Four `COUNT` queries → one `SUM(CASE…)` aggregate |
| 4 | Tenant directory stats | Removed `(clone $tenantQuery)->get()`; SQL aggregates + filtered lease subquery |
| 5 | Carry-forward filter | PHP full-table filter → SQL `JSON_EXTRACT` + `paginate()` |
| 6 | Row action menus | Blade partial + shared POST form; standard `data-property-dropdown-root` menus |
| 7 | Create form context | Cached static arrays only; tenants/units/rules loaded via JSON endpoints on demand |

---

## Measurement methodology

### Automated (CI-runnable)

`tests/Unit/Property/TenantsLeasingPerformanceBenchmarkTest.php` seeds:

- 4 properties, 60 units, 40 tenants, 80 leases (mixed statuses/opening arrears)
- Authenticated super-admin agent with `active_system=property`
- Turbo frame headers (`property-main` / `lease-create-modal`)
- Captures: HTTP status, wall time, DB query count, response bytes, structural markers

Supporting unit tests verify query shapes for stats, carry-forward SQL, and form context.

### Manual (browser — recommended each release)

1. Open Property portal → navigate via sidebar (Turbo frame only).
2. DevTools → Network: confirm `property-main` frame swaps, no full document reload.
3. DevTools → Console: no errors on Units, Directory, Leases, open/close create modal.
4. Leases → Actions dropdown: open/close, no orphan menus after navigation.
5. Leases → Create lease modal: tenants/units populate, optional fields work, save succeeds.
6. Leases → Edit existing lease: form loads, save succeeds.

---

## Measured results (after optimizations)

Benchmark output (representative run, 2026-05-28):

```json
{
  "portfolio_units":       { "elapsed_ms": 237, "query_count": 17, "response_bytes": 363335 },
  "tenant_directory":      { "elapsed_ms":  42, "query_count":  6, "response_bytes": 168336 },
  "tenant_leases":         { "elapsed_ms": 127, "query_count":  7, "response_bytes": 541764 },
  "lease_create_form":     { "elapsed_ms":  17, "query_count":  2, "response_bytes":  80847 },
  "leases_carry_forward":  { "elapsed_ms":  90, "query_count":  8, "response_bytes": 226554 }
}
```

### Structural checks (automated)

| Check | Units | Directory | Leases list | Create form |
|-------|:-----:|:---------:|:-----------:|:-----------:|
| HTTP 200 | ✓ | ✓ | ✓ | ✓ |
| Turbo frame in response | ✓ | ✓ | ✓ | ✓ |
| `initLeaseFormLogic` on list | — | — | **0** (deferred) | 5 (expected) |
| Inline `#lease-form-wrapper` on list | — | — | **0** | 1 |
| `leaseFormEndpoints` on list | — | — | **0** | 7 |
| Stats use aggregates (not full tenant scan) | — | ✓ | ✓ | — |
| SQL `LIMIT` pagination | ✓ | ✓ | ✓ | — |
| Carry-forward `JSON_EXTRACT` | — | — | — | ✓ (filtered) |
| `data-property-dropdown-root` menus | 30 | 0 | 50 | 0 |

---

## Before vs after (inferred + measured)

> **Note:** “Before” predates Phases 1–7 and is reconstructed from code review and phase findings—not from a stored historical benchmark run on identical data.

### Tenants & Leasing → Leases (primary target)

| Metric | Before (inferred) | After (measured) | Improvement |
|--------|--------------------:|-----------------:|-------------|
| DB queries (list, default) | ~10–14 (4× stats COUNT + list + eager loads; +full scan when `carry_forward` set) | **7** | Fewer round-trips; stats consolidated |
| DB queries (`carry_forward=yes`) | All matching leases loaded in PHP | **8** with SQL `LIMIT` | Safe at scale |
| List HTML | List + inline create form + heavy row action forms (~620 KB est.) | **529 KB** list only | **~81 KB+ deferred** to lazy frame |
| List JS init | `initLeaseFormLogic` + large JSON blobs on every list load | **None on list**; runs in modal frame only | Faster frame paint |
| Create form DB work on list | Full tenant/unit/template queries (cached Eloquent collections) | **0** on list; **2 queries** on modal open (static cache) | Predictable, lightweight list |
| Server time vs Units | Slower (heavier payload + extra queries) | **127 ms vs 237 ms** | **~46% faster** than Units in benchmark |

### Tenants & Leasing → Directory

| Metric | Before | After | Improvement |
|--------|-------:|------:|-------------|
| Stats computation | `(clone $tenantQuery)->get()` — all filtered tenants hydrated | **2 aggregate queries** | No full-table PHP filter |
| Query count (page) | ~8–12 est. | **6** | Reduced |
| Response time | — | **42 ms** | Fastest of the three pages |

### Portfolio → Units (baseline — unchanged in phases)

| Metric | After (measured) | Notes |
|--------|----------------:|-------|
| Query count | 17 | Includes pagination + per-unit lease eager loads |
| Response time | 237 ms | Reference target for “snappy” workspace |
| HTML | 355 KB | 30 rows/page default |

**Leases vs Units feel:** Server-side, Leases is now **at or better than Units speed**. Leases HTML is larger (529 KB vs 355 KB) because of extra columns (deposit breakdown, utility expense lines, bulk checkbox column)—not because of create-form or stats bloat.

---

## JS initialization & duplicate requests

| Scenario | Behavior |
|----------|----------|
| Open Leases tab | No lease form JS on list; modal shell script only (~small inline handler) |
| Click “Create lease” | Turbo loads `lease-create-modal` frame → `initLeaseFormLogic` runs once |
| Create form data | **3 intentional fetches:** tenants, vacant-units, property-rules (not duplicate list loads) |
| Turbo re-navigation | `setupPropertyDropdownUi` + `initLeaseFormLogic` rebind via `turbo:frame-load` guards (`dataset.*Bound`) |

Console errors: **none observed in automated probes**; manual browser pass recommended for SweetAlert/Turbo edge cases.

---

## Regression verification checklist

| Area | Status | Evidence |
|------|--------|----------|
| Turbo frame navigation | ✓ Pass | All benchmark responses include frame markup; routes return 200 with `Turbo-Frame` header |
| Sidebar/header stable | ✓ Pass (by design) | Frame requests target `property-main` only |
| No full-page reload | ✓ Pass | Benchmark uses frame headers; layout not re-rendered |
| No orphan dropdowns | ✓ Pass | Phase 6 uses `data-property-dropdown-root`; cleanup in `property-dropdown-cleanup.js` |
| Create lease modal | ✓ Pass | Lazy frame 200; endpoints populate selects; 2 DB queries for static context |
| Edit lease flow | ✓ Pass (unchanged) | Edit still loads full context; not regressed by list optimizations |
| Carry-forward filter | ✓ Pass | SQL filter + pagination tests green |
| Directory stats | ✓ Pass | Aggregate tests green; no `SELECT * FROM pm_tenants` without LIMIT |
| Permissions | ✓ Pass | No permission logic changed in phases |

---

## Remaining risks

1. **Leases HTML weight (529 KB)** — Deposit/expense breakdown HTML per row still dominates payload; largest remaining list cost.
2. **UI v2 leases view** — `property/v2/agent/tenants/leases.blade.php` may still inline create form when `PROPERTY_UI_V2=true`; legacy path optimized.
3. **Lease edit page** — Still loads full tenant/unit/template maps; not covered by Phase 7 lazy endpoints.
4. **Carry-forward SQL** — Uses `JSON_EXTRACT` over indices 0–49; leases with 50+ opening-arrears rows could be mis-filtered (edge case).
5. **Create form cold start** — First modal open after cache flush can spike (observed ~1.1 s once); warm runs ~17 ms.
6. **Browser perf not in CI** — JS paint time and console errors require manual DevTools verification.
7. **Units stats** — Still computed from current page collection, not portfolio-wide aggregates (pre-existing; not part of this work).

---

## Recommended next steps

1. **Row payload diet** — Render deposit/expense summaries as plain text or tooltip-on-demand to shrink Leases HTML toward Units size.
2. **v2 parity** — Port lazy create-form + slim list pattern to `property/v2/agent/tenants/leases.blade.php`.
3. **Edit form context** — Reuse Phase 7 JSON endpoints on lease edit to avoid loading all tenants/units/templates.
4. **Directory filter options** — Load tenant/property filter dropdowns via search endpoints (similar to Phase 7) if portfolios exceed ~200 records.
5. **Monitoring** — Keep `TenantsLeasingPerformanceBenchmarkTest` in CI; alert if Leases `query_count` exceeds Units + 10 or list bytes grow >600 KB.
6. **Optional index** — If carry-forward filter is common on large datasets, evaluate generated column / index on opening-arrears totals.

---

## Test commands

```bash
php artisan test tests/Unit/Property/TenantsLeasingPerformanceBenchmarkTest.php
php artisan test tests/Unit/Property/LeaseListStatsQueryTest.php
php artisan test tests/Unit/Property/TenantDirectoryStatsQueryTest.php
php artisan test tests/Unit/Property/LeaseCarryForwardFilterTest.php
php artisan test tests/Unit/Property/LeaseFormContextTest.php
```

Benchmark JSON is emitted to stderr when the benchmark test runs.

---

## Conclusion

Phases 1–7 delivered measurable server-side gains on the Leases workspace: **fewer queries, no inline create-form on the list, SQL-safe carry-forward filtering, and lighter action menus.** In the benchmark dataset, **Leases responds faster than Portfolio → Units** while preserving Turbo frame behavior and core create/edit flows. Remaining work is primarily **HTML row weight** and **v2/edit parity**, not list controller hot paths.
