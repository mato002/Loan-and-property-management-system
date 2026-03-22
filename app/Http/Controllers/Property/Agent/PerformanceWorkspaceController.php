<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use Illuminate\View\View;

class PerformanceWorkspaceController extends Controller
{
    public function collectionRate(): View
    {
        $out = PropertyDashboardStats::outstandingBalance();
        $col = PropertyDashboardStats::mtdCollected();

        return view('property.agent.performance.collection_rate', [
            'stats' => [
                ['label' => 'Collected MTD', 'value' => PropertyMoney::kes($col), 'hint' => ''],
                ['label' => 'Outstanding', 'value' => PropertyMoney::kes($out), 'hint' => ''],
                ['label' => 'Gap', 'value' => PropertyMoney::kes($out), 'hint' => 'Open AR'],
            ],
            'columns' => [],
            'tableRows' => [],
        ]);
    }

    public function vacancy(): View
    {
        $rate = PropertyDashboardStats::occupancyRate();

        return view('property.agent.performance.vacancy', [
            'stats' => [
                ['label' => 'Occupancy', 'value' => $rate !== null ? $rate.'%' : '—', 'hint' => ''],
            ],
            'columns' => [],
            'tableRows' => [],
        ]);
    }

    public function arrearsTrends(): View
    {
        return view('property.agent.performance.arrears_trends', [
            'stats' => [
                ['label' => '7d bucket', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)), 'hint' => ''],
                ['label' => '14d bucket', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)), 'hint' => ''],
                ['label' => '30d+ bucket', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)), 'hint' => ''],
            ],
            'columns' => ['Cohort', 'Opening', 'New', 'Cured', 'Write-off', 'Closing', 'Cure rate'],
            'tableRows' => [],
        ]);
    }

    public function maintenanceTrends(): View
    {
        return view('property.agent.performance.maintenance_trends', [
            'stats' => [
                ['label' => 'Spend (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::maintenanceSpendMtd()), 'hint' => ''],
            ],
            'columns' => ['Month', 'Reactive', 'Preventive', 'Capex', 'Total', 'vs prior'],
            'tableRows' => [],
        ]);
    }

    public function tenantReliability(): View
    {
        return view('property.agent.performance.tenant_reliability', [
            'stats' => [
                ['label' => 'Model status', 'value' => 'Off', 'hint' => ''],
            ],
            'columns' => ['Tenant', 'Score', 'Drivers', 'Last computed', 'Human review', 'Actions'],
            'tableRows' => [],
        ]);
    }
}
