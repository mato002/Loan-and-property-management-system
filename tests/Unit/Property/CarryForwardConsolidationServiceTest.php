<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmLeaseCarryForwardLine;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\PmTenantCreditBalance;
use App\Models\User;
use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\Property\TenantCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarryForwardConsolidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delta_sync_creates_invoice_and_marks_line_invoiced_without_deleting_paid_rows(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $property = Property::query()->create(['name' => 'CF Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'CF1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'CF Tenant',
            'opening_arrears_amount' => 500,
            'opening_arrears_status' => 'active',
        ]);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-10', 'amount' => '800.00'],
            ],
        ]);
        $lease->units()->attach($unit->id);

        $paidInvoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CF-001',
            'issue_date' => '2025-10-01',
            'due_date' => '2025-10-01',
            'amount' => 300,
            'amount_paid' => 100,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-10',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-10',
        ]);

        $service = app(CarryForwardConsolidationService::class);
        $result = $service->syncLease($lease->fresh(['units.property']));

        $this->assertGreaterThanOrEqual(1, (int) ($result['invoices_created'] ?? 0));
        $this->assertDatabaseHas('pm_invoices', ['id' => $paidInvoice->id, 'deleted_at' => null]);

        $line = PmLeaseCarryForwardLine::query()->where('pm_lease_id', $lease->id)->first();
        $this->assertNotNull($line);
        $this->assertSame(PmLeaseCarryForwardLine::STATUS_INVOICED, (string) $line->carry_forward_status);

        $tenant->refresh();
        $this->assertSame('superseded', (string) $tenant->opening_arrears_status);
        $this->assertSame(0.0, $service->tenantOpeningArrearsInDue($tenant));
        $this->assertSame(0.0, $service->leaseJsonUninvoicedInDue($lease));
    }

    public function test_paid_carry_forward_line_transitions_to_settled(): void
    {
        $property = Property::query()->create(['name' => 'Settle Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'S1',
            'rent_amount' => 500,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Settle Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 500,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-09', 'amount' => '400.00'],
            ],
        ]);
        $lease->units()->attach($unit->id);

        $line = PmLeaseCarryForwardLine::query()->create([
            'pm_lease_id' => $lease->id,
            'pm_tenant_id' => $tenant->id,
            'row_key' => 'rent|old rent|2025-09',
            'charge_type' => 'rent',
            'specific_charge' => 'Old rent',
            'period' => '2025-09',
            'amount' => 400,
            'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_INVOICED,
            'invoiced_amount' => 400,
            'captured_at' => now(),
            'invoiced_at' => now(),
        ]);

        $invoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CF-002',
            'issue_date' => '2025-09-01',
            'due_date' => '2025-09-01',
            'amount' => 400,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-09',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-09',
        ]);

        $line->update(['pm_invoice_ids' => [(int) $invoice->id]]);

        $payment = app(PropertyPaymentSettlementService::class)->recordPaymentToInvoice(
            $invoice,
            400,
            'cash',
            'CASH-CF-002',
            now(),
            null,
            null,
            null,
            false,
        );

        $this->assertSame(PmInvoice::STATUS_PAID, (string) $invoice->fresh()->status);
        $line->refresh();
        $this->assertSame(PmLeaseCarryForwardLine::STATUS_SETTLED, (string) $line->carry_forward_status);
        $this->assertNotNull($line->settled_at);
        $this->assertSame(0.0, app(CarryForwardConsolidationService::class)->tenantUninvoicedCarryForwardDue($tenant));
    }

    public function test_purge_lease_on_delete_removes_unpaid_carry_forward_artifacts(): void
    {
        $property = Property::query()->create(['name' => 'Delete CF Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'D1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Delete CF Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-08', 'amount' => '600.00'],
            ],
        ]);
        $lease->units()->attach($unit->id);

        PmLeaseCarryForwardLine::query()->create([
            'pm_lease_id' => $lease->id,
            'pm_tenant_id' => $tenant->id,
            'row_key' => 'rent|old rent|2025-08',
            'charge_type' => 'rent',
            'specific_charge' => 'Old rent',
            'period' => '2025-08',
            'amount' => 600,
            'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_INVOICED,
            'invoiced_amount' => 600,
            'captured_at' => now(),
            'invoiced_at' => now(),
        ]);

        $invoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CF-DEL-1',
            'issue_date' => '2025-08-01',
            'due_date' => '2025-08-01',
            'amount' => 600,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-08',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-08',
        ]);

        app(CarryForwardConsolidationService::class)->purgeLeaseOnDelete($lease->fresh(['units.property']));

        $this->assertSoftDeleted('pm_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('pm_lease_carry_forward_lines', ['pm_lease_id' => $lease->id]);
    }

    public function test_delta_sync_auto_applies_tenant_credit_to_new_carry_forward_invoice(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        if (! app(TenantCreditService::class)->isEnabled()) {
            $this->markTestSkipped('Tenant credit tables are not migrated.');
        }

        $property = Property::query()->create(['name' => 'Credit CF Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'CCF1',
            'rent_amount' => 500,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Credit CF Tenant',
            'opening_arrears_amount' => 500,
            'opening_arrears_status' => 'active',
        ]);
        PmTenantCreditBalance::query()->create([
            'pm_tenant_id' => $tenant->id,
            'balance' => 500,
        ]);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 500,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-11', 'amount' => '500.00'],
            ],
        ]);
        $lease->units()->attach($unit->id);

        $service = app(CarryForwardConsolidationService::class);
        $result = $service->syncLease($lease->fresh(['units.property']));

        $this->assertGreaterThanOrEqual(1, (int) ($result['invoices_created'] ?? 0));
        $this->assertSame(0.0, app(TenantCreditService::class)->balanceForTenant((int) $tenant->id));

        $invoice = PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->latest('id')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(PmInvoice::STATUS_PAID, (string) $invoice->status);
    }
}
