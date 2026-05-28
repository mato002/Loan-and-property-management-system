<?php

namespace Tests\Unit\Property;

use App\Http\Controllers\Property\Agent\PmLeaseWebController;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class LeaseListStatsQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-28 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_lease_list_stats_return_expected_counts_with_filters(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $tenant = PmTenant::query()->create(['name' => 'Tenant One']);

        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_DRAFT,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-20',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-07-27',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-09-01',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_TERMINATED,
        ]);

        $controller = app(PmLeaseWebController::class);
        $applyFilters = new ReflectionMethod(PmLeaseWebController::class, 'applyLeaseListFilters');
        $applyFilters->setAccessible(true);
        $statsMethod = new ReflectionMethod(PmLeaseWebController::class, 'leaseListStatsFromQuery');
        $statsMethod->setAccessible(true);

        $query = PmLease::query();
        $applyFilters->invoke($controller, $query, ['q' => ''], true, false);
        $stats = $statsMethod->invoke($controller, $query);

        $this->assertSame([
            ['label' => 'All leases', 'value' => '5', 'hint' => ''],
            ['label' => 'Active', 'value' => '3', 'hint' => ''],
            ['label' => 'Ending ≤60d', 'value' => '2', 'hint' => ''],
            ['label' => 'Draft', 'value' => '1', 'hint' => ''],
        ], $stats);

        $filteredQuery = PmLease::query();
        $applyFilters->invoke($controller, $filteredQuery, ['q' => '', 'status' => PmLease::STATUS_ACTIVE], true, false);
        $filteredStats = $statsMethod->invoke($controller, $filteredQuery);

        $this->assertSame('3', $filteredStats[0]['value']);
        $this->assertSame('3', $filteredStats[1]['value']);
        $this->assertSame('2', $filteredStats[2]['value']);
        $this->assertSame('0', $filteredStats[3]['value']);
    }

    public function test_lease_list_stats_use_single_aggregate_query(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $tenant = PmTenant::query()->create(['name' => 'Tenant One']);
        PmLease::query()->create([
            'pm_tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);

        $controller = app(PmLeaseWebController::class);
        $statsMethod = new ReflectionMethod(PmLeaseWebController::class, 'leaseListStatsFromQuery');
        $statsMethod->setAccessible(true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $statsMethod->invoke($controller, PmLease::query());

        $statsQueries = collect(DB::getQueryLog())
            ->filter(fn (array $entry): bool => str_contains(strtolower($entry['query']), 'sum(case'))
            ->values();

        DB::disableQueryLog();

        $this->assertCount(1, $statsQueries);
        $this->assertSame('1', $statsMethod->invoke($controller, PmLease::query())[0]['value']);
    }
}
