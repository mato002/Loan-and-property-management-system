<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Property\UtilityIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilityAnalyticsController extends Controller
{
    public function __construct(
        private readonly UtilityIntelligenceService $intelligence,
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'property' => ['nullable', 'integer'],
            'months' => ['nullable', 'integer', 'min:6', 'max:24'],
        ]);

        $propertyId = (int) ($validated['property'] ?? $request->query('property', 0)) ?: null;
        $months = (int) ($validated['months'] ?? $request->query('months', 12));

        $data = $this->intelligence->dashboard([
            'property_id' => $propertyId,
            'months' => $months,
        ]);

        $kpis = $data['kpis'];
        $comparison = $data['comparison'];

        return property_view('property.agent.revenue.utility_analytics', [
            'data' => $data,
            'filters' => [
                'property' => $propertyId ? (string) $propertyId : '',
                'months' => (string) $months,
            ],
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'stats' => [
                ['label' => 'Avg usage / reading', 'value' => number_format((float) $kpis['average_usage'], 2).' m³', 'hint' => 'Portfolio average'],
                ['label' => 'Usage / bedroom', 'value' => number_format((float) $kpis['usage_per_bedroom'], 2).' m³', 'hint' => 'Efficiency KPI'],
                ['label' => 'MoM change', 'value' => ($kpis['mom_change_pct'] >= 0 ? '+' : '').$kpis['mom_change_pct'].'%', 'hint' => 'Latest vs prior month'],
                ['label' => 'Anomalies', 'value' => (string) ($comparison['total_anomalies'] ?? 0), 'hint' => 'Detected signals'],
            ],
            'kpiCards' => [
                [
                    'label' => 'Occupancy-adjusted usage',
                    'value' => number_format((float) $kpis['occupancy_adjusted_usage'], 1).' m³',
                    'icon' => 'fa-chart-line',
                    'bar' => 'bg-teal-500',
                    'route' => 'property.revenue.utilities.analytics',
                ],
                [
                    'label' => 'Monitored units',
                    'value' => (string) ($kpis['monitored_units'] ?? 0),
                    'icon' => 'fa-droplet',
                    'bar' => 'bg-cyan-500',
                    'route' => 'property.revenue.utilities',
                ],
                [
                    'label' => 'Risk alerts',
                    'value' => (string) count($data['alerts'] ?? []),
                    'icon' => 'fa-triangle-exclamation',
                    'bar' => 'bg-amber-500',
                    'route' => 'property.revenue.utilities.analytics',
                ],
                [
                    'label' => 'Reconciliation',
                    'value' => 'Open',
                    'icon' => 'fa-scale-balanced',
                    'bar' => 'bg-indigo-500',
                    'route' => 'property.revenue.utilities.reconciliation',
                ],
            ],
        ]);
    }
}
