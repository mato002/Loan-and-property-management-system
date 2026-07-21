<?php

namespace Tests\Feature\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\RentInvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentIncreaseSupplementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: PmTenant, lease: PmLease, unit: PropertyUnit}
     */
    private function rentFixture(): array
    {
        $property = Property::query()->create(['name' => 'Supplement Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'B2',
            'rent_amount' => 10000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Supplement Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 15000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        return ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit];
    }

    public function test_report_marks_underbilled_after_lease_rent_increase(): void
    {
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->rentFixture();

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OLD-001',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'billing_period' => '2026-05',
        ]);

        $rows = app(RentInvoiceGenerator::class)->reportRows('2026-05');
        $row = collect($rows)->firstWhere('key', $lease->id.'-'.$unit->id);

        $this->assertNotNull($row);
        $this->assertSame(RentInvoiceGenerator::REASON_UNDERBILLED, $row['reason']);
        $this->assertSame(10000.0, (float) $row['invoiced_amount']);
        $this->assertSame(15000.0, (float) $row['expected_amount']);
        $this->assertSame(5000.0, (float) $row['bill_amount']);
        $this->assertTrue($row['can_generate_supplement']);
    }

    public function test_generate_rent_supplement_creates_delta_invoice(): void
    {
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->rentFixture();

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OLD-002',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'billing_period' => '2026-05',
        ]);

        $result = app(RentInvoiceGenerator::class)->generateRentSupplements(
            '2026-05',
            [$lease->id.'-'.$unit->id],
            null,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $supplement = PmInvoice::query()
            ->where('invoice_kind', PmInvoice::KIND_RENT_SUPPLEMENT)
            ->first();

        $this->assertNotNull($supplement);
        $this->assertSame(5000.0, (float) $supplement->amount);
        $this->assertSame('2026-05', (string) $supplement->billing_period);

        $rows = app(RentInvoiceGenerator::class)->reportRows('2026-05');
        $row = collect($rows)->firstWhere('key', $lease->id.'-'.$unit->id);
        $this->assertSame(RentInvoiceGenerator::REASON_ALREADY, $row['reason']);
    }

    public function test_manual_rent_invoice_still_rejected_when_partial_month_exists(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->rentFixture();

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-OLD-003',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'billing_period' => '2026-05',
        ]);

        $payload = [
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'issue_date' => '2026-05-15',
            'due_date' => '2026-05-20',
            'amount' => 5000,
            'status' => 'draft',
            'invoice_type' => 'rent',
            'billing_period' => '2026-05',
            'idempotency_key' => 'test-partial-'.uniqid('', true),
        ];

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->post(route('property.invoices.store'), $payload);

        $response->assertSessionHasErrors('issue_date');
        $this->assertSame(1, PmInvoice::query()->count());
    }
}
