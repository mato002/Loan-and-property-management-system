# Utility Billing Architecture Governance

**Status:** Controlled governance mode — no new business features until this layer is approved.  
**Last updated:** 2026-05-25  
**Scope:** Water/utility billing engine (meter readings, invoicing, penalties, tenant credit, GL, allocations, automation, reporting)

---

## Purpose

The utility billing engine has grown across multiple services, two invoicing paths, shared payment infrastructure, and trust GL integration. Before further feature work, this documentation layer defines:

- System boundaries and canonical flows
- Entity lifecycle and state machines
- Accounting policy and chart-of-accounts mapping
- Reconciliation, period closing, allocation, and reversal rules
- Service dependencies and risk register

These documents describe **current implemented behavior** where it exists, **target policy** where gaps are known, and **explicit prohibitions** during governance mode.

---

## Document Index

| Document | Contents |
|----------|----------|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | System overview, boundaries, lifecycle diagrams (reading → invoice → payment → GL → automation) |
| [STATE-MACHINE.md](./STATE-MACHINE.md) | Formal state machines for readings, invoices, payments, penalties, reversals, credits |
| [ACCOUNTING-POLICY.md](./ACCOUNTING-POLICY.md) | Revenue, liability, suspense, landlord payable, utility receivable rules |
| [GOVERNANCE-RULES.md](./GOVERNANCE-RULES.md) | Reconciliation, period closing, allocation priority, reversal propagation |
| [DEPENDENCIES.md](./DEPENDENCIES.md) | Service/model/command dependency diagrams |
| [RISK-MATRIX.md](./RISK-MATRIX.md) | Risk assessment, gaps, and mitigation priorities |

---

## Governance Mode Rules

While governance mode is active:

1. **No new business features** — bug fixes that violate documented policy require a governance doc update first.
2. **Canonical path** — meter-based billing via `WaterBillingService` is the system of record; legacy manual charge invoicing is deprecated pending alignment (see RISK-MATRIX).
3. **GL changes require policy review** — any new account codes or event types must be added to ACCOUNTING-POLICY.md before code.
4. **State transitions must match STATE-MACHINE.md** — new statuses or transitions require doc update and review.
5. **Automation toggles** — cron commands must respect portal settings documented in ARCHITECTURE.md.

---

## Key Code References

| Layer | Primary paths |
|-------|---------------|
| Meter billing | `app/Services/Property/WaterBillingService.php` |
| Penalties | `app/Services/Property/WaterPenaltyService.php` |
| Tenant credit | `app/Services/Property/TenantCreditService.php` |
| Payment settlement | `app/Services/Property/PropertyPaymentSettlementService.php` |
| GL posting | `app/Services/Property/PropertyAccountingPostingService.php` |
| Reversals | `app/Services/Property/PropertyTransactionReversalService.php` |
| Automation | `app/Console/Commands/GenerateMonthlyWaterInvoices.php`, `ApplyOverdueWaterPenalties.php` |
| Agent UI | `app/Http/Controllers/Property/Agent/PropertyUtilityChargeController.php` |

---

## Approval Checklist

Before exiting governance mode:

- [ ] Architecture document reviewed by product + engineering
- [ ] Accounting policy signed off by finance/trust accounting owner
- [ ] State machines validated against production data samples
- [ ] Risk matrix items R1–R5 have owners and target dates
- [ ] Legacy manual charge path decision recorded (align or retire)
