<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyReversalFinalizeService;
use App\Services\Property\ReversalIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReversalIntegrityServiceTest extends TestCase
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
    private function seedInvoice(string $invoiceNo = 'INV-REV-1'): array
    {
        $this->seedOpenPeriod();
        $property = Property::query()->create(['name' => 'Reversal Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'R1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Reversal Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => $invoiceNo,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        return compact('tenant', 'unit', 'invoice');
    }

    public function test_credit_note_posts_credit_memo_issued_batch(): void
    {
        ['invoice' => $original] = $this->seedInvoice();
        PropertyAccountingPostingService::postInvoiceIssued($original);

        $creditNote = PmInvoice::query()->create([
            'property_unit_id' => $original->property_unit_id,
            'pm_tenant_id' => $original->pm_tenant_id,
            'invoice_no' => 'CN-REV-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount' => -250,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'invoice_kind' => PmInvoice::KIND_CREDIT_NOTE,
            'original_invoice_id' => $original->id,
            'description' => 'Credit note test',
        ]);

        app(PropertyReversalFinalizeService::class)->issueCreditMemo($creditNote);

        $this->assertTrue(AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $creditNote->id)
            ->where('event_type', 'credit_memo_issued')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists());
    }

    public function test_detects_credit_notes_missing_credit_memo(): void
    {
        ['invoice' => $original] = $this->seedInvoice();

        $creditNote = PmInvoice::query()->create([
            'property_unit_id' => $original->property_unit_id,
            'pm_tenant_id' => $original->pm_tenant_id,
            'invoice_no' => 'CN-MISSING-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount' => -100,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'invoice_kind' => PmInvoice::KIND_CREDIT_NOTE,
            'original_invoice_id' => $original->id,
        ]);

        $rows = app(ReversalIntegrityService::class)->detectCreditNotesMissingCreditMemo(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $creditNote->id));
    }

    public function test_detects_cancelled_invoice_with_unreversed_gl(): void
    {
        ['invoice' => $invoice] = $this->seedInvoice('INV-CANCEL-GL');
        PropertyAccountingPostingService::postInvoiceIssued($invoice);
        $invoice->update(['status' => PmInvoice::STATUS_CANCELLED]);

        $rows = app(ReversalIntegrityService::class)->detectCancelledInvoicesWithUnreversedGl(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id));
    }

    public function test_reverse_invoice_fully_reverses_issuance_batches(): void
    {
        ['invoice' => $invoice] = $this->seedInvoice('INV-FULL-REV');
        PropertyAccountingPostingService::postInvoiceIssued($invoice);

        app(PropertyReversalFinalizeService::class)->reverseInvoiceFully($invoice, null, 'Cancelled');

        $postedIssuance = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->get()
            ->contains(function (AccountingJournalBatch $batch) {
                $type = (string) $batch->event_type;

                return $type === 'invoice_issued' || str_starts_with($type, 'invoice_issued_rev_');
            });

        $this->assertFalse($postedIssuance);
    }

    public function test_detects_cancelled_invoice_with_unreversed_penalty(): void
    {
        ['invoice' => $invoice] = $this->seedInvoice('INV-PEN-REV');
        $invoice->update(['status' => PmInvoice::STATUS_CANCELLED, 'invoice_type' => PmInvoice::TYPE_WATER]);

        PmInvoicePenaltyApplication::query()->create([
            'pm_invoice_id' => $invoice->id,
            'pm_penalty_rule_id' => 1,
            'threshold_date' => now()->toDateString(),
            'amount' => 50,
            'applied_at' => now(),
            'base_amount' => 1000,
            'compounding_mode' => 'simple',
            'days_overdue' => 5,
        ]);

        $rows = app(ReversalIntegrityService::class)->detectCancelledInvoicesWithUnreversedPenalties(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id));
    }

    public function test_detects_reversed_payment_with_active_gl(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedInvoice('INV-PAY-REV');
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 500,
            'external_ref' => 'PAY-REV-GL',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        \App\Models\PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 500,
        ]);
        $payment->load('allocations.invoice.unit.property');
        PropertyAccountingPostingService::postPaymentReceived($payment);
        $payment->update(['status' => PmPayment::STATUS_FAILED]);

        $rows = app(ReversalIntegrityService::class)->detectReversedPaymentsWithActiveGl(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['payment_id'] === (int) $payment->id));
    }
}
