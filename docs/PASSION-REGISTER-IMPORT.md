# Passion legacy migration (phased)

Import data from the old Passion Shelters system in **strict order**. Each phase uses its own register export.

## Fresh start (recommended after duplicate imports)

When re-imports created duplicate landlords, units, or leases, wipe the agent portfolio and import once in the correct order. **Super admins and the agent staff account are kept.**

```bash
cd /home/passion/passion
git pull origin passion-homes
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# Preview what will be removed
php artisan property:wipe-passion-portfolio --agent-user-id=2 --dry-run

# Wipe portfolio data for agent #2 (Passion Homes)
php artisan property:wipe-passion-portfolio --agent-user-id=2 --force
```

Then run phases **1 → 5** below using `.txt` register files (most reliable on production). **Do not skip phase 3 or 4.** **Do not** run cleanup or reconcile on a fresh import.

Expected dashboard after a clean run:

| Card | Target |
|------|--------|
| Properties | 38 |
| Landlords | 36 |
| Units/Spaces | ~442–445 |
| Tenants/Residents | 396 |

---

## Migration order

| Phase | Register file | Command | What imports |
|-------|---------------|---------|--------------|
| **1** | Property register PDF | `property:import-passion-register` | Properties + commission % |
| **2** | Landlords register PDF | `property:import-passion-landlords` | Landlord users + property links |
| **3** | Property units listing PDF | `property:import-passion-units` | Units (rent, floor, status, market rent) |
| **4** | Property register (spaces) | `property:fill-passion-register-spaces` | Generic spaces to match register totals (~442) |
| **5** | Active tenants & leases PDF | `property:import-passion-leases` | Tenants (TNT account, balance) + active leases |

Run phases **in order**. Phase 5 matches units by property code + unit label.

---

## Full production sequence (fresh import)

Use this after `property:wipe-passion-portfolio` or on a new agent account. Prefer `.txt` files on production.

```bash
cd /home/passion/passion
git pull origin passion-homes
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# 1 — Properties
php artisan property:import-passion-register storage/passion-legacy/property_register.txt \
  --dry-run --agent-user-id=2
php artisan property:import-passion-register storage/passion-legacy/property_register.txt \
  --agent-user-id=2

# 2 — Landlords (all 36)
php artisan property:import-passion-landlords storage/passion-legacy/landlord_register.txt \
  --dry-run --agent-user-id=2
php artisan property:import-passion-landlords storage/passion-legacy/landlord_register.txt \
  --agent-user-id=2

# 3 — Units (~380 detailed rows)
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.txt \
  --dry-run --agent-user-id=2
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.txt \
  --agent-user-id=2

# 4 — Fill register spaces (~442 total)
php artisan property:fill-passion-register-spaces --dry-run
php artisan property:fill-passion-register-spaces

# 5 — Tenants + leases (396) — run ONCE
php artisan property:import-passion-leases storage/passion-legacy/leases.txt \
  --dry-run --agent-user-id=2
php artisan property:import-passion-leases storage/passion-legacy/leases.txt \
  --agent-user-id=2

# 6 — Remove any lease-import stub units (if total units > ~442)
php artisan property:cleanup-passion-lease-stubs --agent-user-id=2 --dry-run
php artisan property:cleanup-passion-lease-stubs --agent-user-id=2
```

Replace `--agent-user-id=2` if your Passion agent account uses a different id.

---

## Full production sequence (PDF files)

The legacy PDF exports live in `storage/passion-legacy/`. If PDF parsing returns too few rows on the server, use the `.txt` fresh-import sequence above instead.

---

```bash
php artisan property:import-passion-register "property_register pdf.pdf" \
  --dry-run --agent-user-id=YOUR_AGENT_ID

php artisan property:import-passion-register "property_register pdf.pdf" \
  --agent-user-id=YOUR_AGENT_ID
```

**Default:** 38 properties, commission overrides, no units/landlords/tenants.

| Flag | Purpose |
|------|---------|
| `--dry-run` | Validate without saving |
| `--no-update` | Skip updating existing properties |
| `--without-commission` | Skip commission % from PDF |
| `--export-csv=path.csv` | Export property-level CSV |
| `--export-sql=path.sql` | Export SQL (properties only) |

---

## Phase 2 — Landlords

```bash
php artisan property:import-passion-landlords landlord_register.pdf \
  --agent-user-id=YOUR_AGENT_ID
```

Imports landlord portal users and links them to properties. Legacy codes like `A00039` match `A00039A` automatically.

| Flag | Purpose |
|------|---------|
| `--dry-run` | Validate without saving |
| `--no-update` | Skip updating existing landlords |

---

## Phase 3 — Units

```bash
php artisan property:import-passion-units property_unit_register.pdf \
  --agent-user-id=YOUR_AGENT_ID
```

Imports unit labels, market/current rent, floor, bedrooms, furnished, status, available-from, and legacy area. Properties are matched by **name** from the unit register.

| Flag | Purpose |
|------|---------|
| `--dry-run` | Validate without saving |
| `--no-update` | Skip updating existing units |

---

## Phase 4 — Tenants & active leases

```bash
php artisan property:import-passion-leases leases.pdf \
  --agent-user-id=YOUR_AGENT_ID
```

Imports all **396** active lease rows from the legacy system:

| Legacy field | Stored as |
|--------------|-----------|
| TNT account code | `pm_tenants.account_number` |
| Tenant name, phone | `pm_tenants.name`, `phone` |
| A/c balance | `pm_tenants.opening_arrears_amount` |
| Monthly rent | `pm_leases.monthly_rent` |
| Lease start / end | `pm_leases.start_date`, `end_date` |
| Lease variation | `pm_leases.lease_variation_type` |
| Period / days to expire | `pm_leases.lease_period_days`, `days_to_expire` |

Units are linked via `pm_lease_unit`. Missing units get a stub created from the lease row.

---

## UI columns (after import)

The agent portal now shows legacy fields in:

- **Tenants → Tenant list** — Ac/No, unit, A/c balance, rent, lease dates
- **Tenants → Lease agreements** — Ac/No, phone, email, balance, lease variation

Additional unit fields (floor, market rent, available from) are stored on `property_units` and visible on unit records.

---

## PDF text extraction

Pre-extracted `.txt` copies live beside the PDFs in `storage/passion-legacy/`. **Use these on production if a PDF import parses far fewer rows than expected** (e.g. units `Parsed=178` instead of ~380):

```bash
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.txt --agent-user-id=2
php artisan property:import-passion-leases storage/passion-legacy/leases.txt --agent-user-id=2
```

If a command cannot read a PDF at all:

1. Linux: `apt install poppler-utils` (`pdftotext`)
2. Or: `pip install pypdf`
3. Or: pass the matching `.txt` file from `storage/passion-legacy/`

```bash
php scripts/extract_passion_pdf.php storage/passion-legacy/property_unit_register.pdf
```

---

## Production re-run (after a partial import)

If units import shows `Parsed=380` but `Units created=0`, property names in the DB likely came from a bad PDF import. Either:

```bash
php artisan property:import-passion-register storage/passion-legacy/property_register.txt --agent-user-id=2
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.txt --agent-user-id=2
```

Or pull the latest code (units import falls back to property register **codes** when names do not match).

### Duplicate leases / inflated unit counts

If leases were imported **before** units, the first run created stub units and active leases. Re-running leases then created **second** active leases on real units (763 leases, 812 units).

```bash
git pull origin passion-homes
composer install --no-dev --optimize-autoloader

# Preview cleanup
php artisan property:cleanup-passion-import-duplicates --dry-run

# Terminate duplicate active leases; vacate orphan stub units
php artisan property:cleanup-passion-import-duplicates

# Remove stub units with no active lease (optional)
php artisan property:cleanup-passion-import-duplicates --delete-orphan-stubs

# Remove duplicate stubs where register unit already exists (optional)
php artisan property:cleanup-passion-import-duplicates --delete-duplicate-stubs

# Re-run leases once more (updates + relinks; safe to repeat)
php artisan property:import-passion-leases storage/passion-legacy/leases.txt --agent-user-id=2
```

Expected dashboard after cleanup: ~380 units, ~396 active leases, ~35 vacant units.

### Reconcile extras / wrong links (recommended)

When duplicate cleanup still leaves ~445 units or occupied counts do not match leases:

```bash
git pull origin passion-homes
php artisan property:reconcile-passion-import --agent-user-id=2 --dry-run
php artisan property:reconcile-passion-import --agent-user-id=2
```

Uses `property_unit_register.txt` + `leases.txt` as source of truth to:
- dedupe units on the same property + label
- relink active leases to the correct property/unit from the lease register
- remove orphan/extra units not in the unit register
- sync vacant/occupied status from the unit register

Then import the 3 missing tenants/leases (only if reconcile still reports them):

```bash
php artisan property:import-passion-leases storage/passion-legacy/leases.txt --agent-user-id=2
```

**Do not re-run lease import after reconcile** unless needed for missing tenants — older versions created new stubs. Latest code uses register label matching to avoid that.

---

## Expected totals (old Ezen dashboard)

| Card | Old Ezen | Register export | New system target |
|------|----------|-----------------|-------------------|
| Properties | 38 | 38 | 38 |
| Landlords | 36 | 36 rows | **36** landlord accounts (~28 linked to properties) |
| Units/Spaces | 445 | 380 unit rows; **442** from property register occupied+vacant | **~442–445** |

After the detailed unit listing import (~380 rows), fill remaining spaces from the property register:

```bash
php artisan property:fill-passion-register-spaces --dry-run
php artisan property:fill-passion-register-spaces
```

This adds generic `Unit N` spaces where the property register counts more units than the unit listing export (owner-occupied, caretaker, unlisted bays, etc.). **Do not run reconcile after fill** — it treats those as extras.

| Tenants | 396 | 396 leases | 396 |

---

## Expected warnings (not errors)

| Warning | Meaning |
|---------|---------|
| Landlord code has no matching property | Archived/orphan code (e.g. `J00041`, `M00017`) — landlord is still imported |
| `property not found — WINTA END APARTMENT` | Property was saved as truncated name `WINTA END`; fixed in latest code — re-run units with `.txt` |
| Lease import “unit not found — creating stub” | Units phase did not complete; re-run units import first |

---

## Local test results (Sep 2026)

| Phase | Parsed | Created |
|-------|--------|---------|
| Properties | 38 | 38 |
| Landlords | 36 | 36 accounts (28+ property links) |
| Units | 380 | 372 |
| Leases | 396 | 396 tenants/leases |

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| BIRT Excel/HTML export = red error page | Use PDF export instead |
| `Could not extract text from PDF` | Pass `.txt` or install `pdftotext` / `pypdf` |
| Duplicate properties on re-run | Safe — matches on `properties.code` |
| Unit not found during lease import | Run phase 3 first; lease import creates stubs if needed |
| Landlord code shorter than property code | Resolver matches prefix (`A00039` → `A00039A`) |
| PDF parses too few rows on server | Use `.txt` from `storage/passion-legacy/` instead of `.pdf` |
| Property name mismatch (e.g. WINTA END vs WINTA END APARTMENT) | Pull latest code; re-run units import with `.txt` |
