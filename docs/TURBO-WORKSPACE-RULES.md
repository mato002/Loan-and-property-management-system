# Turbo Workspace Rules

**Phase 5 — property portal navigation standardization**

The property module uses [Hotwire Turbo](https://turbo.hotwired.dev/) with a **persistent shell** and a **single swappable workspace frame**. These rules keep navigation app-like: sidebar and header stay mounted; only the main content area updates.

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  property-v2 / property layout (full page, first load)   │
│  ┌──────────┬──────────────────────────────────────────┐ │
│  │ Sidebar  │ Header (#property-shell-header)         │ │
│  │ permanent│──────────────────────────────────────────│ │
│  │          │ Workspace scroll (#property-workspace-main)│ │
│  │          │  ┌ turbo-frame#property-main ─────────┐ │ │
│  │          │  │ Page content swaps here only        │ │ │
│  │          │  └─────────────────────────────────────┘ │ │
│  │          │ Footer (#property-shell-footer)          │ │
│  └──────────┴──────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

| Region | Element | Turbo behaviour |
|--------|---------|-----------------|
| Sidebar | `#property-shell-sidebar` | `data-turbo-permanent` — never replaced |
| Header | `#property-shell-header` | `data-turbo-permanent` — never replaced |
| Footer | `#property-shell-footer` | `data-turbo-permanent` — never replaced |
| Workspace | `turbo-frame#property-main` | Replaced on each in-app navigation |
| Loading | `#property-workspace-loading` | Overlay inside workspace scroll area only |

Implementation: `resources/js/property-portal-turbo.js`, layouts `property-v2.blade.php` / `property.blade.php`.

---

## Rules for developers

### 1. Internal `/property/` links

**Do:** Target the workspace frame.

```html
<a href="{{ route('property.tenants.directory', absolute: false) }}"
   data-turbo-frame="property-main">Tenants</a>
```

**Automatic wiring:** On each frame load, `wirePropertyFrameNavigation()` adds `data-turbo-frame="property-main"` to same-origin `/property/` links that do not opt out.

**Do not:** Use `data-turbo-frame="_top"` for in-portal navigation — that forces a **full page reload** and flashes the entire shell.

Sidebar links in `layouts/property/sidebar/*` and `_classic_sidebar_source.blade.php` already set `data-turbo-frame="property-main"`.

---

### 2. GET filter / search forms

**Do:** Submit into the workspace frame.

```html
<form method="get" action="{{ route('property.revenue.invoices') }}"
      data-turbo-frame="property-main">
```

**Filter toolbar:** `<x-property.filter-toolbar>` defaults `turboFrame="property-main"` (see `App\View\Components\Property\FilterToolbar`).

**Auto-submit filters:** Layout scripts bind debounced search on `form[method="get"]` and set `data-turbo-frame="property-main"` when missing.

**Chip remove / reset links** inside filter toolbar inherit the same frame target.

---

### 3. Exports, prints, downloads — bypass Turbo

These must **not** navigate inside `#property-main` (you would render CSV/PDF bytes inside the frame).

**Do:**

```html
<a href="{{ route('...', ['export' => 'csv']) }}" data-turbo="false">Export CSV</a>
<a href="{{ route('...') }}" target="_blank" data-turbo="false">PDF</a>
```

**Automatic detection:** `wirePropertyBypassLinks()` sets `data-turbo="false"` when the URL has:

- Query: `export`, `download`, `print`, or `format=csv|pdf|xls|…`
- Path contains: `/export`, `/print`, `/download`
- File extension: `.csv`, `.pdf`, etc.
- Link has `download` or `target="_blank"`

**Export dropdown:** `property-export-dropdowns.js` uses `window.location.href` (full navigation), not `visitPropertyMain()`.

**Filter toolbar export slot:** Wrapped in `[data-property-export-actions]`; still add explicit `data-turbo="false"` on new export links when possible.

---

### 4. POST forms (mutations)

Most POST forms inside the workspace should use `data-turbo-frame="property-main"` so validation errors render in-frame.

**Exceptions:**

- Logout: `data-turbo="false"`
- Flows that intentionally leave the portal

Turbo `submit-start` calls `ensurePropertyFormUsesMainFrame()` for in-portal POST actions.

---

### 5. Loading states (no full-page flash)

| Signal | UI |
|--------|-----|
| Frame fetch starts | Green progress bar on `#property-main` + `#property-global-nav-progress` |
| After ~120ms | `#property-workspace-loading` skeleton overlay (workspace only) |
| After ~160ms | Inline frame skeleton from `#property-frame-skeleton-template` |
| Frame load complete | All loading layers cleared; header title synced from `#property-main-route` |

Fast navigations (&lt;160ms) skip visible skeleton (anti-flash).

---

### 6. What stays synchronous (full page)

| Action | Why |
|--------|-----|
| Logout | Session teardown |
| External links | Off-origin |
| Export / print / download | Binary response |
| `data-turbo="false"` | Explicit opt-out |

---

## Blade checklist for new pages

- [ ] Page renders inside `@extends` / layout that provides `turbo-frame#property-main`
- [ ] In-page links use `data-turbo-frame="property-main"` (or rely on JS wiring)
- [ ] GET filters use `<x-property.filter-toolbar>` or `data-turbo-frame="property-main"`
- [ ] Export buttons have `data-turbo="false"`
- [ ] Print views use `print-hide` / `@media print` classes on shell (already in layout)
- [ ] Avoid `data-turbo-frame="_top"` for `/property/*` routes

---

## JavaScript helpers

| Helper | Purpose |
|--------|---------|
| `window.visitPropertyMain(url)` | Programmatic in-frame navigation |
| `wirePropertyFrameNavigation()` | Re-run after dynamic DOM injection |
| `wirePropertyBypassLinks()` | Mark export/download links |

Events to hook client UI: `turbo:frame-load`, `turbo:frame-request-started`, `turbo:submit-end`.

---

## Phase 5 changes (audit summary)

| Area | Change |
|------|--------|
| `_top` links | Replaced with `property-main` on landlord/property legacy pages (7 files) |
| Export dropdown | Full `window.location` navigation instead of frame visit |
| Bypass detection | Auto `data-turbo="false"` on export/print/download URLs |
| Frame skeleton | Injected during slow loads; template added to legacy layout |
| GET auto-filters | Explicit `data-turbo-frame="property-main"` in layout scripts |
| Filter export slot | `[data-property-export-actions]` wrapper for clarity |

---

## Verification

1. Open any agent property page with v2 layout.
2. Click sidebar items — **sidebar/header should not flicker**.
3. Apply a list filter — **only table/workspace updates**.
4. Click Export CSV — **browser download**, not HTML inside frame.
5. Watch `#property-workspace-loading` during slow network (DevTools throttling).

```bash
# Optional: find remaining _top frame targets
rg 'data-turbo-frame="_top"' resources/views/property
```

---

## Related docs

- [BACKGROUND-JOBS-ROADMAP.md](BACKGROUND-JOBS-ROADMAP.md)
- [SCHEDULER-SETUP.md](SCHEDULER-SETUP.md)

---

*Do not redesign pages or change business logic when applying these rules — navigation attributes and Turbo wiring only.*
