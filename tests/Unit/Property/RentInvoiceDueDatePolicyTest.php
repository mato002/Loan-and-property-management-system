<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\InvoiceStateIntegrityService;
use App\Services\Property\RentInvoiceGenerator;
use App\Services\Property\TenantCommunicationStageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentInvoiceDueDatePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['property.rent_due_day_default' => 5]);
        PropertyPortalSetting::setValue('property_rent_due_day_default', null);
        PropertyPortalSetting::setValue('workflow_auto_rent_invoices', '1');
    }

    public function test_new_june_invoice_issued_first_due_fifth(): void
    {
        $lease = $this->seedActiveLease(startDate: '2024-03-15');

        $this->artisan('rent:generate-invoices', ['--month' => '2026-06'])->assertSuccessful();

        $invoice = PmInvoice::query()
            ->where('pm_lease_id', $lease->id)
            ->where('billing_period', '2026-06')
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame('2026-06-01', $invoice->issue_date->toDateString());
        $this->assertSame('2026-06-05', $invoice->due_date->toDateString());
    }

    public function test_existing_invoice_due_date_is_not_changed_by_generation(): void
    {
        $lease = $this->seedActiveLease(startDate: '2024-03-15');
        $unit = $lease->units()->first();

        $existing = PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $lease->pm_tenant_id,
            'invoice_no' => 'INV-LEGACY-001',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'billing_period' => '2026-06',
        ]);

        $this->artisan('rent:generate-invoices', ['--month' => '2026-06'])->assertSuccessful();

        $existing->refresh();
        $this->assertSame('2026-06-15', $existing->due_date->toDateString());
        $this->assertSame(1, PmInvoice::query()->where('pm_lease_id', $lease->id)->where('billing_period', '2026-06')->count());
    }

    public function test_arrears_starts_on_sixth_for_invoice_due_fifth(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Arrears Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Arrears Tenant']);

        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-ARR-001',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-05',
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-05', config('app.timezone'))->endOfDay());
        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();
        $this->assertFalse(app(InvoiceStateIntegrityService::class)->expectedPastDue($invoice, $invoice->balanceFloat()));

        Carbon::setTestNow(Carbon::parse('2026-06-06', config('app.timezone'))->startOfDay());
        $invoice->syncAmountPaidFromAllocations();
        $invoice->refresh();
        $this->assertTrue((bool) $invoice->is_past_due);

        Carbon::setTestNow();
    }

    public function test_reminder_stages_for_due_fifth_policy(): void
    {
        $service = app(TenantCommunicationStageService::class);
        $due = Carbon::parse('2026-06-05');

        $d3 = $service->resolveFromDueDate($due, Carbon::parse('2026-06-02'));
        $this->assertNotNull($d3);
        $this->assertSame('D-3', $d3['internal_stage']);
        $this->assertSame('Due in 3 Days', $d3['display_label']);

        $d1 = $service->resolveFromDueDate($due, Carbon::parse('2026-06-04'));
        $this->assertSame('D-1', $d1['internal_stage']);
        $this->assertSame('Due Tomorrow', $d1['display_label']);

        $today = $service->resolveFromDueDate($due, Carbon::parse('2026-06-05'));
        $this->assertSame('D+0', $today['internal_stage']);
        $this->assertSame('Due Today', $today['display_label']);

        $oneOver = $service->resolveFromDueDate($due, Carbon::parse('2026-06-06'));
        $this->assertSame('D+1', $oneOver['internal_stage']);
        $this->assertSame('1 Day Overdue', $oneOver['display_label']);

        $seven = $service->resolveFromDueDate($due, Carbon::parse('2026-06-12'));
        $this->assertSame('D+7', $seven['internal_stage']);
        $this->assertSame('7 Days Overdue', $seven['display_label']);
    }

    public function test_uninvoiced_preview_uses_resolved_due_day(): void
    {
        $lease = $this->seedActiveLease(startDate: '2024-11-23');
        $rows = app(RentInvoiceGenerator::class)->reportRows('2026-06');
        $row = collect($rows)->firstWhere('lease_id', $lease->id);

        $this->assertNotNull($row);
        $this->assertSame('2026-06-05', $row['due_date']);
    }

    private function seedActiveLease(string $startDate): PmLease
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Policy Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'P1',
            'rent_amount' => 10000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Policy Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => $startDate,
            'monthly_rent' => 10000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        return $lease->fresh(['units.property']);
    }
}
