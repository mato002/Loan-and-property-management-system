<?php

namespace Tests\Unit\Property;

use App\Http\Controllers\Property\Agent\PmLeaseWebController;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\PropertyDashboardCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class LeaseFormContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_lease_form_static_context_stores_arrays_only(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $controller = app(PmLeaseWebController::class);
        $method = new ReflectionMethod(PmLeaseWebController::class, 'leaseFormStaticContext');
        $method->setAccessible(true);

        $context = $method->invoke($controller);

        $this->assertIsString($context['leaseTemplate']);
        $this->assertIsArray($context['leaseFields']);
        $this->assertIsArray($context['openingArrearsTypeOptions']);
        $this->assertArrayNotHasKey('tenants', $context);
        $this->assertArrayNotHasKey('vacantUnits', $context);
    }

    public function test_lease_form_tenant_endpoint_excludes_active_lease_tenants(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $availableTenant = PmTenant::query()->create(['name' => 'Available Tenant']);
        $busyTenant = PmTenant::query()->create(['name' => 'Busy Tenant']);

        PmLease::query()->create([
            'pm_tenant_id' => $busyTenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);

        $response = app(PmLeaseWebController::class)->formTenants(request());

        $this->assertSame(200, $response->getStatusCode());
        $labels = collect($response->getData(true)['items'] ?? [])->pluck('label')->all();
        $this->assertContains('Available Tenant', $labels);
        $this->assertNotContains('Busy Tenant', $labels);
    }

    public function test_lease_form_vacant_units_endpoint_returns_assignable_units(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $property = Property::query()->create(['name' => 'Sunset Apartments']);
        $vacantUnit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'status' => PropertyUnit::STATUS_VACANT,
            'rent_amount' => 12000,
        ]);
        PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A2',
            'status' => PropertyUnit::STATUS_OCCUPIED,
            'rent_amount' => 13000,
        ]);

        $response = app(PmLeaseWebController::class)->formVacantUnits(request());

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $unitIds = collect($payload['units'] ?? [])->pluck('id')->all();
        $this->assertSame([(int) $vacantUnit->id], $unitIds);
        $this->assertSame('Sunset Apartments', $payload['properties'][0]['name'] ?? null);
    }

    public function test_forget_leases_form_context_bumps_cache_version(): void
    {
        $before = PropertyDashboardCache::leasesFormContextKey();

        PropertyDashboardCache::forgetLeasesFormContext();

        $this->assertNotSame($before, PropertyDashboardCache::leasesFormContextKey());
    }
}
