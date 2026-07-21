# Property Portal — UX / Interaction Architecture

Phase 0 reference for z-index layering, overlay stacking, and workspace slot contracts. Visual redesigns are out of scope here.

## Z-index tokens

| Layer | Token / value | Used by |
|-------|----------------|---------|
| Table action menus (teleported) | `9999` | `<x-property.action-menu>` via `property-dropdown-cleanup.js` |
| FAB / advisor chip | `z-30` | `layouts/property.blade.php` |
| Legacy inline modals | `z-40`–`z-90` | Older agent pages (migrate to `<x-property.modal>`) |
| Property header | `z-[5000]` | `layouts/property.blade.php` |
| Sidebar mobile backdrop | `z-[5500]` | Agent sidebar overlay |
| Sidebar panel | `z-[5600]` | Collapsible nav |
| Filter drawers | `z-[6500]` | `<x-property.filter-toolbar>` mobile sheet, legacy `<x-property.responsive.mobile-filter-drawer>`, public filter drawer |
| Standard modals | `z-[7010]` | `<x-property.modal>` default |
| Nested modals | `z-[7110]` | `<x-property.modal nested>` |
| Global search autocomplete | `z-[99999]` | Header search |

**Rule:** New property overlays should use the table above. Do not add ad-hoc `z-50` modals inside `#property-main` without teleport.

## Header / sidebar layering

```
[page content]
  → sticky workspace bulk bar (z-20)
  → teleported table menus (9999)
  → filter drawer (6500)
  → modal stack (7010+)
  → sidebar backdrop (5500) / panel (5600)
  → header (5000)
```

Sidebar and header stay below modals and filter drawers so filters and dialogs remain usable on mobile.

## Workspace slot contract (`<x-property.workspace>`)

| Slot | Required | Rendered where |
|------|----------|----------------|
| (default) | No | Below table when both table + slot content exist; otherwise replaces table area |
| `actions` | No | Top-right header actions |
| `above` | No | KPIs / forms above the table wrap |
| `toolbar` | No | Filter forms; legacy pages wrap in mobile drawer; migrated pages use `<x-property.filter-toolbar>` |
| `mobile_filters_extra` | No | Legacy only — extra filters in the old mobile drawer (prefer `filter-toolbar` `dateRange` slot) |
| `table_actions` | No | Sticky bulk/action bar directly above table/cards (`data-workspace-table-actions`) |
| `footer` | No | Pagination / notes below table |

### Props (common)

- `tableMinWidth` — passed to `<x-property.responsive.table-wrapper>`; numeric values become `px`.
- `showSearch` — client-side table search when columns are set.
- `legacyToolbar` — when `false`, `toolbar` renders as-is (no legacy drawer wrapper). Set on pages using `<x-property.filter-toolbar>`.
- `responsiveCards` — when `true`, renders `<x-property.responsive.mobile-record-list>` below `md` and hides the table on small screens.
- `columnConfig` — optional column metadata for mobile cards (`App\Support\Property\ResponsiveTableColumns` presets). Keys: `mobile_label`, `priority`, `hide_on_mobile`, `is_primary`, `is_subtitle`, `is_status`, `is_amount`, `is_action`, `is_bulk_select`.
- `stats`, `columns`, `tableRows`, `tableFooterRow`, `tableRowFilters` — built-in table mode.

### JS lifecycle

- `property-workspace-ui.js` — row click navigation, dropdown rebind scoped to `#property-main`.
- `property-export-dropdowns.js` — collapses multiple export links in GET forms.
- `property-dropdown-cleanup.js` — action menus only; does not close unmarked `<details>` form panels.

## Action menu (`<x-property.action-menu>`)

Standard row/header action dropdown. Markup:

- `details.group` with menu panel `absolute right-0` under the trigger (inline in the cell — same pattern as property list)
- Wrapper uses `data-row-ignore-click` so workspace row navigation does not steal clicks

Legacy teleported menus (`data-property-dropdown-root`) are deprecated; `property-dropdown-cleanup.js` still cleans up any remaining teleported nodes on Turbo navigation.

Migrated: units list, invoices, payments, chart of accounts; property show uses `unit_row_actions` partial.

Legacy `data-dropdown-root` / `data-dropdown-menu` remain supported during migration.

## Dropdown cleanup scope

`closeAllPropertyDropdowns()` closes only marked action menus (`data-property-dropdown-root`, legacy `data-dropdown-root`) and teleported menu nodes.

Unmarked `<details>` (lease forms, filter “More filters”, record-payment panels) are **not** closed.

## Filter toolbar (`<x-property.filter-toolbar>`)

Migrated list pages: leases, utility charges, invoices, payments.

| Slot | Purpose |
|------|---------|
| `primary` | Search, status, sort, per-page (inline on desktop) |
| `secondary` | “More filters” popover (desktop) / grouped section (mobile drawer) |
| `dateRange` | Issue/received period controls (invoices & payments) |
| `export` | Export dropdown |
| `bulk` | Optional bulk UI (prefer `table_actions` for row bulk) |

Props: `action`, `resetUrl`, `submitFilters` (false for client-side `data-table-filter`), `chipLabels`, `chipExclude` (default drops `sort`, `dir`, `per_page`, `page`, `export`), `revenueDateFilter` (`invoices` \| `payments` for date-clear script).

Field primitive: `<x-property.filter-field>` (`text`, `search`, `select`, `date`, `month`, `number`, `hidden`, `date-range`, `custom`).

Mobile: one **Filters** button → bottom sheet with **Apply filters** + **Reset** (GET). Chips render below the toolbar row.

## Legacy mobile filters

Non-migrated pages may still use `mobile_filters_extra` + the workspace drawer wrapper (`legacyToolbar` default `true`).

## Mobile density grids (Phase 6)

Shared CSS in `resources/css/app.css`. Prefer components over ad-hoc `grid-cols-*` for KPI strips.

| Component | Class | Mobile | Tablet (`md`) | Desktop (`xl`) |
|-----------|-------|--------|---------------|----------------|
| `<x-property.responsive.stat-card-grid>` | `.stat-grid` | 2 columns | 3 columns | 4 columns |
| Same + `dense` prop | `.stat-grid-dense` | 2 columns (tighter padding/gaps) | 3 | 4 |
| `<x-property.responsive.kpi-card-grid>` | `.kpi-card-grid` | 2 columns; tap whole card (`md:hidden` overlay) | 3 | 4 |
| `<x-property.responsive.quick-action-grid>` | `.quick-action-grid` | `auto-fit minmax(90px, 1fr)` | flex-wrap from `md` | flex-wrap |
| `<x-property.responsive.compact-card-grid>` | `.compact-card-grid` | 1 column (charts, tables, forms) | 1 | 2–3 from `lg` (`lgCols` prop) |
| `<x-property.hub-grid>` | `.hub-grid` | 2 columns | 2 | 3 |

Panel padding utility: `.property-compact-panel` → `p-3` / `md:p-5` / `lg:p-6`.

KPI cards hide the “View” footer band on mobile (`kpi-card-link max-md:hidden`); the card body is still tappable.

Utilities summary KPIs use `<x-property.utility.compact-kpi-strip>` which delegates to `stat-card-grid` with `dense`.

## Responsive table / mobile cards

List workspaces with `responsiveCards` enabled:

| Page | Preset |
|------|--------|
| Property list | `ResponsiveTableColumns::propertyList()` |
| Units | `ResponsiveTableColumns::units()` |
| Leases / expiry | `ResponsiveTableColumns::leases($tab)` |
| Invoices | `ResponsiveTableColumns::invoices()` |
| Payments | `ResponsiveTableColumns::payments()` |

Desktop: table inside `<x-property.responsive.table-wrapper>` (`overflow-x-auto`, min-width). Mobile: stacked cards with primary title, subtitle, amount/status chips, meta fields, and touch-friendly action footer. Row navigation uses `data-row-href` on both `<tr>` and `<article>` (`property-workspace-ui.js`).

## Regression testing (Phase 7)

See [UX-REGRESSION-REPORT.md](./UX-REGRESSION-REPORT.md) for page-by-page verification, fixes applied, and remaining risks.

## Lease form sub-modals

Create/edit lease pages use `<x-property.modal>` for:

- Utilities, deposits & terms (`showOptionalFieldsModal`)
- Carry-forward / opening arrears (`showOpeningArrearsModal`)
- Add charge line (`showArrearsLineModal`, nested z-index `7110`)
- Add charge type on edit (`showChargeTypeModal`)

Teleported fields stay associated with the main form via `<fieldset form="lease-form-wrapper">` (or `lease-edit-form-wrapper`). Vanilla helpers: `resources/js/lease-form-modals.js` (`openLeaseSubmodal`, `revealLeaseField`).
