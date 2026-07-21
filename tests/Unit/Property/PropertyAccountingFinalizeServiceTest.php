<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\PropertyAccountingFinalizeService;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\TenantCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyAccountingFinalizeServiceTest extends TestCase
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
     * @return array{tenant: PmTenant, unit: PropertyUnit, invoice: PmInvoice, landlord: User}
     */
    private function seedPaymentFixture(float $invoiceAmount = 1000): array
    {
        $this->seedOpenPeriod();

        $landlord = User::factory()->create();
        $property = Property::query()->create(['name' => 'Finalize Property']);
        DB::table('property_landlord')->insert([
            'property_id' => $property->id,
            'user_id' => $landlord->id,
            'ownership_percent' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'F1',
            'rent_amount' => $invoiceAmount,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Finalize Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-FIN-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => $invoiceAmount,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        return compact('tenant', 'unit', 'invoice', 'landlord');
    }

    public function test_after_payment_settled_posts_single_payment_received_batch_and_landlord_ledger(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice, 'landlord' => $landlord] = $this->seedPaymentFixture();

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-FIN-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 1000,
        ]);
        $payment->load('allocations.invoice.unit.property');

        app(PropertyAccountingFinalizeService::class)->afterPaymentSettled($payment);
        app(PropertyAccountingFinalizeService::class)->afterPaymentSettled($payment);

        $this->assertSame(1, AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->count());
        $this->assertSame(0, AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_unmatched_suspense')
            ->count());
        $this->assertTrue(PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', $payment->id)
            ->where('user_id', $landlord->id)
            ->exists());
    }

    public function test_partial_allocation_without_tenant_credit_does_not_double_post_suspense(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedPaymentFixture(800);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'cash',
            'amount' => 1000,
            'external_ref' => 'PAY-FIN-2',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 800,
        ]);
        $payment->load('allocations.invoice.unit.property');

        app(PropertyPaymentSettlementService::class)->finalizeIdentifiedPayment($payment, null, 200.0);

        $this->assertSame(1, AccountingJournalBatch::query()
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->count());
        $this->assertSame(0, AccountingJournalBatch::query()
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_unmatched_suspense')
            ->count());
    }

    public function test_unmatched_payment_uses_suspense_only_path(): void
    {
        $this->mock(TenantCreditService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')->andReturn(false);
        });

        $this->seedOpenPeriod();
        $tenant = PmTenant::query()->create(['name' => 'Unmatched Tenant']);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'bank',
            'amount' => 500,
            'external_ref' => 'PAY-SUSP-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);

        app(PropertyAccountingFinalizeService::class)->afterPaymentSettled($payment, null, 500.0);

        $this->assertSame(1, AccountingJournalBatch::query()
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_unmatched_suspense')
            ->count());
        $this->assertSame(0, AccountingJournalBatch::query()
            ->where('source_id', $payment->id)
            ->where('event_type', 'payment_received')
            ->count());
    }

    public function test_conflicting_batches_throw_before_new_posting(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedPaymentFixture();

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-CONFLICT',
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

        $this->expectException(\RuntimeException::class);
        app(PropertyAccountingFinalizeService::class)->afterPaymentSettled($payment);
    }
}
