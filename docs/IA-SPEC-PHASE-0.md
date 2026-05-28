# Property ERP — Phase 0 Information Architecture Lock

**Status:** Approved for implementation planning (spec only — no UI/code changes in this phase)  
**Scope:** Agent portal (`property.*` routes)  
**Date:** 2026-05-27

---

## Executive summary

The Property Management System is reorganized from a **long sidebar CRUD admin panel** (14+ top-level sections, nested children, duplicated money views) into a **Property Operating System** with **9 primary workspaces**.

Each workspace owns an operational domain. The sidebar shows **workspaces only**. Lists live in **workspace tabs**. Records live in **entity surfaces** (show/edit/create). Analytics live in **Reports**. Configuration lives in **Settings**.

**Naming lock:** The current “Revenue” module becomes the **Collections** workspace in IA/UI copy. Routes may remain `property.revenue.*` during migration; workspace identity is **Collections**.

---

## 1. Final workspace map

| # | Workspace | Purpose | Primary user | Operational frequency | Pages owned |
|---|-----------|---------|--------------|----------------------|-------------|
| 1 | **Dashboard** | Command center — alerts, risks, KPIs, today’s work queue, cross-workspace drill links | Agency owner, ops manager, all agents | **Daily** (first screen) | Command center, performance snapshot widgets, notification inbox entry |
| 2 | **Portfolio** | Structural inventory — buildings, units, landlords, occupancy; **no billing** | Portfolio manager, leasing coordinator | **Daily–weekly** | Properties hub, property/unit CRUD lists, landlord directory, occupancy, amenities, unit performance |
| 3 | **Tenants & Leasing** | People and contracts — directory, leases, movements, notices | Leasing agent, tenant relations | **Daily** | Tenant directory, profiles, import, leases, expiry, move-ins/outs, notices |
| 4 | **Collections** | Money-in engine — rent roll, arrears, invoicing, payments, utilities, penalties, bank sync | Collections agent, accountant (ops) | **Daily** | Rent roll, uninvoiced leases, arrears, invoices, payments, receipts/eTIMS, utilities, reconciliation, period close, tenant credits, Equity sync |
| 5 | **Maintenance** | Work orders and vendor execution — tickets through payout | Maintenance coordinator | **Daily** | Requests, jobs, history, cost/frequency ops views, **entire vendor module** (directory, RFQ, quotes, performance, work records) |
| 6 | **Listings** | Vacancy marketing pipeline — publish, leads, applications | Leasing/marketing | **Weekly** (spikes when vacant) | Listing setup, vacant units, live ads, leads, applications |
| 7 | **Reports** | Read-only analytical outputs — tenant, landlord, expense, maintenance, financial packs, trends | Owner, PM, accountant (review) | **Weekly–monthly** | All `property.reports.*`, `property.financials.*`, `property.performance.*`, operational exports framed as reports |
| 8 | **Accounting** | Trust GL — journals, COA, receivables/payables ledgers, bank recon, payroll, statutory GL reports, controls | Accountant, finance admin | **Weekly** (daily during month-end) | Accounting dashboard, GL, journal batches, AR/AP views, cash book, bank recon, payroll, accounting reports, audit trail, reversals, accounting periods, account mapping |
| 9 | **Settings** | System configuration — users, permissions, M-Pesa, branding, rules, form/workflow templates | Super admin, agency admin | **Rare** | Team users, permissions, commission, payments, branding, rules, deposits/expense rules, system setup, SMS forwarder |

### Cross-cutting capabilities (not workspaces)

| Capability | Placement | Rationale |
|------------|-----------|-----------|
| **Global search** | Header | Finds entities across workspaces; never a sidebar item |
| **Communications** | Collections (operational sends/reminders) + Settings (templates) + header notifications | Messaging is an action on collections/tenants, not a standalone ERP domain |
| **AI Advisor** | Header FAB + optional Dashboard card | Utility overlay, not operational workspace |
| **Entity surfaces** | Contextual (opened from lists) | No sidebar presence |
| **Quick-action POST** | Hidden route | Form plumbing only |

### Retired as top-level sidebar sections

| Current section | Disposition |
|-----------------|-------------|
| Revenue | → **Collections** workspace |
| Vendors | → **Maintenance** workspace tabs |
| Financials | → **Reports** workspace (owner-facing summaries) |
| Analytics / Performance | → **Dashboard** (headline KPIs) + **Reports** (trend pages) |
| Communications | → Cross-cutting (see above) |
| AI advisor | → Header FAB |

---

## 2. Navigation hierarchy map

```
Property ERP (agent)
│
├── [Header — persistent]
│   ├── Workspace context (title / breadcrumb)
│   ├── Global search
│   ├── Quick links (top 5–7 operational shortcuts)
│   ├── Notifications
│   ├── AI advisor entry
│   └── User / impersonation / date
│
├── [Sidebar — workspaces only, max 9 items]
│   ├── Dashboard
│   ├── Portfolio
│   ├── Tenants & Leasing
│   ├── Collections
│   ├── Maintenance
│   ├── Listings
│   ├── Reports
│   ├── Accounting
│   └── Settings
│
└── [Main frame — Turbo `property-main`]
    │
    ├── Workspace Hub (landing per workspace)
    │   └── KPI strip + hub grid OR workspace dashboard
    │
    ├── Workspace Tabs (horizontal sub-nav within workspace)
    │   └── Operational list / queue pages
    │
    ├── Entity Surface (no tabs in sidebar)
    │   ├── Show (360° record)
    │   ├── Edit / Create (form or modal)
    │   └── Sub-actions (statement, receipt PDF, credit ledger…)
    │
    └── Report Surface (Reports workspace only)
        ├── Category hub (Tenant / Landlord / Expense / Maintenance / Financial / Trends)
        └── Individual report runner
```

### Workspace → tab hierarchy (target)

#### Dashboard
- *(no tabs — single command surface)*

#### Portfolio
| Tab | Route(s) |
|-----|----------|
| Properties | `property.properties.list` |
| Landlords | `property.landlords.index` |
| Units | `property.properties.units` |
| Occupancy | `property.properties.occupancy` |
| Amenities | `property.properties.amenities` |
| Unit performance | `property.properties.performance` |

#### Tenants & Leasing
| Tab | Route(s) |
|-----|----------|
| Directory | `property.tenants.directory` |
| Leases | `property.tenants.leases` |
| Expiring | `property.tenants.expiry` |
| Movements | `property.tenants.movements` |
| Notices | `property.tenants.notices` |

#### Collections
| Tab group | Tabs | Route(s) |
|-----------|------|----------|
| **Rent** | Rent roll | `property.revenue.rent_roll` |
| | Arrears | `property.revenue.arrears` |
| | Uninvoiced | `property.revenue.uninvoiced_leases` |
| **Billing** | Invoices | `property.revenue.invoices` |
| | Penalties | `property.revenue.penalties` |
| **Cash** | Payments | `property.revenue.payments` |
| | Receipts (eTIMS) | `property.revenue.receipts` |
| | Tenant credits | `property.revenue.tenant_credits` |
| **Utilities** | Charges & readings | `property.revenue.utilities` |
| | Reconciliation | `property.revenue.utilities.reconciliation` |
| | Period closing | `property.revenue.utilities.periods` |
| **Bank** | Equity sync | `property.equity.sync_status` |
| | Unmatched payments | `property.equity.unmatched` |

#### Maintenance
| Tab group | Tabs | Route(s) |
|-----------|------|----------|
| **Operations** | Requests | `property.maintenance.requests` |
| | Jobs | `property.maintenance.jobs` |
| | History | `property.maintenance.history` |
| **Vendors** | Directory | `property.vendors.directory` |
| | RFQ & bidding | `property.vendors.bidding` |
| | Quotes | `property.vendors.quotes` |
| | Work records | `property.vendors.work_records` |
| | Performance | `property.vendors.performance` |

#### Listings
| Tab | Route(s) |
|-----|----------|
| Setup | `property.listings.create` |
| Vacant units | `property.listings.vacant` |
| Live ads | `property.listings.ads` |
| Leads | `property.listings.leads` |
| Applications | `property.listings.applications` |

#### Reports
| Category hub | Route(s) |
|--------------|----------|
| Tenant | `property.reports.tenant` + children |
| Landlord | `property.reports.landlord` + children |
| Expense | `property.reports.expense` + children |
| Maintenance | `property.reports.maintenance` + children |
| Financial (management) | `property.reports.financial` + children |
| Owner financials | `property.financials.*` |
| Trends & analytics | `property.performance.*` |

#### Accounting
| Tab group | Tabs | Route(s) |
|-----------|------|----------|
| **Overview** | Dashboard | `property.accounting.index` |
| **Ledger** | Journal entries | `property.accounting.entries` |
| | Chart of accounts | `property.accounting.gl.chart_accounts` |
| | Journal batches | `property.accounting.gl.journal_batches` |
| **Balances** | Receivables | `property.accounting.receivables.*` |
| | Payables | `property.accounting.payables.*` |
| | Cash & bank | `property.accounting.cash_bank.reconciliation`, cash book |
| **Payroll** | Run payroll | `property.accounting.payroll` |
| | Payslips | `property.accounting.payroll.payslips` |
| **Controls** | Audit trail | `property.accounting.audit_trail` |
| | Reversals | `property.accounting.controls.reversals` |
| | Periods | `property.accounting.controls.periods` |
| **GL reports** | Trial balance, P&L, balance sheet, aged AR/AP, deposits | `property.accounting.reports.*` |
| **Setup** | Account mapping, financial settings | `property.accounting.settings.*` |

#### Settings
| Tab | Route(s) |
|-----|----------|
| Property users | `property.settings.roles` |
| Permissions | `property.settings.permissions` |
| Commission | `property.settings.commission` |
| Payment config | `property.settings.payments` |
| Branding | `property.settings.branding` |
| System rules | `property.settings.rules`, deposits, expenses |
| System setup | `property.settings.system_setup` + field/workflow/template sub-pages |
| My SMS forwarder | `property.settings.forwarder` |

---

## 3. Sidebar rules

1. **Exactly 9 workspace links** for standard agents (Dashboard through Settings). No CRUD pages, no report names, no entity names.
2. **Flat list only** — no nested `<details>` groups, no three-level trees. Depth belongs in workspace tabs.
3. **One active workspace** highlighted from route prefix registry (single source of truth config, not scattered `routeIs` in Blade).
4. **Collapsed desktop mode** shows icons + tooltips; expanded shows workspace name only (no tab labels in sidebar).
5. **Mobile:** sidebar is an overlay drawer; closing on navigation is mandatory.
6. **Permission-gated workspaces:** Settings visible to all agents but tabs inside respect PM permissions; Accounting visible if any accounting permission; entire workspace hidden only when user has zero pages inside.
7. **No duplication:** if a page appears in workspace tabs, it must **not** appear in the sidebar.
8. **Legacy hub routes** (`property.properties.index`, `property.tenants.index`, `property.revenue.index`, etc.) redirect to workspace hub or default tab — they are not sidebar targets.
9. **Communications, Vendors, Financials, Analytics, AI advisor** must not return as sidebar sections.

---

## 4. Tab rules

1. **Tabs belong to one workspace** — never span workspaces (e.g. “Arrears” tab lives under Collections, not Dashboard).
2. **Tab count target:** 4–8 visible tabs per workspace; use **tab groups** (Rent / Billing / Cash) when >8.
3. **Default tab:** first operational queue for that workspace (e.g. Collections → Rent roll; Maintenance → Requests).
4. **Hub page:** optional; may be dropped in favor of landing on default tab + KPI strip. If kept, hub grid mirrors tab set 1:1 (no extra links).
5. **Active tab** derived from route → workspace registry (same config as sidebar).
6. **Entity surfaces hide workspace tabs** — replaced by entity header (back link, record title, primary actions).
7. **Reports use category hubs**, not operational tabs — second-level nav is report picker inside category.
8. **Settings uses horizontal subnav** (already partially implemented via `settings/partials/subnav`).
9. **Turbo frame:** tab links use `data-turbo-frame="property-main"`; entity navigation may use full page or frame based on surface type.
10. **Badges:** operational counts (open requests, unmatched payments) on tabs only — never on sidebar workspace icons except Dashboard alert dot.

---

## 5. Entity action rules

**Definition:** A screen centered on **one record** or **one transactional form** (create/edit/show), usually reached from a list row, search result, or notification.

### Classification

| Type | Examples | Nav behavior |
|------|----------|--------------|
| **Entity show** | Property, unit, tenant, lease, landlord, vendor, invoice, payment, maintenance job, listing application | Back → owning list tab; sidebar stays on parent workspace |
| **Entity edit/create** | `properties/edit`, `tenants/edit`, `leases/edit`, `invoices/edit`, vendor edit, maintenance request edit | Same; prefer modal for lightweight creates |
| **Transactional overlay** | Record payment, apply credit, send invoice, terminate lease | Modal or drawer when possible; full page when multi-step |
| **Document output** | Invoice PDF/print, payment receipt, payslip download, CSV export | Opens new tab/download; no nav slot |
| **Workspace form host** | `property.workspace.form.{slug}` | Modal/full-page form; parent workspace inferred from form registry |

### Rules

1. **Entity routes never appear in sidebar or workspace tabs.**
2. **Row actions** use `<x-property.action-menu>` — primary operational verbs (Pay, Edit, Statement, Terminate).
3. **Show pages are hubs** for related entity actions (tabs: Overview | Leases | Statements | Maintenance) — max 5 sub-tabs on entity surface.
4. **Create flows:** default to modal from list page; full-page create only when >15 fields or multi-section (leases, properties).
5. **Cross-workspace links** on entity pages must label destination workspace (“Open in Collections → Payments”).
6. **Breadcrumb minimum:** `Workspace › List tab › Record` — no full route path.
7. **Destructive actions** require confirmation modal; never a standalone nav page.
8. **Impersonation, quick-action, geo suggest** are infrastructure — not entity surfaces.

### Entity inventory (agent portal)

| Entity | Show | Edit/Create | Primary workspace |
|--------|------|-------------|-------------------|
| Property | `properties.show` | `properties.edit`, store | Portfolio |
| Unit | via property show | `units.edit`, store | Portfolio |
| Landlord | `landlords.show` | onboard flows | Portfolio |
| Tenant | `tenants.show` | `tenants.edit`, store, import | Tenants & Leasing |
| Lease | `leases.show` | `leases.edit`, store | Tenants & Leasing |
| Invoice | `revenue.invoices.show` | `revenue.invoices.edit`, store | Collections |
| Payment | receipt views | store, settle, reversal | Collections |
| Utility period | `utilities.periods.show` | close, override | Collections |
| Maintenance request | edit | store | Maintenance |
| Maintenance job | edit | store | Maintenance |
| Vendor | `vendors.show` | `vendors.edit`, store | Maintenance |
| Listing / public unit | `listings.vacant.public.edit` | photos | Listings |
| Application | `listings.applications.show` | status update | Listings |
| Journal entry | `accounting.entries.show` | store, reverse | Accounting |
| Payroll period | `accounting.payroll.show` | approve/post | Accounting |
| Team user | — | `settings.team_users.create` | Settings |

---

## 6. Reporting philosophy

1. **Reports are read-only** — no inline edit of source transactions (drill links go to operational workspace entity surfaces).
2. **One Reports workspace** owns all management/owner analytical packs (`property.reports.*`, `property.financials.*`, `property.performance.*`).
3. **Accounting GL reports stay in Accounting** (trial balance, statutory balance sheet, GL cash book) — they post to the trust ledger; management P&L in Reports is for owners/PMs.
4. **Duplication boundary:**
   - **Collections** = operational balances (arrears list, rent roll, aging for action)
   - **Reports** = historical/analysis slices (aging summary, allocation reports, trend lines)
   - **Accounting** = GL truth (posted entries, trial balance, deposit liability)
5. **Export** is a report action (CSV/PDF/print), not a separate nav item.
6. **Report picker UX:** category hub → report list → runner with filters → export. No report names in sidebar.
7. **Scheduled / recurring reports** (future): live under Reports workspace, configured in Settings.

---

## 7. Settings philosophy

1. **Settings is the only workspace for configuration** — no split between “Settings sidebar” and “Accounting settings sidebar” in navigation (account mapping is a **tab** under Accounting → Setup, but duplicates of commission/M-Pesa/rules live only in Settings).
2. **Frequency = rare** — agents may visit for forwarder token; admins for system setup.
3. **Permission tiers:**
   - All agents: commission view (if permitted), payment config read, branding read, forwarder
   - `settings.manage`: rules, deposits, expenses, system setup
   - `team.users.manage`: property users
   - `settings.access.manage`: permissions matrix
4. **System setup depth** (forms, fields, workflows, templates) stays nested **inside Settings tabs**, never promoted to sidebar.
5. **Operational toggles** that affect daily work (penalty rules, utility period close) stay in **Collections** as operational controls; **Settings** holds defaults/templates.

---

## 8. Mobile navigation philosophy

### Agent mobile (< lg breakpoint)

| Layer | Behavior |
|-------|----------|
| **Header** | Hamburger → workspace drawer; centered title; search icon; notifications |
| **Workspace drawer** | Same 9 workspaces as desktop sidebar |
| **Workspace tabs** | Horizontally scrollable strip below header OR segmented control; sticky |
| **Hub grids** | 2-column cards (`hub-grid`); tap → tab/list |
| **Lists** | Responsive cards (`responsiveCards`) preferred over horizontal scroll tables |
| **Filters** | Single “Filters” bottom sheet (`filter-toolbar`) |
| **Entity surfaces** | Full viewport; back chevron in header |
| **FAB** | AI advisor only; no per-page FABs without audit |
| **Quick links row** | Hidden on mobile OR collapsed to “Shortcuts” menu — workspace drawer is primary |

### Landlord / tenant portals (out of scope for 9-workspace lock, documented for consistency)

- **Landlord:** Portfolio, Earnings, Reports, Maintenance, Audit — bottom nav or compact sidebar (5 items max).
- **Tenant:** Home, Pay, Payments, Maintenance, Lease — task-oriented bottom nav.

### Capacitor / Android (PWA shell)

- Workspace drawer + bottom bar for top 4 daily workspaces (Dashboard, Collections, Tenants, Maintenance) with “More” → full list.
- Deep links map to entity surface with workspace context in header.

---

## 9. Header behavior philosophy

1. **Global chrome** — header persists across Turbo navigations; sidebar persists on desktop.
2. **Title stack:** `[Workspace name]` or `[Entity name]` + optional breadcrumb on entity/report surfaces.
3. **Global search** — agents only; searches tenants, properties, units, leases, invoices; results grouped by entity type with workspace badge.
4. **Quick links** (desktop): max 7 shortcuts aligned to daily ops — Dashboard, Rent roll, Arrears, Portfolio list, Tenant directory, Collections hub, Maintenance requests. **Remove Financials as quick link** (move to Reports). Replace with Listings or Accounting based on role.
5. **Notifications** — link to `property.notifications` (agent inbox); unread badge; not a workspace.
6. **Impersonation banner** — supersedes all header styling when active.
7. **Date stamp** — contextual “today” for ops; hidden on mobile if crowded.
8. **Z-index contract** — per `docs/UX-ARCHITECTURE.md`: header `5000`, sidebar `5600`, modals `7010+`, search autocomplete topmost.
9. **Workspace switch** resets tab memory to default unless user returns via back navigation within session.

---

## 10. Duplication prevention rules

| Risk | Rule |
|------|------|
| Same list in sidebar + tab | **Tab wins** — remove from sidebar |
| Arrears in Collections + Accounting + Reports | **Collections** = actionable list; **Reports** = aging summary export; **Accounting** = GL aged receivables |
| Tenant statement in Reports + Accounting + Tenant show | **Tenant show** = primary; Reports = bulk/historical; Accounting = GL reconciliation view |
| Cash book in Reports expense + Accounting | **Accounting** owns GL cash book; **Reports expense** cash book = management summary (link, don’t duplicate runner) |
| Balance sheet in Reports financial + Accounting | **Accounting** = statutory; **Reports financial** = owner-pack variants |
| Utilities analytics vs Reports utility | **Reports** owns analytics; Collections owns operational billing/reconciliation |
| Maintenance costs/frequency vs Reports maintenance | **Reports** owns historical; Maintenance workspace ops pages are **queues**, not analytics — move cost/frequency to Reports or label as “Ops snapshot” with link to Reports |
| Settings roles vs Portfolio hub “Property users” | **Settings only** — remove from Portfolio hub grid |
| Communications sidebar vs Collections reminders | Reminders launched from arrears; comms log accessible from Dashboard notifications + Collections context |
| Multiple hub index routes | One canonical hub per workspace; old indexes redirect |

**Registry requirement:** Maintain `config/property_navigation.php` (or `App\Support\Property\PropertyNavigation`) as single source: `workspace`, `tab`, `classification`, `route`, `sidebar_visible`, `tab_visible`.

---

## 11. ERP governance rules

1. **No new top-level sidebar item** without architecture review — must map to one of 9 workspaces or cross-cutting header utility.
2. **Every new route** must ship with: workspace owner, classification (A–E), tab placement, sidebar visibility (= false for entities).
3. **PR checklist:** “Does this duplicate an existing list/report?” — if yes, link instead of new page.
4. **Naming:** User-facing copy uses workspace names; internal route names may lag (e.g. `revenue` → Collections).
5. **Permissions follow workspace** — tab hidden if no permission; workspace hidden if all tabs hidden.
6. **Turbo-first** — workspace tabs and list pages must not full-reload layout.
7. **Modal-first creates** for single-step entities (tenant, vendor, payment) unless exempted in entity rules.
8. **Reporting cannot mutate** — POST endpoints belong in operational workspaces.
9. **Audit:** Navigation changes require update to this document + registry in same PR.
10. **Phase gate:** Phase 1+ implementation must not add CSS/layout refactors until registry exists and sidebar is reduced to 9 items.

---

## 12. Final recommended architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  HEADER: Search · Quick ops · Notifications · Advisor · User    │
├──────────┬──────────────────────────────────────────────────────┤
│ SIDEBAR  │  WORKSPACE MAIN                                       │
│ (9 only) │  ┌─────────────────────────────────────────────────┐ │
│          │  │ Workspace tabs (operational) OR entity header      │ │
│ Dashboard│  ├─────────────────────────────────────────────────┤ │
│ Portfolio│  │ KPI strip / filters / table / cards                │ │
│ Tenants  │  │                                                    │ │
│ Collect. │  │  Row → Entity surface (show/edit/modal)            │ │
│ Maint.   │  │                                                    │ │
│ Listings │  └─────────────────────────────────────────────────┘ │
│ Reports  │                                                       │
│ Account. │                                                       │
│ Settings │                                                       │
└──────────┴──────────────────────────────────────────────────────┘
```

**Operating principles**

- **Workspaces = verbs domains** (where staff spend time)
- **Tabs = queues and registers** (what they scan daily)
- **Entities = records** (what they act on)
- **Reports = evidence** (what they review periodically)
- **Settings = policy** (what they change rarely)

**Migration sequence (recommended, not Phase 0 work)**

1. Introduce navigation registry (routes → workspace/tab/classification).
2. Collapse sidebar to 9 workspaces using registry active states.
3. Add workspace tab bars per section (reuse existing hub partials).
4. Redirect legacy hub routes; dedupe Portfolio/Settings hub links.
5. Fold Vendors into Maintenance tabs; rename Revenue → Collections in UI.
6. Move Financials + Performance under Reports category hubs.
7. Relocate Communications entry points; remove Communications sidebar.
8. Align header quick links to new workspaces.
9. Mobile bottom nav + Capacitor shell (optional parallel track).

---

## Appendix A — Full page classification (agent routes)

**Legend:** A = Daily operational · B = Occasional management · C = Reporting · D = Admin/configuration · E = Entity action

### Dashboard & global

| Page / route name | Class | Workspace |
|-------------------|-------|-----------|
| `property.dashboard` | A | Dashboard |
| `property.search`, `property.search.suggest` | A | Header (global) |
| `property.notifications` | A | Header → inbox |
| `property.advisor`, `property.advisor.ask` | B | Header FAB |
| `property.workspace.form.*` | E | Contextual |
| `property.quick_action.store` | E | Infrastructure |
| `property.geo.kenya_addresses` | E | Infrastructure |

### Portfolio

| Page | Class | Workspace |
|------|-------|-----------|
| `property.properties.index` (hub) | B | Portfolio |
| `property.properties.list` | B | Portfolio › Properties |
| `property.properties.list.export` | C | Portfolio |
| `property.properties.show` | E | Portfolio |
| `property.properties.edit`, store, update, destroy | E | Portfolio |
| `property.landlords.index` | B | Portfolio › Landlords |
| `property.landlords.show` | E | Portfolio |
| `property.landlords.statement` | C | Portfolio (link → Reports) |
| `property.landlords.impersonate` | E | Portfolio |
| `property.landlords.onboard*` | E | Portfolio |
| `property.properties.units` | A | Portfolio › Units |
| `property.units.*` | E | Portfolio |
| `property.properties.occupancy` | A | Portfolio › Occupancy |
| `property.properties.performance` | B | Portfolio › Unit performance |
| `property.properties.amenities` | B | Portfolio › Amenities |

### Tenants & Leasing

| Page | Class | Workspace |
|------|-------|-----------|
| `property.tenants.index` | B | Tenants & Leasing |
| `property.tenants.directory`, export | A | Tenants › Directory |
| `property.tenants.profiles` | B | Tenants › Directory |
| `property.tenants.import*` | D | Tenants › Directory |
| `property.tenants.show`, edit, update, destroy | E | Tenants |
| `property.tenants.statement` | C | Tenants (→ Reports duplicate link) |
| `property.tenants.leases`, bulk | A | Tenants › Leases |
| `property.leases.show`, edit, update, terminate, restore, destroy | E | Tenants |
| `property.tenants.expiry` | A | Tenants › Expiring |
| `property.tenants.movements`, export | A | Tenants › Movements |
| `property.tenants.notices`, export | A | Tenants › Notices |
| `property.tenants.credit.*` | E | Collections (linked from tenant) |
| `property.tenants.utility.statement` | C | Collections › Utilities |

### Collections (current `revenue.*` + equity)

| Page | Class | Workspace |
|------|-------|-----------|
| `property.revenue.index` | A | Collections |
| `property.revenue.rent_roll` | A | Collections › Rent roll |
| `property.revenue.arrears`, `arrears.tenant` | A | Collections › Arrears |
| `property.revenue.uninvoiced_leases` | A | Collections › Uninvoiced |
| `property.revenue.invoices` (+ show/edit/pdf/print/send/credit) | A/E | Collections › Invoices |
| `property.revenue.payments` (+ settle, reversal, receipt) | A/E | Collections › Payments |
| `property.revenue.receipts` | B | Collections › Receipts |
| `property.revenue.tenant_credits` | B | Collections › Tenant credits |
| `property.revenue.penalties` | D | Collections › Penalties |
| `property.revenue.utilities` (+ water readings, invoices, penalties) | A | Collections › Utilities |
| `property.revenue.utilities.reconciliation`, ledger | B | Collections › Reconciliation |
| `property.revenue.utilities.analytics` | C | Reports › Trends (relocate) |
| `property.revenue.utilities.periods` (+ show, close, overrides) | B/D | Collections › Period closing |
| `property.equity.sync_status`, unmatched, matched, all | B | Collections › Bank |

### Maintenance (+ vendors)

| Page | Class | Workspace |
|------|-------|-----------|
| `property.maintenance.index` | B | Maintenance |
| `property.maintenance.requests` (+ export, edit, status) | A/E | Maintenance › Requests |
| `property.maintenance.jobs` (+ export, edit, status) | A/E | Maintenance › Jobs |
| `property.maintenance.history` | B | Maintenance › History |
| `property.maintenance.costs` | C | Reports › Maintenance (relocate) |
| `property.maintenance.frequency` | C | Reports › Maintenance (relocate) |
| `property.vendors.index` | B | Maintenance |
| `property.vendors.directory`, export | B | Maintenance › Vendors |
| `property.vendors.show`, edit, store, status | E | Maintenance |
| `property.vendors.bidding`, quotes, award | B/A | Maintenance › Vendors |
| `property.vendors.performance` | C | Reports › Maintenance |
| `property.vendors.work_records`, pay flows | B/E | Maintenance › Work records |

### Listings

| Page | Class | Workspace |
|------|-------|-----------|
| `property.listings.index` | B | Listings |
| `property.listings.create`, start | E | Listings › Setup |
| `property.listings.vacant`, public edit/photos | A/E | Listings › Vacant |
| `property.listings.ads` | B | Listings › Live ads |
| `property.listings.leads` (+ export) | A | Listings › Leads |
| `property.listings.applications` (+ export, show, message) | A/E | Listings › Applications |

### Reports (+ financials + performance)

| Page | Class | Workspace |
|------|-------|-----------|
| `property.reports.center`, category indexes | C | Reports |
| All `property.reports.tenant.*` | C | Reports › Tenant |
| All `property.reports.landlord.*` | C | Reports › Landlord |
| All `property.reports.expense.*` | C | Reports › Expense |
| All `property.reports.maintenance.*` | C | Reports › Maintenance |
| All `property.reports.financial.*` | C | Reports › Financial |
| `property.reports.export.*` | C | Reports |
| `property.financials.index` | C | Reports › Owner financials |
| `property.financials.income_expenses` | C | Reports |
| `property.financials.cash_flow` | C | Reports |
| `property.financials.owner_balances` | C | Reports |
| `property.financials.commission` | C | Reports |
| `property.performance.index` | C | Reports › Trends |
| All `property.performance.*` | C | Reports › Trends |
| `property.exports.*` | C | Reports |

### Accounting

| Page | Class | Workspace |
|------|-------|-----------|
| `property.accounting.index` | B | Accounting |
| `property.accounting.entries` (+ show, store, reverse, bulk, export) | B/E | Accounting › Journal |
| `property.accounting.gl.chart_accounts` (+ store, disable, clone) | D | Accounting › COA |
| `property.accounting.gl.journal_batches` | B | Accounting |
| `property.accounting.receivables.*` | B | Accounting › Receivables |
| `property.accounting.payables.*` | B | Accounting › Payables |
| `property.accounting.cash_bank.reconciliation` | B | Accounting › Cash & bank |
| All `property.accounting.reports.*` | C | Accounting › GL reports |
| `property.accounting.payroll` (+ settings, payslips, show, approve…) | B/D/E | Accounting › Payroll |
| `property.accounting.audit_trail` (+ show, export) | D | Accounting › Controls |
| `property.accounting.controls.reversals`, periods | D | Accounting › Controls |
| `property.accounting.settings.account_mapping`, financial | D | Accounting › Setup |

### Settings

| Page | Class | Workspace |
|------|-------|-----------|
| `property.settings.index` | D | Settings |
| `property.settings.roles`, team users | D | Settings |
| `property.settings.permissions` | D | Settings |
| `property.settings.commission`, payments, branding | D | Settings |
| `property.settings.rules`, deposits, expenses | D | Settings |
| `property.settings.forwarder` | D | Settings |
| All `property.settings.system_setup.*` | D | Settings › System setup |

### Communications (cross-cutting)

| Page | Class | Primary entry |
|------|-------|---------------|
| `property.communications.index` | B | Dashboard / Collections |
| `property.communications.messages` (+ show, store) | B | Collections / Dashboard |
| `property.communications.bulk` | B | Collections campaigns |
| `property.communications.templates` | D | Settings (link) |
| `property.communications.conversations` (+ show, reply) | B | Dashboard notifications |
| `property.communications.recipients` | B | Bulk send flow |

---

## Appendix B — Current → target sidebar mapping

| Current sidebar section | Target |
|-------------------------|--------|
| Dashboard | Dashboard |
| Properties | Portfolio |
| Listings | Listings |
| Tenants | Tenants & Leasing |
| Revenue | Collections |
| Maintenance | Maintenance |
| Vendors | *(merged into Maintenance tabs)* |
| Analytics | *(merged into Reports › Trends)* |
| Reports | Reports |
| Financials | *(merged into Reports › Owner financials)* |
| Accounting | Accounting |
| Communications | *(removed — cross-cutting)* |
| AI advisor | *(header FAB only)* |
| Settings | Settings |

---

## Appendix C — Open decisions (defaults chosen for Phase 0 lock)

| Question | Phase 0 decision |
|----------|----------------|
| Rename routes from `revenue` to `collections`? | **UI/copy only** in Phase 1; route rename optional Phase 3 |
| Keep workspace hub grids? | **Yes**, but must mirror tabs 1:1; optional collapse to default tab later |
| Utility analytics page workspace | **Reports › Trends** (operational billing stays Collections) |
| Maintenance costs/frequency pages | **Relocate to Reports** under Maintenance category |
| Communications workspace? | **No** — cross-cutting |
| Property users link on Portfolio hub | **Remove** — Settings only |

---

*End of Phase 0 IA specification. Implementation phases (sidebar collapse, tab bars, registry) begin in Phase 1.*
