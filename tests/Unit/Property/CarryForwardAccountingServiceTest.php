<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmLeaseCarryForwardLine;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\CarryForwardAccountingService;
use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarryForwardAccountingServiceTest extends TestCase
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

    public function test_backfill_posts_opening_balance_invoice_issued_once(): void
    {
        $this->seedOpenPeriod();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $property = Property::query()->create(['name' => 'CF GL Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'CFGL1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'CF GL Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);

        $invoice = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CFGL-1',
            'issue_date' => '2025-10-01',
            'due_date' => '2025-10-01',
            'amount' => 500,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-10',
        ]);

        $service = app(CarryForwardAccountingService::class);
        $result = $service->backfillMissing((int) $tenant->id, null, 50, false);
        $service->backfillMissing((int) $tenant->id, null, 50, false);

        $this->assertSame(1, (int) $result['posted']);
        $this->assertTrue($service->hasPostedIssuance($invoice));

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->first();

        $this->assertNotNull($batch);
        $lines = AccountingJournalLine::query()->where('batch_id', $batch->id)->get();
        $this->assertSame(
            round((float) $lines->sum('debit'), 2),
            round((float) $lines->sum('credit'), 2)
        );
    }

    public function test_delta_sync_creates_invoice_with_trust_gl_posting(): void
    {
        $this->seedOpenPeriod();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $property = Property::query()->create(['name' => 'CF Sync Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'CFS1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'CF Sync Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-09', 'amount' => '300.00'],
            ],
        ]);
        $lease->units()->attach($unit->id);

        app(CarryForwardConsolidationService::class)->syncLease($lease);

        $invoice = PmInvoice::query()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertTrue(app(CarryForwardAccountingService::class)->hasPostedIssuance($invoice));
        $this->assertTrue(PropertyAccountingPostingService::isCarryForwardInvoice($invoice));
    }

    public function test_reconcile_operational_ar_vs_gl_detects_missing_issuance(): void
    {
        $property = Property::query()->create(['name' => 'CF Drift Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'CFD1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'CF Drift Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-CFD-1',
            'issue_date' => '2025-08-01',
            'due_date' => '2025-08-01',
            'amount' => 400,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' | Rent | Old rent | Period 2025-08',
        ]);

        $rows = app(CarryForwardAccountingService::class)->reconcileOperationalArVsGl((int) $tenant->id, 20);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['tenant_id'] === (int) $tenant->id));
        $this->assertTrue($rows->first(fn (array $row) => (int) $row['tenant_id'] === (int) $tenant->id)['missing_issuance_count'] >= 1);
    }
}
