<?php

namespace Tests\Unit\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\RentDueDayResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentDueDayResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['property.rent_due_day_default' => 5]);
        PropertyPortalSetting::setValue('property_rent_due_day_default', null);
    }

    public function test_system_default_is_five_when_unconfigured(): void
    {
        config(['property.rent_due_day_default' => 5]);

        $this->assertSame(5, app(RentDueDayResolver::class)->systemDefaultDueDay());
    }

    public function test_lease_override_beats_property_and_system(): void
    {
        config(['property.rent_due_day_default' => 5]);

        $property = Property::query()->create(['name' => 'Tower', 'rent_due_day' => 10]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U1',
            'rent_amount' => 10000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant A']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2024-03-15',
            'rent_due_day' => 12,
            'monthly_rent' => 10000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        $this->assertSame(12, app(RentDueDayResolver::class)->resolveDueDay($lease->load('units.property')));
    }

    public function test_property_default_beats_system_when_lease_override_null(): void
    {
        config(['property.rent_due_day_default' => 5]);

        $property = Property::query()->create(['name' => 'Block', 'rent_due_day' => 8]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U2',
            'rent_amount' => 8000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant B']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2024-01-20',
            'rent_due_day' => null,
            'monthly_rent' => 8000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        $this->assertSame(8, app(RentDueDayResolver::class)->resolveDueDay($lease->load('units.property')));
    }

    public function test_portal_setting_overrides_env_system_default(): void
    {
        config(['property.rent_due_day_default' => 5]);
        PropertyPortalSetting::setValue('property_rent_due_day_default', '7');

        $this->assertSame(7, app(RentDueDayResolver::class)->systemDefaultDueDay());
    }

    public function test_due_date_for_june_uses_fifth_by_default(): void
    {
        config(['property.rent_due_day_default' => 5]);

        $property = Property::query()->create(['name' => 'Estate']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U3',
            'rent_amount' => 5000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant C']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2024-11-23',
            'monthly_rent' => 5000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        $due = app(RentDueDayResolver::class)->dueDateForBillingMonth(
            $lease->load('units.property'),
            Carbon::parse('2026-06-01')
        );

        $this->assertSame('2026-06-05', $due);
    }

    public function test_clamps_due_day_to_month_length(): void
    {
        $property = Property::query()->create(['name' => 'Feb', 'rent_due_day' => 31]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'U4',
            'rent_amount' => 3000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Tenant D']);
        $lease = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2024-01-01',
            'monthly_rent' => 3000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        $lease->units()->attach($unit->id);

        $due = app(RentDueDayResolver::class)->dueDateForBillingMonth(
            $lease->load('units.property'),
            Carbon::parse('2026-02-01')
        );

        $this->assertSame('2026-02-28', $due);
    }
}
