<?php

namespace Tests\Unit\Property;

use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Server-side performance probes for Tenants & Leasing verification (Phase 8).
 *
 * Run: php artisan test tests/Unit/Property/TenantsLeasingPerformanceBenchmarkTest.php
 */
class TenantsLeasingPerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-28 09:00:00');

        $this->agent = User::factory()->create([
            'is_super_admin' => true,
            'property_portal_role' => 'agent',
        ]);

        $this->seedBenchmarkDataset();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_benchmark_property_units_directory_and_leases_pages(): void
    {
        $this->actingAs($this->agent);
        $this->withSession(['active_system' => 'property']);

        $pages = [
            'portfolio_units' => route('property.properties.units', absolute: false),
            'tenant_directory' => route('property.tenants.directory', absolute: false),
            'tenant_leases' => route('property.tenants.leases', absolute: false),
            'lease_create_form' => route('property.leases.create_form', absolute: false),
        ];

        $results = [];
        foreach ($pages as $key => $url) {
            $results[$key] = $this->measureTurboFrameRequest($url);
        }

        $results['leases_carry_forward_filter'] = $this->measureTurboFrameRequest(
            route('property.tenants.leases', ['carry_forward' => 'yes'], false)
        );

        fwrite(STDERR, "\n=== TENANTS & LEASING BENCHMARK (after optimizations) ===\n");
        fwrite(STDERR, json_encode($results, JSON_PRETTY_PRINT)."\n");

        $this->assertSame(200, $results['portfolio_units']['status']);
        $this->assertSame(200, $results['tenant_directory']['status']);
        $this->assertSame(200, $results['tenant_leases']['status']);
        $this->assertSame(200, $results['lease_create_form']['status']);

        $this->assertLessThanOrEqual(
            $results['portfolio_units']['query_count'] + 8,
            $results['tenant_leases']['query_count'],
            'Leases list query count should stay close to Portfolio Units baseline.'
        );

        $this->assertSame(0, $results['tenant_leases']['inline_create_form_markers']);
        $this->assertSame(0, $results['tenant_leases']['inline_lease_form_logic_markers']);
        $this->assertGreaterThan(0, $results['lease_create_form']['lease_form_context_endpoints']);
        $this->assertSame(0, $results['tenant_leases']['lease_form_context_endpoints']);

        $this->assertTrue(
            $results['leases_carry_forward_filter']['uses_sql_limit'],
            'Carry-forward filter should paginate in SQL.'
        );

        $this->assertFalse(
            $results['tenant_directory']['stats_loads_all_tenant_rows'],
            'Directory stats must not select all tenant rows.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function measureTurboFrameRequest(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startedAt = microtime(true);
        $response = $this->withHeaders([
            'Turbo-Frame' => $url === route('property.leases.create_form', absolute: false)
                ? 'lease-create-modal'
                : 'property-main',
        ])->get($url);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $body = (string) $response->getContent();
        $queryTexts = $queries->pluck('query')->map(fn (string $q): string => strtolower($q));

        return [
            'url' => $url,
            'status' => $response->getStatusCode(),
            'elapsed_ms' => round($elapsedMs, 2),
            'query_count' => $queries->count(),
            'response_bytes' => strlen($body),
            'contains_turbo_frame' => str_contains($body, 'turbo-frame') || str_contains($body, '<turbo-frame'),
            'contains_property_main' => str_contains($body, 'property-main') || str_contains($body, 'lease-create-modal'),
            'inline_create_form_markers' => substr_count($body, 'id="lease-form-wrapper"'),
            'inline_lease_form_logic_markers' => substr_count($body, 'initLeaseFormLogic'),
            'stats_loads_all_tenant_rows' => $queryTexts->contains(
                fn (string $q): bool => preg_match('/select\s+\*\s+from\s+[`"]?pm_tenants[`"]?\s*(;|$)/i', $q) === 1
                    && ! str_contains($q, 'limit')
            ),
            'stats_aggregate_query' => $queryTexts->contains(
                fn (string $q): bool => str_contains($q, 'sum(case') || str_contains($q, 'count(*)')
            ),
            'uses_sql_limit' => $queryTexts->contains(fn (string $q): bool => str_contains($q, 'limit')),
            'carry_forward_json_extract' => $queryTexts->contains(
                fn (string $q): bool => str_contains($q, 'json_extract') && str_contains($q, 'opening_arrears')
            ),
            'lease_form_context_endpoints' => substr_count($body, 'leaseFormEndpoints'),
            'action_menu_shells' => substr_count($body, 'data-property-dropdown-root'),
        ];
    }

    private function seedBenchmarkDataset(): void
    {
        $properties = collect(range(1, 4))->map(function (int $index): Property {
            return Property::query()->create([
                'name' => 'Benchmark Property '.$index,
            ]);
        });

        $units = collect();
        foreach ($properties as $property) {
            foreach (range(1, 15) as $unitIndex) {
                $units->push(PropertyUnit::query()->create([
                    'property_id' => $property->id,
                    'label' => 'U'.$unitIndex,
                    'status' => $unitIndex % 3 === 0
                        ? PropertyUnit::STATUS_VACANT
                        : PropertyUnit::STATUS_OCCUPIED,
                    'rent_amount' => 10000 + ($unitIndex * 250),
                    'bedrooms' => ($unitIndex % 4) + 1,
                    'unit_type' => 'apartment',
                ]));
            }
        }

        $tenants = collect(range(1, 40))->map(function (int $index): PmTenant {
            return PmTenant::query()->create([
                'name' => 'Benchmark Tenant '.$index,
                'risk_level' => $index % 5 === 0 ? 'high' : 'normal',
                'user_id' => $index % 8 === 0 ? User::factory()->create()->id : null,
            ]);
        });

        foreach (range(1, 80) as $leaseIndex) {
            $tenant = $tenants->get($leaseIndex % $tenants->count());
            $unit = $units->get($leaseIndex % $units->count());
            $status = match ($leaseIndex % 6) {
                0 => PmLease::STATUS_DRAFT,
                1 => PmLease::STATUS_TERMINATED,
                default => PmLease::STATUS_ACTIVE,
            };

            $lease = PmLease::query()->create([
                'pm_tenant_id' => $tenant->id,
                'start_date' => '2026-01-01',
                'end_date' => $leaseIndex % 7 === 0 ? '2026-07-15' : '2026-12-31',
                'monthly_rent' => 10000 + ($leaseIndex * 100),
                'status' => $status,
                'opening_arrears' => $leaseIndex % 4 === 0
                    ? [['charge_type' => 'rent', 'specific_charge' => 'Arrears', 'period' => '2025-12', 'amount' => '150.00']]
                    : [],
            ]);

            $lease->units()->sync([(int) $unit->id]);
        }
    }
}
