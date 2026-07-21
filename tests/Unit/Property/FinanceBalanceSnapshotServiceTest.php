<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\FinanceBalanceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceBalanceSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_uses_amount_minus_amount_paid_and_excludes_draft_and_cancelled(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Balance Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'B1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Balance Tenant']);

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OPEN-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 1000,
            'amount_paid' => 250,
            'balance_due' => 750,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-DRAFT-001',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-02-10',
            'amount' => 5000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_DRAFT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CANCEL-001',
            'issue_date' => '2026-02-01',
            'due_date' => '2026-02-10',
            'amount' => 3000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_CANCELLED,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $service = app(FinanceBalanceSnapshotService::class);

        $this->assertSame(750.0, $service->tenantOutstanding((int) $tenant->id));
        $this->assertSame(750.0, $service->globalOutstanding());
        $this->assertSame(750.0, $service->unitOutstanding([(int) $unit->id]));
    }

    public function test_tenant_snapshot_matches_individual_outstanding(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Snapshot Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'S1',
            'rent_amount' => 2000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Snapshot Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OVERDUE-001',
            'issue_date' => '2026-01-01',
            'due_date' => now()->subDays(10)->toDateString(),
            'amount' => 2000,
            'amount_paid' => 500,
            'balance_due' => 1500,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();

        PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-FUTURE-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'balance_due' => 1000,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_WATER,
        ]);

        $service = app(FinanceBalanceSnapshotService::class);
        $snapshot = $service->snapshotForTenant($tenant);

        $this->assertSame(2500.0, $snapshot['outstanding']);
        $this->assertSame(1500.0, $snapshot['overdue']);
        $this->assertSame(1000.0, $snapshot['not_due']);
        $this->assertSame(1500.0, $snapshot['partial_overdue']);
    }

    public function test_allocated_amount_is_memoized_per_invoice_during_request(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Memo Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'M1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Memo Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-MEMO-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $this->assertSame(0.0, $invoice->allocatedAmount());
        $this->assertSame(0.0, $invoice->allocatedAmount());

        PmInvoice::flushAllocatedAmountMemo((int) $invoice->id);
        $this->assertSame(0.0, $invoice->allocatedAmount());
    }

    public function test_balance_due_is_derived_on_save(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Due Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'D1',
            'rent_amount' => 500,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Due Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-DUE-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 500,
            'amount_paid' => 125,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $this->assertSame(375.0, $invoice->balanceFloat());
        $this->assertSame(375.0, (float) $invoice->fresh()->balance_due);
    }
}
