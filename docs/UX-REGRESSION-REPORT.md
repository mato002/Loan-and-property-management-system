# Property Portal — UX Regression Report (Phase 7)

**Date:** 2026-05-25  
**Scope:** Interaction architecture stability after Phases 0–6 (filters, responsive tables, action menus, bulk bar, mobile density).  
**Method:** Static code audit against acceptance criteria, route verification (`php artisan route:list --path=property`), Blade compile (`php artisan view:cache`), and targeted polish fixes. Manual browser pass at **360px** and **1280px** is recommended before release.

---

## Pages tested (code + route audit)

| Page | Route | Mobile cards | Filter drawer | Bulk bar | Notes |
|------|-------|--------------|---------------|----------|-------|
| Dashboard | `property.dashboard` | N/A (custom layout) | N/A | N/A | KPI grid 2-up; charts 1-col mobile; tables use `property-table-scroll` |
| Property list | `property.properties.list` | Yes | Legacy drawer | No | Stats 2-up via workspace `stat-card-grid` |
| Units | `property.properties.units` | Yes | Legacy drawer | No | Quick-action strips; compact panels |
| Leases | `property.tenants.leases` | Yes | `filter-toolbar` (client filters) | Yes | `legacy-toolbar=false`; bulk on leases tab |
| Invoices | `property.revenue.invoices` | Yes | `filter-toolbar` | Yes | Dense summary stats |
| Payments | `property.revenue.payments` | Yes | `filter-toolbar` | Yes | Dense primary stats |
| Utilities | `property.revenue.utilities` | Tabs/cards | `filter-toolbar` | Partial | Workspace tabs; reading bulk separate |
| Accounting hub | `property.accounting.index` | N/A | N/A | N/A | Compact card grid + quick actions |
| Chart of accounts | `property.accounting.gl.chart_accounts` | No (tree/table) | Legacy drawer | No | Action menus on rows |
| Settings hub | `property.settings.index` | Hub 2-up | N/A | N/A | `hub-grid` mobile density |

Additional accounting/settings child routes were checked for route registration only (no Blade breakage). Permissions and middleware unchanged.

---

## Verification checklist

| Criterion | Result | Evidence |
|-----------|--------|----------|
| No horizontal **page** overflow | Pass (with fixes) | `main` uses `overflow-x-hidden`; `#property-main` `max-width:100%`; wide tables wrapped in `property-table-scroll` |
| Filters compact on mobile | Pass | `filter-toolbar` bottom sheet at `z-[6500]`; legacy pages use `mobile-filter-drawer` |
| Mobile filter drawer works | Pass | Alpine `filterOpen`; Escape + Turbo visit closes; Apply submits GET form |
| Tables → cards where expected | Pass | `responsive-cards` on list, units, leases, invoices, payments; `is_bulk_select` on mobile cards |
| Tables scroll safely elsewhere | Pass | `table-wrapper` + `property-table-scroll`; dashboard tables given `min-w-[*]` inside scroll |
| Modals do not auto-close incorrectly | Pass | Close on navigation (`turbo:before-*`) and backdrop/Escape only; lease `<details>` not closed by dropdown cleanup |
| Action menus cleared on navigation | Pass | `closeAllPropertyDropdowns()` on Turbo visit/render/cache/frame |
| Bulk actions work | Pass | `property-bulk-actions.js`; invoices, payments, leases, notices, arrears selection |
| Chat/advisor does not cover actions | Pass | Advisor `z-30`; bulk bar `z-35` above advisor; positioned `bottom: max(4.5rem, …)` |
| Footer does not cover content | Pass | `property-mobile-safe-bottom` padding; extra padding when `property-bulk-active` |
| Sidebar drawer on mobile | Pass | Backdrop `z-[5500]`, panel `z-[5600]`; below modals/filters |

---

## Issues fixed in Phase 7

1. **Bulk bar + footer padding** — Combined `html.property-bulk-active` with `property-mobile-safe-bottom` so stacked fixed UI (footer + advisor + bulk bar) does not clip list content on mobile.
2. **Bulk apply loading state** — Reset `aria-busy` / disabled state after Turbo frame reload so the Apply button does not stay stuck.
3. **Dashboard table overflow** — Remaining `overflow-x-auto` blocks wrapped with `property-table-scroll` and explicit `min-w-[*]` on tables (scroll inside panel, not page).
4. **Accounting dashboard density** — Charts/alerts use `compact-card-grid` + `property-compact-panel`; drill-down links use `quick-action-grid`.
5. **Workspace empty/footer** — Tighter mobile padding on empty states and footer slot via `property-compact-panel`.
6. **`#property-main` width guard** — `max-width:100%; min-width:0` on frame and workspace wrap to reduce stray overflow from nested grids.
7. **Utilities shell actions** — Recreated `utilities.blade.php` (file had binary encoding corruption) and aligned header actions with `quick-action-grid`.

---

## Remaining risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| **Property list / units** still use legacy filter drawer, not unified `filter-toolbar` | Low | Drawer works; migrate later for chip parity with invoices |
| **Notices / arrears** row actions still use inline `<details>` menus | Medium | Teleported `action-menu` migration reduces z-index clashes |
| **Utilities readings bulk** uses separate patterns (not workspace bulk bar) | Low | Documented; tab-specific UX |
| **Accounting report pages** wide tables without mobile cards | Medium | Expected; rely on horizontal scroll — test PDF/export on phone |
| **Manual 360px pass** not automated in CI | Medium | Run checklist below before deploy |
| **Binary/corrupted Blade files** (utilities was affected) | High if recurring | Re-save UTF-8; avoid zip tools that break encoding |

---

## Recommended manual test script (360px + desktop)

1. Open each URL in the table above; confirm no horizontal page scrollbar.
2. **Filters:** Open mobile drawer → change field → Apply → confirm Turbo frame updates; Reset clears chips.
3. **Cards:** On invoices, select rows on mobile cards → bulk bar appears → Apply (confirm SweetAlert).
4. **Menus:** Open row action menu → navigate away → menu must not reappear on new page.
5. **Modals:** Open lease sub-modal → scroll inside → must not close until backdrop/Escape/navigation.
6. **Advisor:** With bulk bar visible, tap advisor FAB — bulk bar should remain usable; content scrolls above footer.

---

## Recommended next improvements

1. Migrate **property list**, **units**, and **chart of accounts** to `<x-property.filter-toolbar>` for one filter UX.
2. Replace remaining `<details>` row menus (notices, arrears tenant list) with `<x-property.action-menu>`.
3. Add **mobile cards** to high-traffic accounting lists (entries, journal batches) or dedicated compact row layouts.
4. Add **Playwright** smoke tests: filter drawer open/close, bulk select count, dropdown closed after `turbo:visit`.
5. Centralize **touch target** utilities (`min-h-[44px]`) on legacy toolbar buttons in list/units forms.

---

## Backend / permissions

- No routes renamed or removed in Phase 7.
- Bulk endpoints unchanged: `revenue.invoices.bulk`, `revenue.payments.bulk`, `leases.bulk`, `tenants.notices.bulk`.
- `php artisan view:cache` succeeds after polish changes.

---

## Sign-off

| Area | Status |
|------|--------|
| Mobile-first density | Ready |
| Desktop stability | Ready |
| Critical interaction bugs | None identified in audit |
| Release gate | Manual 360px pass on checklist above |
