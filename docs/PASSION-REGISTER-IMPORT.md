# Passion legacy migration (phased)

Import data from the old Passion Shelters system in **strict order**. Each phase uses its own register export.

## Migration order

| Phase | Register file | Command | What imports |
|-------|---------------|---------|--------------|
| **1** | Property register PDF | `property:import-passion-register` | Properties + commission % |
| **2** | Landlords register PDF | `property:import-passion-landlords` | Landlord users + property links |
| **3** | Property units listing PDF | `property:import-passion-units` | Units (rent, floor, status, market rent) |
| **4** | Active tenants & leases PDF | `property:import-passion-leases` | Tenants (TNT account, balance) + active leases |

Run phases **in order**. Phase 4 matches units by property code + unit label.

---

## Full production sequence

The legacy PDF exports are committed in the repo at `storage/passion-legacy/` — after `git pull` you do **not** need to upload them manually.

```bash
cd /home/passion/passion
git pull origin passion-homes
composer install --no-dev --optimize-autoloader
php artisan migrate --force

# 1 — Properties
php artisan property:import-passion-register storage/passion-legacy/property_register.pdf \
  --dry-run --agent-user-id=2
php artisan property:import-passion-register storage/passion-legacy/property_register.pdf \
  --agent-user-id=2

# 2 — Landlords
php artisan property:import-passion-landlords storage/passion-legacy/landlord_register.pdf \
  --dry-run --agent-user-id=2
php artisan property:import-passion-landlords storage/passion-legacy/landlord_register.pdf \
  --agent-user-id=2

# 3 — Units
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.pdf \
  --dry-run --agent-user-id=2
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.pdf \
  --agent-user-id=2

# 4 — Tenants + leases
php artisan property:import-passion-leases storage/passion-legacy/leases.pdf \
  --dry-run --agent-user-id=2
php artisan property:import-passion-leases storage/passion-legacy/leases.pdf \
  --agent-user-id=2
```

Replace `YOUR_AGENT_ID` with the Passion staff user id.

---

## Phase 1 — Properties

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

If units failed but leases ran (many “creating stub” warnings):

```bash
git pull origin passion-homes
php artisan property:import-passion-units storage/passion-legacy/property_unit_register.txt --agent-user-id=2
```

Re-running is safe — existing properties/tenants/leases are matched and updated, not duplicated.

---

## Expected warnings (not errors)

| Warning | Meaning |
|---------|---------|
| Landlord `J00041`, `M00008`, etc. — no matching property | Those landlord codes are **not** in the 38-property register PDF; 28/36 landlords linked is correct |
| `property not found — WINTA END APARTMENT` | Property was saved as truncated name `WINTA END`; fixed in latest code — re-run units with `.txt` |
| Lease import “unit not found — creating stub” | Units phase did not complete; re-run units import first |

---

## Local test results (Sep 2026)

| Phase | Parsed | Created |
|-------|--------|---------|
| Properties | 38 | 38 |
| Landlords | 36 | 28 (+ links) |
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
