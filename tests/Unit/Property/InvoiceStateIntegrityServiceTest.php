<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\InvoiceStateIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceStateIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_engine_marks_past_due_without_overdue_status(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Status Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'ST1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Status Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-STATUS-001',
            'issue_date' => '2026-01-01',
            'due_date' => now()->subDays(5)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();

        $this->assertSame(PmInvoice::STATUS_SENT, (string) $invoice->status);
        $this->assertTrue((bool) $invoice->is_past_due);
        $this->assertSame([], app(InvoiceStateIntegrityService::class)->inspect($invoice));
    }

    public function test_detects_paid_with_open_balance(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Integrity Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'I1',
            'rent_amount' => 500,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Integrity Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-BAD-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 500,
            'amount_paid' => 100,
            'status' => PmInvoice::STATUS_PAID,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'is_past_due' => false,
        ]);

        $violations = app(InvoiceStateIntegrityService::class)->inspect($invoice);

        $this->assertContains(InvoiceStateIntegrityService::VIOLATION_PAID_WITH_BALANCE, $violations);
    }
}
