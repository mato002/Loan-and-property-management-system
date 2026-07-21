<?php

namespace Tests\Unit\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\FinanceFirebreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceFirebreakServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: PmTenant, lease: PmLease, unit: PropertyUnit}
     */
    private function seedLeaseWithUnit(): array
    {
        $property = Property::query()->create(['name' => 'Firebreak Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant A']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-11', 'amount' => '500.00'],
            ],
        ]);

        return compact('tenant', 'lease', 'unit');
    }

    public function test_paid_carry_forward_invoice_is_protected_from_prune(): void
    {
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->seedLeaseWithUnit();

        $paidInvoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000101',
            'issue_date' => '2025-11-01',
            'due_date' => '2025-11-01',
            'amount' => 500,
            'amount_paid' => 200,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-11',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-11',
        ]);

        $unpaidInvoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000102',
            'issue_date' => '2025-10-01',
            'due_date' => '2025-10-01',
            'amount' => 300,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_OVERDUE,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-10',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-10',
        ]);

        $service = app(FinanceFirebreakService::class);
        $result = $service->pruneUnprotectedCarryForwardInvoices($lease);

        $this->assertTrue($service->isCarryForwardInvoiceProtected($paidInvoice));
        $this->assertFalse($service->isCarryForwardInvoiceProtected($unpaidInvoice));
        $this->assertSame(1, $result['deleted']);
        $this->assertCount(1, $result['preserved']);
        $this->assertSame((int) $paidInvoice->id, (int) $result['preserved']->first()->id);

        $this->assertDatabaseHas('pm_invoices', ['id' => $paidInvoice->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('pm_invoices', ['id' => $unpaidInvoice->id]);

        $this->assertDatabaseHas('pm_finance_audit_logs', [
            'action' => PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION_SKIPPED,
            'pm_invoice_id' => $paidInvoice->id,
        ]);
    }

    public function test_allocation_drift_detector_finds_mismatch(): void
    {
        $property = Property::query()->create(['name' => 'Drift Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U2',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant B']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000201',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 1000,
            'amount_paid' => 500,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'cash',
            'amount' => 300,
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);

        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 300,
        ]);

        $drift = app(FinanceFirebreakService::class)->detectAllocationDrift(null, 50);

        $this->assertTrue($drift->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id));
        $this->assertSame(200.0, (float) $drift->firstWhere('invoice_id', $invoice->id)['drift']);
    }

    public function test_carry_forward_warnings_detect_json_mismatch_and_tenant_duplicate(): void
    {
        $property = Property::query()->create(['name' => 'Warning Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U3',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create([
            'name' => 'Tenant C',
            'opening_arrears_amount' => 750,
        ]);

        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-09', 'amount' => '1000.00'],
            ],
        ]);

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-000301',
            'issue_date' => '2025-09-01',
            'due_date' => '2025-09-01',
            'amount' => 600,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_OVERDUE,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'billing_period' => '2025-09',
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-09',
        ]);

        $warnings = app(FinanceFirebreakService::class)->carryForwardWarnings($lease);
        $types = collect($warnings)->pluck('type')->all();

        $this->assertContains(PmFinanceAuditLog::ACTION_CARRY_FORWARD_JSON_MISMATCH, $types);
        $this->assertContains(PmFinanceAuditLog::ACTION_TENANT_OPENING_ARREARS_DUPLICATE, $types);
    }
}
