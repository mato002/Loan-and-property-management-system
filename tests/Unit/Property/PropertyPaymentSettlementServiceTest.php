<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPaymentSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_payment_to_invoice_derives_amount_paid_from_allocations(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Settlement Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'S1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Settlement Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-SET-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $payment = app(PropertyPaymentSettlementService::class)->recordPaymentToInvoice(
            $invoice,
            250,
            'cash',
            'CASH-001',
            now(),
            $agent,
            null,
            null,
            false,
        );

        $invoice->refresh();
        $allocated = round((float) PmPaymentAllocation::query()
            ->where('pm_invoice_id', $invoice->id)
            ->where(function ($q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->sum('amount'), 2);

        $this->assertSame(PmPayment::STATUS_COMPLETED, (string) $payment->status);
        $this->assertSame(250.0, $allocated);
        $this->assertSame(250.0, (float) $invoice->amount_paid);
        $this->assertSame(250.0, $invoice->allocatedAmount());
    }

    public function test_reverse_payment_allocations_resyncs_amount_paid_from_allocations(): void
    {
        $tenant = PmTenant::query()->create(['name' => 'Reverse Tenant']);
        $property = Property::query()->create(['name' => 'Reverse Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'R1',
            'rent_amount' => 500,
        ]);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-REV-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 500,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'cash',
            'amount' => 200,
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);

        app(PropertyPaymentSettlementService::class)->createAllocation($payment, $invoice, 200);
        $invoice->refresh();
        $this->assertSame(200.0, (float) $invoice->amount_paid);

        app(PropertyPaymentSettlementService::class)->reversePaymentAllocations($payment, null, 'test reversal');
        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->amount_paid);
        $this->assertSame(0.0, $invoice->allocatedAmount());
    }
}
