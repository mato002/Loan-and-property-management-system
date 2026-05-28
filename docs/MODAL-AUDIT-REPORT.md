# Property Management System — Modal Architecture Audit Report

**Date:** 2026-05-25  
**Scope:** `resources/views/property/**`, layouts, JS bundles  
**Goal:** Identify root causes of modal auto-close, focus loss, z-index conflicts, and mobile breakage; standardize on one system.

---

## Executive Summary

The Property portal runs **four parallel overlay systems** with no shared lifecycle API. The primary bugs are architectural, not isolated typos:

| Root cause | Impact |
|------------|--------|
| `@click.outside` on modal **panels** | Native `<select>` dropdowns render outside the panel DOM → modal closes while picking options |
| Inconsistent z-index (many modals at `z-40`–`z-50`) | Modals render **behind** mobile sidebar (`z-[5600]`) |
| Duplicate next-steps modals | Layout + page both render from `session('next_steps')` → double overlay |
| No Turbo dismiss on most modals | Stale open state after frame navigation |
| Modals inside `turbo-frame#property-main` | Parent `overflow-hidden` clips overlays; no teleport |
| Dropdown cleanup capture-phase clicks | Global outside-click handler competes with modal interactions |
| No reference-counted scroll lock | Body scroll leaks or fights layout `overflow-hidden` |
| Vanilla JS modals without stack management | ESC closes wrong layer; nested modals conflict |

**No Bootstrap, Livewire, Select2, TomSelect, or Flatpickr** were found in the property module.

---

## Phase 1 — Modal Inventory

### Reusable components

| Component | Path | Stack | Z-index |
|-----------|------|-------|---------|
| **NEW** `<x-property.modal>` | `resources/views/components/property/modal.blade.php` | Alpine + teleport | 7010 (configurable) |
| Next steps | `components/property/next-steps-modal.blade.php` | Alpine → now uses `<x-property.modal>` | 7010 |
| Quick-create select | `components/property/quick-create-select.blade.php` | Alpine → now uses `<x-property.modal>` | 7010 |
| Mobile filter drawer | `components/property/responsive/mobile-filter-drawer.blade.php` | Alpine slide-up | 6500 |
| Public filter drawer | `components/public/filter-drawer.blade.php` | Alpine slide-up | 6500 |
| Breeze modal (auth only) | `components/modal.blade.php` | Alpine | 50 — **not used in property** |
| Swal flash | `components/swal-flash.blade.php` | SweetAlert2 | dynamic |

### Inline Alpine overlays (agent)

| File | Purpose | Z-index | Status |
|------|---------|---------|--------|
| `settings/system_setup/access.blade.php` | Role/permission modals | 7010/7110 | **Migrated** to `<x-property.modal>` |
| `properties/show.blade.php` | Add unit | 7010 | **Migrated** |
| `tenants/directory.blade.php` | Duplicate next-steps | z-40 | **Removed** (use layout component) |
| `landlords/index.blade.php` | Duplicate next-steps | z-40 | **Removed** |
| `vendors/directory.blade.php` | Duplicate next-steps | z-40 | **Removed** |
| `equity/unmatched_payments.blade.php` | Print preview | z-70 | Needs migration |
| `tenants/leases.blade.php` | Lease create modals (3) | z-120–130 | Needs migration |
| `tenants/lease_edit.blade.php` | Lease edit modals (4) | z-70–90 | Needs migration; dead `#charge-type-modal` wiring |

### Vanilla JS toggles (`hidden`/`flex`)

| File | Modal ID | Z-index |
|------|----------|---------|
| `accounting/audit_trail.blade.php` | `#audit-preview-modal` | z-50 |
| `accounting/chart_accounts.blade.php` | `#coa-create-modal`, `#coa-disable-modal` | z-50 |
| `properties/units.blade.php` | `#unit_meta_modal` | z-50 |
| `properties/edit_unit.blade.php` | `#unit_meta_modal` (duplicate) | z-50 |
| `revenue/payments.blade.php` | `#payment-reversal-modal` | z-50 |
| `reports/tenant/statements.blade.php` | `#tenant-statement-modal` | z-70 |
| `reports/landlord/statements.blade.php` | 2 modals | z-70/75 |

### Expandable panels (not full-screen, lower risk)

`revenue/invoices.blade.php`, `properties/list.blade.php`, `tenants/directory.blade.php` (inline forms), maintenance pages — use `x-show` for in-page panels, not `fixed inset-0`.

### Tenant / Landlord portals

No fixed overlays found in tenant or landlord views.

---

## Phase 2 — Root Causes

### 1. Click outside issues

**Primary bug:** `@click.outside` on the inner panel (not the backdrop).

Affected files (property):
- `access.blade.php` — **fixed**
- `properties/show.blade.php` — **fixed**

Loan module still uses `@click.outside` / `@click.away` extensively (out of scope but same pattern).

**Correct pattern** (now in `<x-property.modal>`):
```blade
<div class="absolute inset-0" @click="close()"></div>  {{-- backdrop --}}
<div @click.stop> ... panel ... </div>                   {{-- never @click.outside --}}
```

### 2. Livewire re-render

**Not applicable** — zero `wire:model` / Livewire components in property views.

### 3. Alpine state reset

Turbo frame replacement destroys Alpine `x-data` on navigation. Modals inside `#property-main` lose state when the frame re-renders.

**Mitigation:** `PropertyModalManager.closeAll()` on Turbo lifecycle events; `x-teleport="body"` escapes frame DOM.

### 4. Z-index problems

```
z-40/z-50     → legacy inline modals (BELOW sidebar)
z-[5000]      → property header
z-[5500]      → sidebar mobile backdrop
z-[5600]      → sidebar panel
z-[6500]      → filter drawers
z-[7010]      → standardized modals (NEW)
z-[7110]      → nested modals (NEW)
z-[99999]     → global search autocomplete
9999          → teleported table dropdown menus
```

### 5. Body scroll lock

Layout uses `h-screen overflow-hidden` on body + sidebar toggle on `documentElement`. Most modals did not coordinate scroll lock.

**Fix:** `property-modal-manager.js` reference-counted `property-modal-scroll-lock` class.

### 6. Escape key

Multiple listeners: dropdown cleanup, individual modals, modal manager. Dropdown handler now yields when modal stack is non-empty.

### 7. Event propagation

Payment reversal modal correctly uses `e.target === modal` for backdrop. Alpine `@click.outside` was the main propagation bug.

### 8. Mobile touch

- Bottom sheet pattern added via `<x-property.modal mobileSheet>` 
- `max-h-[90vh]` + internal scroll on panel
- `min-h-[44px]` touch targets on form controls inside modal

---

## Phase 3–4 — Standardized Architecture (Implemented)

### New files

| File | Role |
|------|------|
| `resources/js/property-modal-manager.js` | Stack, scroll lock, Turbo dismiss, ESC, debug API |
| `resources/views/components/property/modal.blade.php` | Single reusable modal wrapper |

### Usage

```blade
<div x-data="{ open: false }">
    <button @click="open = true">Open</button>
    <x-property.modal show="open" close="open = false" name="my-modal" title="Edit record">
        ... form ...
        <x-slot name="footer">
            <button @click="open = false">Cancel</button>
        </x-slot>
    </x-property.modal>
</div>
```

### Stability rules enforced by component

- Panel uses `@click.stop` — never `@click.outside`
- Backdrop is separate sibling with explicit `@click`
- Teleport to `<body>` — escapes overflow clipping
- Scroll lock via manager on open/close
- Turbo navigation closes all modals
- ESC closes top of stack only

---

## Phase 5 — Mobile

`<x-property.modal>` defaults:
- Desktop: centered dialog, `max-h-[90vh]`, internal scroll
- Mobile: bottom sheet (`max-md:items-end`, rounded top, slide-up transition)
- Sticky header/footer slots
- Safe area padding on footer

Filter drawers already implement bottom-sheet pattern at `z-[6500]`.

---

## Phase 6 — JS Conflicts

| File | Modal role |
|------|------------|
| `app.js` | Loads Alpine once; registers `propertyModalState` |
| `property-modal-manager.js` | **NEW** — unified modal lifecycle |
| `property-dropdown-cleanup.js` | Teleports menus to body; **updated** to skip modal clicks & defer ESC |
| `swal-init.js` | SweetAlert2; Turbo stale overlay cleanup; `data-overlay-recoverable` support |
| `property-portal-turbo.js` | Frame navigation; no modal logic (manager handles dismiss) |
| Inline `<script>` in Blade | 8+ pages with bespoke modal toggle logic — migration backlog |

**No duplicate Alpine initialization found.**

---

## Phase 7 — Dropdown / Action Menu Issues

`property-dropdown-cleanup.js`:
- Teleports `[data-table-actions-menu]` to `document.body` at `z-index: 9999`
- Closes on Turbo, scroll, outside click, ESC
- **Fixed:** outside-click handler ignores targets inside `[data-property-modal]`
- **Fixed:** ESC yields to modal stack

Remaining issue: dropdown z-index 9999 can still appear above legacy modals at z-50 until those are migrated.

---

## Phase 8 — Debug Utilities

Enable in browser console:

```js
localStorage.setItem('overlay_debug', '1');
location.reload();

// Then:
PropertyModalManager.inspect();   // stack, z-index, scroll lock state
PropertyModalManager.getStack();  // active modal ids
inspectPropertyModals();          // alias
```

Logs prefixed `[PropertyModal]` in dev or when `overlay_debug=1`.

---

## Phase 9 — Priority Fixes

### Completed (this pass)

- [x] Create `<x-property.modal>` + `PropertyModalManager`
- [x] Migrate quick-create-select modals (~18 pages)
- [x] Migrate next-steps-modal with tenant/landlord/vendor summary support
- [x] Remove duplicate next-steps from directory/landlords/vendors pages
- [x] Fix `@click.outside` on access control + add unit modals
- [x] Dropdown/modal ESC and click coordination
- [x] Reference-counted scroll lock CSS + JS
- [x] Turbo dismiss for all managed modals

### High priority (next sprint)

1. Migrate lease modal stack (`leases.blade.php`, `lease_edit.blade.php`) — largest inline cluster
2. Extract shared `unit_meta_modal` component from `units.blade.php` + `edit_unit.blade.php`
3. Migrate vanilla JS modals (payments reversal, chart of accounts, audit trail, statements)
4. Fix or remove dead `#charge-type-modal` in `lease_edit.blade.php`
5. Align filter drawers with modal manager scroll lock (optional consolidation)

### Medium priority

6. Migrate print preview modal (`unmatched_payments.blade.php`)
7. Add `data-property-modal` to legacy vanilla modals for ESC/stack integration
8. Standardize loan module on same component (separate effort)

---

## Files Changed (Stabilization Pass)

| File | Change |
|------|--------|
| `resources/js/property-modal-manager.js` | **NEW** |
| `resources/views/components/property/modal.blade.php` | **NEW** |
| `resources/js/app.js` | Import + Alpine.data registration |
| `resources/js/property-dropdown-cleanup.js` | Modal-aware click/ESC |
| `resources/css/app.css` | Scroll lock + isolation |
| `resources/views/components/property/next-steps-modal.blade.php` | Uses `<x-property.modal>` |
| `resources/views/components/property/quick-create-select.blade.php` | Uses `<x-property.modal>` |
| `resources/views/property/agent/settings/system_setup/access.blade.php` | Migrated 2 modal stacks |
| `resources/views/property/agent/properties/show.blade.php` | Add unit modal migrated |
| `resources/views/property/agent/tenants/directory.blade.php` | Removed duplicate next-steps |
| `resources/views/property/agent/landlords/index.blade.php` | Removed duplicate next-steps |
| `resources/views/property/agent/vendors/directory.blade.php` | Removed duplicate next-steps |

---

## Recommended Z-Index Tokens

```js
// property-modal-manager.js MODAL_Z
drawer:   6500
modal:    7010
nested:   7110
dropdown: 7200  // future: above modals when intentional
```

Always above sidebar (`5600`), below global search (`99999`).
