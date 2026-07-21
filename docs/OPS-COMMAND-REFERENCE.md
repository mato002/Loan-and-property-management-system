# Operations command reference

**One cheat sheet** for server-side tasks: deploy steps, landlord/agent scoping, finance repairs, billing automation, SMS, queues, and diagnostics.

**Production app path (Gaitho example):**

```bash
cd /home/gaithoproperties/property
```

**Web UI (super admin only):** `/superadmin/ops` — landlord/agent scope tools + this command reference in the browser (no SSH required for landlord scope fixes).

**Module switch (dual-module staff):** use the **Property / Loan** pill in the header or **Switch to…** in the profile menu — no logout required.

**Always preview destructive/backfill work with `--dry` or `--dry-run` first when available.**

Related deep dives: [PRODUCTION-RUNBOOK.md](PRODUCTION-RUNBOOK.md) · [SCHEDULER-SETUP.md](SCHEDULER-SETUP.md) · [QUEUE-WORKER-SETUP.md](QUEUE-WORKER-SETUP.md)

---

## Deploy (after code upload)

```bash
cd /home/gaithoproperties/property

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build          # if assets changed

php artisan migrate --force
php artisan optimize:clear       # config, cache, routes, views
```

Optional:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Landlord ↔ agent visibility

Agents only see landlords that are:

1. **Stamped** on `users.agent_user_id` for that agent, **or**
2. Recorded in **`pm_portal_actions`** (`landlord_onboarded`), **or**
3. **Linked** to a property owned by that agent (`property_landlord` → `properties.agent_user_id`).

Super admins see **all** landlords.

**UI (what an agent sees):** log in as the agent → **Portfolio → Landlords** (`/property/landlords`).

### Command

```bash
php artisan property:backfill-landlord-agent-scope [options]
```

| Option | Purpose |
|--------|---------|
| `--list-agents` | List property agents with user IDs |
| `--list-for-agent=ID` | List landlords visible to one agent |
| `--inspect-landlord=ID` | Show why a landlord is visible (stamp / audit / property) |
| `--landlord=ID --agent=ID` | Assign one landlord to one agent |
| `--release-landlord=ID` | Make landlord super-admin only (clears all agent ties; unlinks properties by default) |
| `--release-landlord=ID --from-agent=ID` | Remove only one agent’s tie (keeps other agents if any) |
| `--keep-property-links` | With `--release-landlord`, do not unlink properties |
| `--assign-orphans-to=ID` | Stamp **every** unscoped landlord to one agent (use carefully) |
| `--dry` | Preview without writing |

### Common recipes (Gaitho)

**List Arthur’s agents and landlords:**

```bash
php artisan property:backfill-landlord-agent-scope --list-agents
php artisan property:backfill-landlord-agent-scope --list-for-agent=24
```

**Assign NJEHIA (38) to Arthur (24):**

```bash
php artisan property:backfill-landlord-agent-scope --landlord=38 --agent=24
```

**Remove test landlord (40) from Arthur only:**

```bash
php artisan property:backfill-landlord-agent-scope --release-landlord=40 --from-agent=24
```

**Keep landlord admin-only (35, 39 — no agent):**

```bash
php artisan property:backfill-landlord-agent-scope --release-landlord=35
php artisan property:backfill-landlord-agent-scope --release-landlord=39
```

**First-time repair after deploy (backfill stamps from property links + SMS + audit):**

```bash
php artisan property:backfill-landlord-agent-scope
```

**Do not run** `--assign-orphans-to=24` if some orphans must stay admin-only.

### Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Agent sees no new landlord | Onboarded before fix; no stamp/audit | `--landlord=X --agent=Y` or re-onboard / resend credentials as agent |
| Landlord stuck on wrong agent | `onboard audit` or `agent_user_id` | `--release-landlord=X --from-agent=Y` |
| Super admin sees landlord, agent does not | Unscoped (intentional) | `--landlord=X --agent=Y` if agent should manage them |
| `--assign-orphans-to=ARTHUR_USER_ID` did nothing | Used literal text, not numeric ID | Use `--assign-orphans-to=24` |

---

## Property billing & utilities

| Command | What it does |
|---------|----------------|
| `php artisan rent:generate-invoices` | Monthly rent invoices (`--month=YYYY-MM`) |
| `php artisan water:generate-invoices` | Water invoices from meter readings (`--month=`, `--due-date=`) |
| `php artisan utility:materialize-attached-charges` | Garbage / fixed attached charges from property templates (`--month=`) |
| `php artisan water:apply-penalties` | Apply water penalty rules (`--date=`, `--preview`) |
| `php artisan rent:send-reminders` | Rent reminder SMS/email (`--date=YYYY-MM-DD`) |
| `php artisan invoices:refresh-statuses` | Recompute invoice paid/overdue flags (`--limit=`) |
| `php artisan property:workflow-automation-status` | Show whether schedulers for rent/water/reminders are enabled |

---

## Finance integrity & reconciliation

| Command | What it does |
|---------|----------------|
| `php artisan finance:reconcile` | Full finance drift scan (`--tenant=`, `--scope=`, `--audit`, `--alert`) |
| `php artisan finance:reconcile-carry-forward` | Carry-forward lifecycle + auto-apply tenant credit (`--tenant=`, `--lease=`, `--backfill-gl`) |
| `php artisan finance:backfill-carry-forward-gl` | Backfill missing carry-forward GL (`--tenant=`, `--lease=`) |
| `php artisan finance:detect-allocation-drift` | Payment allocation vs invoice drift (`--tenant=`, `--audit`) |
| `php artisan finance:detect-accounting-drift` | GL / journal drift |
| `php artisan finance:detect-reversal-drift` | Reversal integrity (`--tenant=`, `--audit`) |
| `php artisan finance:reconcile-landlord-subledger` | Landlord payable GL vs subledger (`--property=`) |
| `php artisan finance:backfill-landlord-subledger` | Backfill landlord subledger from payments (`--tenant=`, `--dry-run`) |
| `php artisan property:backfill-landlord-ledger` | Backfill landlord ledger from payments (`--from=`, `--to=`) |
| `php artisan payments:repair-allocations` | Repair tenant payment allocations (`--tenant=`, `--limit=`) |

**Example — fix tenant credit not applied to carry-forward invoice:**

```bash
php artisan finance:reconcile-carry-forward --tenant=TENANT_ID
```

---

## Tenants, leases, payments (property)

| Command | What it does |
|---------|----------------|
| `php artisan pm:payments-list` | List recent `pm_payments` (`--tenant_id=`, `--limit=`) |
| `php artisan pm:payment-show {id}` | Show one payment |
| `php artisan pm:payment-verify {id}` | Poll Daraja STK status and settle if paid |
| `php artisan tenants:merge-duplicates` | Merge duplicate tenants (`--by=phone`, `--agent=`, `--apply`) |
| `php artisan leases:audit-integrity` | One lease per tenant / unit rules (`--fix`, `--force`) |
| `php artisan leases:backfill-revenue-postings` | Backfill lease revenue GL (`--lease-id=`, `--dry-run`) |
| `php artisan property:backfill-payment-agent-ids` | Stamp agent on SMS ingests / payments (`--dry`) |

---

## SMS & communications

| Command | What it does |
|---------|----------------|
| `php artisan bulksms:dispatch-schedules` | Send due bulk SMS schedules |
| `php artisan communications:dispatch-scheduled` | Release scheduled property + loan messages |
| `php artisan sms:monitor-wallet` | Check SMS wallet balance / pressure |
| `php artisan communications:sms-audit` | Audit SMS logs (`--from=`, `--to=`, `--fix-supersede`) |
| `php artisan property:supersede-stale-failed-sms-logs` | Mark stale failed SMS as superseded (`--dry-run`) |
| `php artisan sms:backfill-unmatched` | Re-parse unmatched SMS ingests (`--dry-run`, `--limit=`) |
| `php artisan landlord:send-portal-alerts` | Landlord portal notification digest (`--user=`) |

**M-Pesa / STK testing:**

```bash
php artisan mpesa:oauth-test
php artisan mpesa:stk-test 2547XXXXXXXX 10 --tenant_id=ID
```

---

## Loan module

| Command | What it does |
|---------|----------------|
| `php artisan loan:accrue-penalties` | Accrue loan penalties (`--date=`, `--dry-run`) |
| `php artisan loan:close-settled` | Close zero-balance loans (`--dry-run`) |
| `php artisan loan:backfill-client-wallets` | Create missing client wallets (`--dry-run`) |
| `php artisan loan:backfill-wallet-overpayments` | Wallet credits from overpayments (`--dry-run`) |
| `php artisan loan:backfill-payment-allocations` | Backfill loan allocation rows (`--dry-run`) |
| `php artisan loan:backfill-payment-journals` | Backfill payment journals (`--dry-run`) |
| `php artisan loan:seed-microfinance-coa` | Seed loan COA (`--dry-run`, `--prune-unused`) |
| `php artisan loan:expire-temporary-access` | Expire temporary loan portal access |
| `php artisan loan:lead-officer-digest` | Daily lead pipeline digest (`--date=`) |

---

## Infrastructure & ops

| Command | What it does |
|---------|----------------|
| `php artisan ops:queue-status` | Queue depth + recent failures (`--json`) |
| `php artisan ops:redis-check` | Redis readiness (`--json`) |
| `php artisan ops:redis-cutover-verify` | Post-cutover Redis verification |
| `php artisan fetch:equity-transactions` | Pull Equity bank transactions (`--manual`, `--sync`) |
| `php artisan system:super-admin-access` | Reset/create super admin (`--email=`, `--password=`, `--force`) |

**Laravel maintenance:**

```bash
php artisan queue:work --stop-when-empty
php artisan queue:failed
php artisan schedule:list
php artisan horizon:status          # if Horizon enabled
```

---

## Webhook testing (local / staging)

See [README.md](../README.md) for curl examples:

- Property STK callback — `POST /webhooks/property/payments/stk-callback`
- Property SMS ingest — `POST /webhooks/property/payments/sms-ingest`
- Loan SMS ingest — `POST /webhooks/loan/payments/sms-ingest`

---

## Quick index (all custom commands)

```
property:backfill-landlord-agent-scope
property:backfill-landlord-ledger
property:backfill-payment-agent-ids
property:supersede-stale-failed-sms-logs
property:workflow-automation-status

rent:generate-invoices
rent:send-reminders
water:generate-invoices
water:apply-penalties
utility:materialize-attached-charges
invoices:refresh-statuses

finance:reconcile
finance:reconcile-carry-forward
finance:backfill-carry-forward-gl
finance:detect-allocation-drift
finance:detect-accounting-drift
finance:detect-reversal-drift
finance:reconcile-landlord-subledger
finance:backfill-landlord-subledger

pm:payments-list
pm:payment-show
pm:payment-verify
payments:repair-allocations
tenants:merge-duplicates
leases:audit-integrity
leases:backfill-revenue-postings

bulksms:dispatch-schedules
communications:dispatch-scheduled
communications:sms-audit
sms:monitor-wallet
sms:backfill-unmatched
landlord:send-portal-alerts
mpesa:oauth-test
mpesa:stk-test

loan:accrue-penalties
loan:close-settled
loan:backfill-client-wallets
loan:backfill-wallet-overpayments
loan:backfill-payment-allocations
loan:backfill-payment-journals
loan:seed-microfinance-coa
loan:expire-temporary-access
loan:lead-officer-digest

ops:queue-status
ops:redis-check
ops:redis-cutover-verify
fetch:equity-transactions
system:super-admin-access
```

---

*Last updated: 2026-07-01 — includes landlord/agent scoping workflows from production (agent #24 / Gaitho Properties).*
