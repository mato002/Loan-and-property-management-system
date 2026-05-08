<?php

namespace Tests\Feature\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyJournalService;
use App\Services\Property\PropertyPaymentReversalApprovalService;
use App\Services\Property\PropertyTransactionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class TrustAccountingPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_service_rejects_unbalanced_batch(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seedOpenPeriod();

        /** @var PropertyJournalService $service */
        $service = app(PropertyJournalService::class);
        $service->postBatch([
            'date' => now()->toDateString(),
            'description' => 'Broken test batch',
            'source_type' => 'test',
            'source_id' => 100,
            'event_type' => 'unbalanced',
            'source_key' => 'test:100:unbalanced',
        ], [
            ['account_id' => $service->accountIdByCode('1100'), 'debit' => 100, 'credit' => 0],
            ['account_id' => $service->accountIdByCode('1200'), 'debit' => 0, 'credit' => 99],
        ]);
    }

    public function test_payment_posting_is_idempotent_and_balanced(): void
    {
        $this->seedOpenPeriod();
        $property = Property::query()->create(['name' => 'Test Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Tenant One',
        ]);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-TEST-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-TEST-1',
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
        PropertyAccountingPostingService::postPaymentReceived($payment);

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->first();

        $this->assertNotNull($batch);
        $this->assertSame(1, AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->count());

        $lines = AccountingJournalLine::query()->where('batch_id', $batch->id)->get();
        $this->assertGreaterThan(0, $lines->count());
        $this->assertSame(
            round((float) $lines->sum('debit'), 2),
            round((float) $lines->sum('credit'), 2)
        );
    }

    public function test_payment_reversal_reverses_journals_allocations_and_landlord_ledger(): void
    {
        $this->seedOpenPeriod();

        $landlord = \App\Models\User::factory()->create();
        $property = Property::query()->create(['name' => 'Rev Property']);
        DB::table('property_landlord')->insert([
            'property_id' => $property->id,
            'user_id' => $landlord->id,
            'ownership_percent' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'B1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Rev Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-REV-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-REV-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 1000,
        ]);

        app(\App\Services\Property\PropertyPaymentSettlementService::class)->complete(
            $payment,
            $payment->external_ref,
            $payment->paid_at,
            'ok',
            'test',
            1000
        );

        app(PropertyTransactionReversalService::class)->reversePayment($payment->fresh(), 1, 'test reversal');

        $payment->refresh();
        $invoice->refresh();
        $allocation = PmPaymentAllocation::query()->where('pm_payment_id', $payment->id)->first();
        $this->assertSame(PmPayment::STATUS_FAILED, $payment->status);
        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertTrue((bool) $allocation->is_reversed);

        $baseBatch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->first();
        $reversalBatch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received_reversal')
            ->first();
        $this->assertNotNull($baseBatch);
        $this->assertNotNull($reversalBatch);
        $this->assertSame(AccountingJournalBatch::STATUS_REVERSED, $baseBatch->status);

        $reversedLedgerCount = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment_reversal')
            ->where('reference_id', $payment->id)
            ->count();
        $this->assertGreaterThan(0, $reversedLedgerCount);
    }

    public function test_maker_checker_prevents_self_approval_of_reversal(): void
    {
        $this->seedOpenPeriod();
        $user = \App\Models\User::factory()->create();
        $property = Property::query()->create(['name' => 'MC Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'C1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'MC Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-MC-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 1000,
            'status' => PmInvoice::STATUS_PAID,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'bank',
            'amount' => 1000,
            'external_ref' => 'PAY-MC-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 1000,
        ]);

        app(PropertyPaymentReversalApprovalService::class)->request($payment, (int) $user->id, 'Mistaken receipt');

        $this->expectException(RuntimeException::class);
        app(PropertyPaymentReversalApprovalService::class)->approve($payment->fresh(), (int) $user->id, 'self approve');
    }

    private function seedOpenPeriod(): void
    {
        AccountingPeriod::query()->create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
    }
}

