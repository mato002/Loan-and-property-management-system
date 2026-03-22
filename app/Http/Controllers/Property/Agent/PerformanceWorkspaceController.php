<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmMaintenanceJob;
use App\Models\PmTenant;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use Carbon\Carbon;
use Illuminate\View\View;

class PerformanceWorkspaceController extends Controller
{
    public function collectionRate(): View
    {
        $out = PropertyDashboardStats::outstandingBalance();
        $col = PropertyDashboardStats::mtdCollected();
        $bundle = PropertyDashboardStats::collectionRateMtd();
        $trend = PropertyChartSeries::monthlyCollectionTrend(6);

        return view('property.agent.performance.collection_rate', [
            'stats' => [
                ['label' => 'Target', 'value' => $bundle['target'].'%', 'hint' => 'This month'],
                ['label' => 'Actual', 'value' => $bundle['actual'] !== null ? $bundle['actual'].'%' : '—', 'hint' => '% of billed'],
                ['label' => 'Collected MTD', 'value' => PropertyMoney::kes($col), 'hint' => 'Cash'],
                ['label' => 'Billed MTD', 'value' => PropertyMoney::kes(PropertyDashboardStats::mtdBilled()), 'hint' => 'Invoices'],
                ['label' => 'Collection gap', 'value' => PropertyMoney::kes($bundle['gap_kes']), 'hint' => 'Billed − collected'],
                ['label' => 'Outstanding AR', 'value' => PropertyMoney::kes($out), 'hint' => 'Open'],
            ],
            'columns' => [],
            'tableRows' => [],
            'trend' => $trend,
        ]);
    }

    public function vacancy(): View
    {
        $rate = PropertyDashboardStats::occupancyRate();
        $total = PropertyUnit::query()->count();
        $vac = PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count();
        $vacantRent = (float) PropertyUnit::query()
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->sum('rent_amount');

        $byProperty = PropertyUnit::query()
            ->with('property')
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->get()
            ->groupBy('property_id')
            ->map(fn ($g) => [
                'label' => $g->first()->property->name ?? '—',
                'value' => (float) $g->count(),
            ])
            ->values()
            ->sortByDesc('value')
            ->take(12)
            ->values()
            ->all();

        return view('property.agent.performance.vacancy', [
            'stats' => [
                ['label' => 'Occupancy', 'value' => $rate !== null ? $rate.'%' : '—', 'hint' => 'All units'],
                ['label' => 'Vacant units', 'value' => (string) $vac, 'hint' => 'Now'],
                ['label' => 'Total units', 'value' => (string) $total, 'hint' => ''],
                ['label' => 'Vacant rent exposure', 'value' => PropertyMoney::kes($vacantRent), 'hint' => 'Sum of asking rent'],
            ],
            'columns' => [],
            'tableRows' => [],
            'vacancyByProperty' => $byProperty,
        ]);
    }

    public function arrearsTrends(): View
    {
        $b7 = PropertyDashboardStats::arrearsBucket(7, 14);
        $b14 = PropertyDashboardStats::arrearsBucket(14, 30);
        $b30 = PropertyDashboardStats::arrearsBucket(30);
        $total = $b7 + $b14 + $b30;

        return view('property.agent.performance.arrears_trends', [
            'stats' => [
                ['label' => '7–14d past due', 'value' => PropertyMoney::kes($b7), 'hint' => 'Balance due'],
                ['label' => '14–30d past due', 'value' => PropertyMoney::kes($b14), 'hint' => 'Balance due'],
                ['label' => '30d+ past due', 'value' => PropertyMoney::kes($b30), 'hint' => 'Balance due'],
                ['label' => 'Aging total', 'value' => PropertyMoney::kes($total), 'hint' => 'Open invoices'],
            ],
            'columns' => ['Cohort', 'Opening', 'New', 'Cured', 'Write-off', 'Closing', 'Cure rate'],
            'tableRows' => [],
            'agingBars' => [
                ['label' => '7–14d', 'value' => $b7],
                ['label' => '14–30d', 'value' => $b14],
                ['label' => '30d+', 'value' => $b30],
            ],
        ]);
    }

    public function maintenanceTrends(): View
    {
        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $spend = (float) PmMaintenanceJob::query()
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$start, $end])
                ->sum('quote_amount');
            $series[] = ['label' => $start->format('M y'), 'value' => $spend];
        }

        return view('property.agent.performance.maintenance_trends', [
            'stats' => [
                ['label' => 'Spend (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::maintenanceSpendMtd()), 'hint' => 'Completed jobs'],
                ['label' => '6-mo total', 'value' => PropertyMoney::kes(array_sum(array_column($series, 'value'))), 'hint' => 'Quoted'],
            ],
            'columns' => ['Month', 'Reactive', 'Preventive', 'Capex', 'Total', 'vs prior'],
            'tableRows' => [],
            'maintBars' => $series,
        ]);
    }

    public function tenantReliability(): View
    {
        $tenants = PmTenant::query()->orderBy('name')->limit(200)->get();
        $elevated = $tenants->where('risk_level', '!=', 'normal')->count();

        $rows = $tenants->map(fn (PmTenant $t) => [
            $t->name,
            '—',
            $t->risk_level,
            '—',
            '—',
            '—',
        ])->all();

        return view('property.agent.performance.tenant_reliability', [
            'stats' => [
                ['label' => 'Tenants on file', 'value' => (string) $tenants->count(), 'hint' => ''],
                ['label' => 'Elevated risk flag', 'value' => (string) $elevated, 'hint' => 'Not “normal”'],
            ],
            'columns' => ['Tenant', 'Score', 'Drivers', 'Last computed', 'Human review', 'Actions'],
            'tableRows' => $rows,
        ]);
    }
}
