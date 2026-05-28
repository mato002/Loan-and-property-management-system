<?php

namespace Tests\Unit\Property;

use App\Http\Controllers\Property\Agent\PmLeaseWebController;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class LeaseCarryForwardFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_carry_forward_sql_filter_matches_opening_arrears_total_logic(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $tenant = PmTenant::query()->create(['name' => 'Tenant One']);

        $leaseWithoutCarryForward = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => null,
        ]);
        $leaseWithZeroAmount = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-12', 'amount' => '0.00'],
            ],
        ]);
        $leaseWithCarryForward = PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
            'opening_arrears' => [
                ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-11', 'amount' => '150.50'],
                ['charge_type' => 'other', 'specific_charge' => 'Ignored', 'period' => null, 'amount' => '0'],
            ],
        ]);

        $controller = app(PmLeaseWebController::class);
        $carryForwardTotal = new ReflectionMethod(PmLeaseWebController::class, 'leaseCarryForwardTotal');
        $carryForwardTotal->setAccessible(true);
        $applyCarryForwardFilter = new ReflectionMethod(PmLeaseWebController::class, 'applyLeaseCarryForwardFilter');
        $applyCarryForwardFilter->setAccessible(true);

        $allLeases = PmLease::query()->orderBy('id')->get();
        $expectedYesIds = $allLeases
            ->filter(fn (PmLease $lease): bool => $carryForwardTotal->invoke($controller, $lease) > 0)
            ->pluck('id')
            ->all();
        $expectedNoIds = $allLeases
            ->filter(fn (PmLease $lease): bool => $carryForwardTotal->invoke($controller, $lease) <= 0)
            ->pluck('id')
            ->all();

        $yesQuery = PmLease::query()->orderBy('id');
        $applyCarryForwardFilter->invoke($controller, $yesQuery, ['carry_forward' => 'yes']);
        $this->assertSame($expectedYesIds, $yesQuery->pluck('id')->all());

        $noQuery = PmLease::query()->orderBy('id');
        $applyCarryForwardFilter->invoke($controller, $noQuery, ['carry_forward' => 'no']);
        $this->assertSame($expectedNoIds, $noQuery->pluck('id')->all());

        $this->assertSame([$leaseWithCarryForward->id], $expectedYesIds);
        $this->assertSame(
            [$leaseWithoutCarryForward->id, $leaseWithZeroAmount->id],
            $expectedNoIds
        );
    }

    public function test_paginate_lease_list_uses_sql_pagination_for_carry_forward_filter(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $tenant = PmTenant::query()->create(['name' => 'Tenant One']);

        for ($i = 0; $i < 3; $i++) {
            PmLease::query()->create([
                'pm_tenant_id' => $tenant->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'monthly_rent' => 1000,
                'status' => PmLease::STATUS_ACTIVE,
                'opening_arrears' => [
                    ['charge_type' => 'rent', 'specific_charge' => 'Old rent', 'period' => '2025-12', 'amount' => '100.00'],
                ],
            ]);
        }

        $controller = app(PmLeaseWebController::class);
        $paginateLeaseList = new ReflectionMethod(PmLeaseWebController::class, 'paginateLeaseList');
        $paginateLeaseList->setAccessible(true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $pager = $paginateLeaseList->invoke(
            $controller,
            PmLease::query()->orderBy('pm_leases.id'),
            ['carry_forward' => 'yes'],
            2
        );

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $pager->count());
        $this->assertSame(3, $pager->total());
        $this->assertTrue(
            $queries->contains(fn (array $entry): bool => str_contains(strtolower($entry['query']), 'json_extract')),
            'Expected carry-forward filter to use JSON_EXTRACT in SQL.'
        );
        $this->assertTrue(
            $queries->contains(fn (array $entry): bool => str_contains(strtolower($entry['query']), 'limit')),
            'Expected paginate() to apply SQL LIMIT.'
        );
    }
}
