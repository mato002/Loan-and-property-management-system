<?php

namespace Tests\Unit\Property;

use App\Http\Controllers\Property\Agent\PmTenantDirectoryController;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class TenantDirectoryStatsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_directory_stats_return_expected_counts_with_filters(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        $withPortal = PmTenant::query()->create([
            'name' => 'Portal Tenant',
            'risk_level' => 'high',
            'user_id' => User::factory()->create()->id,
        ]);
        $highRisk = PmTenant::query()->create([
            'name' => 'High Risk Tenant',
            'risk_level' => 'high',
        ]);
        $normal = PmTenant::query()->create([
            'name' => 'Normal Tenant',
            'risk_level' => 'normal',
        ]);

        PmLease::query()->create([
            'pm_tenant_id' => $withPortal->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 1000,
            'status' => PmLease::STATUS_ACTIVE,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $withPortal->id,
            'start_date' => '2026-02-01',
            'end_date' => '2027-01-31',
            'monthly_rent' => 1200,
            'status' => PmLease::STATUS_DRAFT,
        ]);
        PmLease::query()->create([
            'pm_tenant_id' => $normal->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_rent' => 900,
            'status' => PmLease::STATUS_ACTIVE,
        ]);

        $controller = app(PmTenantDirectoryController::class);
        $statsMethod = new ReflectionMethod(PmTenantDirectoryController::class, 'tenantDirectoryStatsFromQuery');
        $statsMethod->setAccessible(true);

        $stats = $statsMethod->invoke($controller, Request::create('/tenants/directory', 'GET'));

        $this->assertSame([
            ['label' => 'Tenants', 'value' => '3', 'hint' => 'Filtered records'],
            ['label' => 'With portal login', 'value' => '1', 'hint' => 'Linked user'],
            ['label' => 'High risk flagged', 'value' => '2', 'hint' => 'Manual'],
            ['label' => 'Total leases', 'value' => '3', 'hint' => 'Linked'],
        ], $stats);

        $filteredStats = $statsMethod->invoke(
            $controller,
            Request::create('/tenants/directory', 'GET', ['risk' => 'high'])
        );

        $this->assertSame('2', $filteredStats[0]['value']);
        $this->assertSame('1', $filteredStats[1]['value']);
        $this->assertSame('2', $filteredStats[2]['value']);
        $this->assertSame('2', $filteredStats[3]['value']);
    }

    public function test_tenant_directory_stats_do_not_load_full_tenant_collection(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]));

        PmTenant::query()->create([
            'name' => 'Tenant One',
            'risk_level' => 'normal',
        ]);

        $controller = app(PmTenantDirectoryController::class);
        $statsMethod = new ReflectionMethod(PmTenantDirectoryController::class, 'tenantDirectoryStatsFromQuery');
        $statsMethod->setAccessible(true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $statsMethod->invoke($controller, Request::create('/tenants/directory', 'GET'));

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertFalse(
            collect($queries)->contains(fn (array $entry): bool => preg_match('/select\s+\*\s+from\s+"pm_tenants"/i', $entry['query']) === 1),
            'Stats should not load full tenant rows.'
        );
        $this->assertTrue(
            collect($queries)->contains(fn (array $entry): bool => str_contains(strtolower($entry['query']), 'sum(case')),
            'Stats should use tenant aggregate query.'
        );
    }
}
