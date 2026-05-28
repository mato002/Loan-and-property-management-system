# Utility Billing Dependencies

**Version:** 1.0 (Governance baseline)  
**Purpose:** Service, model, and automation dependency maps for change-impact analysis.

---

## 1. High-Level Dependency Graph

```mermaid
flowchart TB
    subgraph Presentation
        PUC[PropertyUtilityChargeController]
        PIC[PmInvoiceController]
        PPC[PmPaymentController]
        PTC[PmTenantCreditController]
        TPC[TenantPortalController]
        PRC[PropertyReportsController]
    end

    subgraph DomainServices
        WBS[WaterBillingService]
        WPS[WaterPenaltyService]
        TCS[TenantCreditService]
        PSS[PropertyPaymentSettlementService]
        PAR[PropertyPaymentAllocationRepairService]
        APS[PropertyAccountingPostingService]
        TRS[PropertyTransactionReversalService]
        PRAS[PropertyPaymentReversalApprovalService]
        UBR[UtilityBillingReportService]
        RIG[RentInvoiceGenerator]
    end

    subgraph Models
        WR[PmWaterReading]
        INV[PmInvoice]
        PAY[PmPayment]
        ALLOC[PmPaymentAllocation]
        IPA[PmInvoicePenaltyApplication]
        TCB[PmTenantCreditBalance]
        TCT[PmTenantCreditTransaction]
        UAL[UtilityAuditLog]
        UUC[PmUnitUtilityCharge]
        PRU[PmPenaltyRule]
    end

    subgraph Infrastructure
        GL[AccountingJournalBatch]
        PJS[PropertyJournalService]
        PPS[PropertyPortalSetting]
    end

    subgraph Automation
        GWI[GenerateMonthlyWaterInvoices]
        AWP[ApplyOverdueWaterPenalties]
        RIS[RefreshInvoiceStatuses]
        RPA[RepairPaymentAllocations]
    end

    PUC --> WBS
    PUC --> WPS
    PIC --> APS
    PIC --> INV
    PPC --> PSS
    PPC --> APS
    PPC --> PRAS
    PTC --> TCS
    TPC --> PSS
    PRC --> UBR

    WBS --> WR
    WBS --> INV
    WBS --> TCS
    WBS --> APS
    WBS --> UAL
    WPS --> IPA
    WPS --> INV
    WPS --> APS
    WPS --> UAL
    TCS --> TCB
    TCS --> TCT
    TCS --> PAY
    TCS --> APS
    PSS --> PAY
    PSS --> ALLOC
    PSS --> INV
    PSS --> TCS
    PSS --> APS
    PAR --> PSS
    PAR --> ALLOC
    TRS --> APS
    PRAS --> TRS

    APS --> PJS
    APS --> GL

    GWI --> WBS
    GWI --> PPS
    AWP --> WPS
    AWP --> PPS
    RIS --> INV
    RPA --> PAR
```

---

## 2. Service Dependency Matrix

| Service | Depends on | Depended on by |
|---------|------------|----------------|
| `WaterBillingService` | `TenantCreditService`, `PropertyAccountingPostingService`, `PmWaterReading`, `PmInvoice`, `PmLease`, `UtilityAuditLog` | `PropertyUtilityChargeController`, `GenerateMonthlyWaterInvoices` |
| `WaterPenaltyService` | `PropertyAccountingPostingService`, `PmInvoice`, `PmPenaltyRule`, `PmInvoicePenaltyApplication`, `UtilityAuditLog` | `PropertyUtilityChargeController`, `ApplyOverdueWaterPenalties` |
| `TenantCreditService` | `PropertyAccountingPostingService`, `PmTenantCreditBalance`, `PmTenantCreditTransaction`, `PmPayment`, `PmInvoice` | `WaterBillingService`, `PropertyPaymentSettlementService`, `PmTenantCreditController`, `GenerateMonthlyWaterInvoices` |
| `PropertyPaymentSettlementService` | `TenantCreditService`, `PropertyAccountingPostingService`, `PmPayment`, `PmInvoice`, `PmPaymentAllocation` | `PmPaymentController`, `TenantPortalController`, `PropertyPaymentAllocationRepairService` |
| `PropertyPaymentAllocationRepairService` | `PropertyPaymentSettlementService`, `PmPayment`, `PmInvoice` | `RepairPaymentAllocations` |
| `PropertyAccountingPostingService` | `PropertyJournalService`, `AccountingJournalBatch`, `AccountingChartAccount` | All posting paths |
| `PropertyTransactionReversalService` | `PropertyAccountingPostingService`, `PmPayment`, allocations | `PropertyPaymentReversalApprovalService` |
| `UtilityBillingReportService` | `PmInvoice`, `PmWaterReading`, `PmUnitUtilityCharge` (read-only) | `PropertyReportsController` |

---

## 3. Model Dependency Graph

```mermaid
erDiagram
    PropertyUnit ||--o{ PmWaterReading : "property_unit_id"
    PropertyUnit ||--o{ PmUnitUtilityCharge : "property_unit_id"
    PmLease ||--o{ PmInvoice : "pm_lease_id"
    PmTenant ||--o{ PmInvoice : "pm_tenant_id"
    PmTenant ||--o| PmTenantCreditBalance : "pm_tenant_id"
    PmTenant ||--o{ PmTenantCreditTransaction : "pm_tenant_id"
    PmTenant ||--o{ PmPayment : "pm_tenant_id"

    PmWaterReading }o--o| PmInvoice : "pm_invoice_id"
    PmUnitUtilityCharge }o--o| PmInvoice : "pm_invoice_id"

    PmInvoice ||--o{ PmPaymentAllocation : "pm_invoice_id"
    PmInvoice ||--o{ PmInvoicePenaltyApplication : "pm_invoice_id"
    PmInvoice ||--o{ PmInvoiceEvent : "pm_invoice_id"
    PmInvoice ||--o{ PmInvoiceItem : "pm_invoice_id"

    PmPayment ||--o{ PmPaymentAllocation : "pm_payment_id"
    PmPayment ||--o{ PmTenantCreditTransaction : "pm_payment_id"

    PmInvoice ||--o{ AccountingJournalBatch : "source_id"
    PmPayment ||--o{ AccountingJournalBatch : "source_id"

    PmPenaltyRule ||--o{ PmInvoicePenaltyApplication : "pm_penalty_rule_id"
```

---

## 4. Invoice Generation Dependency Chain

### 4.1 Meter Path (Canonical)

```mermaid
sequenceDiagram
    participant Cron as GenerateMonthlyWaterInvoices
    participant Settings as PropertyPortalSetting
    participant WBS as WaterBillingService
    participant WR as PmWaterReading
    participant INV as PmInvoice
    participant APS as PropertyAccountingPostingService
    participant TCS as TenantCreditService
    participant UAL as UtilityAuditLog

    Cron->>Settings: isWaterInvoiceAutomationEnabled()
    Settings-->>Cron: true/false
    Cron->>WBS: generateInvoicesForMonth()
    WBS->>WR: query recorded readings
    WBS->>INV: create water invoice
    WBS->>APS: postInvoiceIssued()
    WBS->>WR: link + status=invoiced
    WBS->>UAL: log invoice_generated
    WBS->>TCS: autoApplyForTenant() optional
```

### 4.2 Payment Settlement Chain

```mermaid
sequenceDiagram
    participant UI as TenantPortal / PaymentController
    participant PSS as PropertyPaymentSettlementService
    participant INV as PmInvoice
    participant ALLOC as PmPaymentAllocation
    participant TCS as TenantCreditService
    participant APS as PropertyAccountingPostingService

    UI->>PSS: finalizeIdentifiedPayment()
    PSS->>INV: query open invoices by scope
    loop each invoice due_date ASC
        PSS->>INV: syncAmountPaidFromAllocations()
        PSS->>ALLOC: create allocation
    end
    alt remaining > 0
        PSS->>TCS: createCreditFromOverpayment()
    end
    PSS->>APS: postPaymentReceived()
    PSS->>APS: postLandlordLedgerCredits()
```

---

## 5. External Dependencies

| Dependency | Type | Impact if unavailable |
|------------|------|------------------------|
| MySQL / DB | Infrastructure | All billing halted |
| Laravel scheduler | Infrastructure | Automation skipped |
| STK / payment gateway | External API | Pending payments not completed |
| Chart of accounts seeded | Migration | GL posting fails |
| `PropertyPortalSetting` | Config | Automation toggles default off/on |
| Auth / agent context | Framework | Controller actions blocked |

---

## 6. Migration Dependencies

Utility billing requires migrations in order:

```mermaid
flowchart LR
    M1[2026_03_23 pm_unit_utility_charges]
    M2[2026_03_26 water_billing_support]
    M3[2026_05_05 trust_gl]
    M4[2026_05_11 invoice_lifecycle]
    M5[2026_05_19 tenant_credit]
    M6[2026_05_25 utility_billing_engine]

    M1 --> M2
    M2 --> M3
    M3 --> M4
    M4 --> M5
    M5 --> M6
```

| Migration | Creates / alters |
|-----------|------------------|
| `2026_03_23_120000` | `pm_unit_utility_charges` |
| `2026_03_26_100000` | `pm_water_readings`, invoice_type, billing_period |
| `2026_05_05_095500` | Trust GL chart (1100, 1200, 1250, 1300, 2100, etc.) |
| `2026_05_11_080000` | Invoice events, penalty applications |
| `2026_05_19_100000` | Tenant credit tables, account 2260 |
| `2026_05_25_100000` | Utility audit log, 1210/4310/4410, penalty reversal cols |

---

## 7. Change Impact Guide

| If you change… | You must review… |
|----------------|------------------|
| `WaterBillingService` | ARCHITECTURE, STATE-MACHINE (reading), ACCOUNTING-POLICY |
| `PropertyAccountingPostingService` | ACCOUNTING-POLICY, GOVERNANCE-RULES (GL reconciliation) |
| `PropertyPaymentSettlementService` | GOVERNANCE-RULES (allocation), ARCHITECTURE (payment flow) |
| `TenantCreditService` | STATE-MACHINE (credit), ACCOUNTING-POLICY (2260/1200 gap) |
| `WaterPenaltyService` | STATE-MACHINE (penalty), ACCOUNTING-POLICY (4410) |
| Invoice status logic | STATE-MACHINE, RefreshInvoiceStatuses command |
| Portal automation settings | ARCHITECTURE (automation lifecycle) |
| New invoice_type or account code | All governance docs + migrations |

---

## 8. Circular Dependencies (Watch List)

| Cycle | Risk | Mitigation |
|-------|------|------------|
| `WaterBillingService` → `TenantCreditService` → `PmInvoice` → (invoice issue triggers credit) | Re-entrancy on same request | DB transactions; idempotent credit apply |
| `PropertyPaymentAllocationRepairService` → `PropertyPaymentSettlementService` | Repair replay differs from original if rules changed | Document repair as destructive rebuild |
| Penalty increases amount → may affect paid/overdue status | Status drift | Always call `refreshComputedStatus` after payment sync |

---

## 9. Reporting Read Dependencies

`UtilityBillingReportService` reads only — safe from write cycles:

```
PmInvoice (water/mixed)
  ← PmPaymentAllocation
  ← PmWaterReading
  ← PmUnitUtilityCharge
  ← PmInvoicePenaltyApplication
```

No dependency on GL tables for operational reports (GL tie-out is separate reconciliation).
