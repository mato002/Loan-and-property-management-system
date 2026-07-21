<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\AccountingFirebreakService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFirebreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedOpenPeriod(): void
    {
        AccountingPeriod::query()->create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * @return array{tenant: PmTenant, unit: PropertyUnit, invoice: PmInvoice}
     */
    private function seedBillableInvoice(string $invoiceNo = 'INV-ACC-1', string $type = PmInvoice::TYPE_RENT): array
    {
        $property = Property::query()->create(['name' => 'Accounting Firebreak Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Accounting Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => $invoiceNo,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => $type,
        ]);

        return compact('tenant', 'unit', 'invoice');
    }

    public function test_detects_invoice_missing_gl_batch(): void
    {
        ['invoice' => $invoice] = $this->seedBillableInvoice();

        $service = app(AccountingFirebreakService::class);
        $rows = $service->detectInvoicesMissingGlBatch(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id));
    }

    public function test_detects_carry_forward_and_utility_missing_invoice_issued(): void
    {
        ['tenant' => $tenant, 'unit' => $unit] = $this->seedBillableInvoice();

        $carryForward = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CF-1',
            'issue_date' => '2025-11-01',
            'due_date' => '2025-11-01',
            'amount' => 500,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-11',
        ]);

        $utility = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-W-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 250,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_WATER,
        ]);

        $service = app(AccountingFirebreakService::class);

        $this->assertTrue($service->detectCarryForwardMissingInvoiceIssued()->contains(
            fn (array $row) => (int) $row['invoice_id'] === (int) $carryForward->id
        ));
        $this->assertTrue($service->detectUtilityMissingInvoiceIssued()->contains(
            fn (array $row) => (int) $row['invoice_id'] === (int) $utility->id
        ));
    }

    public function test_detects_landlord_ledger_gap_and_suspense_double_post(): void
    {
        $this->seedOpenPeriod();
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedBillableInvoice('INV-GAP-1');

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-GAP-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 1000,
        ]);
        $payment->load('allocations.invoice.unit.property');

        PropertyAccountingPostingService::postPaymentReceived($payment);
        PropertyAccountingPostingService::postUnmatchedPaymentToSuspense($payment);

        $service = app(AccountingFirebreakService::class);

        $this->assertTrue($service->detectLandlordLedgerGaps()->contains(
            fn (array $row) => (int) $row['payment_id'] === (int) $payment->id
        ));
        $this->assertTrue($service->detectSuspenseDoublePostRisk()->contains(
            fn (array $row) => (int) $row['payment_id'] === (int) $payment->id
        ));
        $this->assertTrue($service->detectCashDoubleDebit()->contains(
            fn (array $row) => (int) $row['payment_id'] === (int) $payment->id
        ));
    }

    public function test_detects_payment_without_cash_and_invoice_without_ar(): void
    {
        $this->seedOpenPeriod();
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedBillableInvoice('INV-BAD-1');

        /** @var PropertyJournalService $journal */
        $journal = app(PropertyJournalService::class);
        $incomeAccountId = $journal->accountIdByCode('4100');

        $invoiceBatch = AccountingJournalBatch::query()->create([
            'date' => now()->toDateString(),
            'description' => 'Broken invoice batch',
            'source_type' => 'pm_invoice',
            'source_id' => $invoice->id,
            'event_type' => 'invoice_issued',
            'source_key' => 'test:invoice:'.$invoice->id,
            'status' => AccountingJournalBatch::STATUS_POSTED,
            'posted_at' => now(),
        ]);
        AccountingJournalLine::query()->create([
            'batch_id' => $invoiceBatch->id,
            'account_id' => $incomeAccountId,
            'debit' => 0,
            'credit' => 1000,
            'tenant_id' => $tenant->id,
        ]);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'bank',
            'amount' => 500,
            'external_ref' => 'PAY-BAD-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);

        $paymentBatch = AccountingJournalBatch::query()->create([
            'date' => now()->toDateString(),
            'description' => 'Broken payment batch',
            'source_type' => 'pm_payment',
            'source_id' => $payment->id,
            'event_type' => 'payment_received',
            'source_key' => 'test:payment:'.$payment->id,
            'status' => AccountingJournalBatch::STATUS_POSTED,
            'posted_at' => now(),
        ]);
        AccountingJournalLine::query()->create([
            'batch_id' => $paymentBatch->id,
            'account_id' => $journal->accountIdByCode('1200'),
            'debit' => 0,
            'credit' => 500,
            'tenant_id' => $tenant->id,
        ]);

        $service = app(AccountingFirebreakService::class);

        $this->assertTrue($service->detectInvoiceWithoutAr()->contains(
            fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id
        ));
        $this->assertTrue($service->detectPaymentWithoutCash()->contains(
            fn (array $row) => (int) $row['payment_id'] === (int) $payment->id
        ));
    }

    public function test_persist_detected_issues_writes_immutable_audit_logs(): void
    {
        ['invoice' => $invoice] = $this->seedBillableInvoice('INV-AUDIT-1');

        $service = app(AccountingFirebreakService::class);
        $snapshot = $service->diagnosticsSnapshot(null, 20);
        $logged = $service->persistDetectedIssues($snapshot, dedupe: false);

        $this->assertGreaterThan(0, $logged);
        $this->assertTrue(PmAccountingAuditLog::query()
            ->where('action', PmAccountingAuditLog::ACTION_MISSING_INVOICE_ISSUED)
            ->where('pm_invoice_id', $invoice->id)
            ->exists());
        $this->assertTrue(PmAccountingAuditLog::query()
            ->where('action', PmAccountingAuditLog::ACTION_RECONCILIATION_SCAN)
            ->exists());
    }
}
