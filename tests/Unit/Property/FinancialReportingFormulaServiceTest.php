<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\FinancialReportingFormulaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingFormulaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedTenantWithInvoices(): array
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Formula Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'F1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Formula Tenant']);

        $open = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OPEN-001',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-10',
            'amount' => 1000,
            'amount_paid' => 200,
            'balance_due' => 800,
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

        return compact('tenant', 'open', 'unit');
    }

    public function test_billed_for_period_excludes_draft_and_cancelled(): void
    {
        $this->seedTenantWithInvoices();
        $service = app(FinancialReportingFormulaService::class);

        $billed = $service->billedForPeriod(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-02-28'),
        );

        $this->assertSame(1000.0, $billed);
    }

    public function test_collections_use_allocations_not_payment_header(): void
    {
        ['tenant' => $tenant, 'open' => $open] = $this->seedTenantWithInvoices();

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'amount' => 9999,
            'status' => PmPayment::STATUS_COMPLETED,
            'channel' => 'mpesa',
            'paid_at' => '2026-01-15 10:00:00',
        ]);

        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $open->id,
            'amount' => 200,
        ]);

        $service = app(FinancialReportingFormulaService::class);

        $this->assertSame(200.0, $service->tenantCollectionsTotal((int) $tenant->id));
        $this->assertSame(200.0, $service->collectionsFromPayments([$payment->load('allocations')]));
    }

    public function test_tenant_total_due_breakdown_uses_invoice_ar_minus_credit(): void
    {
        ['tenant' => $tenant] = $this->seedTenantWithInvoices();
        $service = app(FinancialReportingFormulaService::class);

        $breakdown = $service->tenantTotalDueBreakdown($tenant->fresh());

        $this->assertSame(800.0, $breakdown['invoice_ar']);
        $this->assertSame(0.0, $breakdown['uninvoiced_cf']);
        $this->assertSame(0.0, $breakdown['tenant_credit']);
        $this->assertSame(800.0, $breakdown['total_due']);
        $this->assertSame(800.0, $service->tenantStatementClosingBalance((int) $tenant->id));
    }
}
