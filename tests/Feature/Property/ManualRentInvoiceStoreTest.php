<?php

namespace Tests\Feature\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualRentInvoiceStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: PmTenant, lease: PmLease, unit: PropertyUnit}
     */
    private function rentFixture(): array
    {
        $property = Property::query()->create(['name' => 'Dup Test Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 12000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Dup Tenant']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 12000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        return ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit];
    }

    public function test_manual_rent_invoice_rejects_duplicate_lease_unit_issue_month(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->rentFixture();

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-DUP-001',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'amount' => 12000,
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
            'amount' => 12000,
            'status' => 'draft',
            'invoice_type' => 'rent',
            'idempotency_key' => 'test-dup-'.uniqid('', true),
        ];

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->post(route('property.invoices.store'), $payload);

        $response->assertSessionHasErrors('issue_date');
        $this->assertSame(1, PmInvoice::query()->count());
    }

    public function test_manual_rent_invoice_allows_different_issue_month(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        ['tenant' => $tenant, 'lease' => $lease, 'unit' => $unit] = $this->rentFixture();

        PmInvoice::query()->create([
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-DUP-001',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-05',
            'amount' => 12000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
            'billing_period' => '2026-05',
        ]);

        $payload = [
            'pm_lease_id' => $lease->id,
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'amount' => 12000,
            'status' => 'draft',
            'invoice_type' => 'rent',
            'idempotency_key' => 'test-next-month-'.uniqid('', true),
        ];

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->post(route('property.invoices.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(2, PmInvoice::query()->count());
        $this->assertDatabaseHas('pm_invoices', [
            'pm_lease_id' => $lease->id,
            'issue_date' => '2026-06-01',
            'billing_period' => '2026-06',
        ]);
    }

    public function test_manual_rent_invoice_requires_lease(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        ['tenant' => $tenant, 'unit' => $unit] = $this->rentFixture();

        $payload = [
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'amount' => 12000,
            'status' => 'draft',
            'invoice_type' => 'rent',
            'idempotency_key' => 'test-no-lease-'.uniqid('', true),
        ];

        $response = $this->actingAs($user)
            ->withSession(['active_system' => 'property'])
            ->post(route('property.invoices.store'), $payload);

        $response->assertSessionHasErrors('pm_lease_id');
        $this->assertSame(0, PmInvoice::query()->count());
    }
}
