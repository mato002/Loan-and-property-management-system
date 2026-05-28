<?php

namespace App\Services\Property;

use App\Models\PmLease;
use App\Models\PmWaterReading;
use App\Models\PropertyUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UtilityIntelligenceService
{
    private const CACHE_TTL_SECONDS = 600;

    private const SPIKE_MULTIPLIER = 2.5;

    private const DROP_RATIO = 0.3;

    private const LEAK_MULTIPLIER = 1.5;

    private const LEAK_CONSECUTIVE = 3;

    private const TREND_MONTHS = 12;

    /**
     * @param  array{property_id?: int|null, months?: int, agent_user_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        $agentId = (int) ($filters['agent_user_id'] ?? auth()->id());
        $propertyId = isset($filters['property_id']) ? (int) $filters['property_id'] : null;
        if ($propertyId !== null && $propertyId <= 0) {
            $propertyId = null;
        }
        $months = min(24, max(6, (int) ($filters['months'] ?? self::TREND_MONTHS)));

        $cacheKey = sprintf(
            'utility_intelligence:%d:%s:%d',
            $agentId,
            $propertyId ?? 'all',
            $months
        );

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => $this->buildDashboard($propertyId, $months));
    }

    /**
     * @param  array<int>  $readingIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function anomalyMapForReadingIds(array $readingIds): array
    {
        if ($readingIds === []) {
            return [];
        }

        $payload = $this->dashboard([]);
        $byReading = (array) ($payload['anomaly_by_reading_id'] ?? []);
        $result = [];
        foreach ($readingIds as $id) {
            $id = (int) $id;
            if (isset($byReading[$id])) {
                $result[$id] = $byReading[$id];
            }
        }

        return $result;
    }

    public function forgetCache(?int $agentUserId = null): void
    {
        $agentId = $agentUserId ?? (int) auth()->id();
        if ($agentId <= 0) {
            return;
        }

        foreach (['all'] as $scope) {
            foreach ([6, 12, 18, 24] as $months) {
                Cache::forget(sprintf('utility_intelligence:%d:%s:%d', $agentId, $scope, $months));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboard(?int $propertyId, int $months): array
    {
        $fromMonth = now()->subMonths($months - 1)->format('Y-m');
        $readings = $this->loadReadings($fromMonth, $propertyId);
        $histories = $readings->groupBy('property_unit_id');

        $anomalies = $this->detectAnomalies($histories, $propertyId);
        $alerts = $this->buildAlerts($anomalies, $propertyId);

        $monthlyTrends = $this->monthlyTrends($readings);
        $propertyTrends = $this->propertyTrends($readings);
        $unitTypeTrends = $this->unitTypeTrends($readings);
        $tenantTrends = $this->tenantTrends($readings);
        $seasonal = $this->seasonalUsage($readings);
        $heatmap = $this->consumptionHeatmap($readings, $months);
        $kpis = $this->efficiencyKpis($readings, $histories, $propertyId);
        $propertyPerformance = $this->propertyPerformance($readings, $kpis);
        $anomalyByReadingId = $this->indexAnomaliesByReading($anomalies);

        return [
            'filters' => [
                'property_id' => $propertyId,
                'months' => $months,
                'from_month' => $fromMonth,
            ],
            'kpis' => $kpis,
            'alerts' => $alerts,
            'anomalies' => $anomalies,
            'anomaly_by_reading_id' => $anomalyByReadingId,
            'monthly_trends' => $monthlyTrends,
            'property_trends' => $propertyTrends,
            'unit_type_trends' => $unitTypeTrends,
            'tenant_trends' => $tenantTrends,
            'seasonal' => $seasonal,
            'heatmap' => $heatmap,
            'property_performance' => $propertyPerformance,
            'comparison' => $this->comparisonWidgets($monthlyTrends, $propertyTrends, $anomalies),
        ];
    }

    /**
     * @return Collection<int, PmWaterReading>
     */
    private function loadReadings(string $fromMonth, ?int $propertyId): Collection
    {
        return PmWaterReading::query()
            ->with(['unit.property'])
            ->when($propertyId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('property_id', $propertyId)))
            ->where('billing_month', '>=', $fromMonth)
            ->orderBy('billing_month')
            ->get();
    }

    /**
     * @param  Collection<int, Collection<int, PmWaterReading>>  $histories
     * @return list<array<string, mixed>>
     */
    private function detectAnomalies(Collection $histories, ?int $propertyId): array
    {
        $anomalies = [];

        foreach ($histories as $unitId => $unitReadings) {
            /** @var Collection<int, PmWaterReading> $unitReadings */
            $sorted = $unitReadings->sortBy('billing_month')->values();
            $values = $sorted->map(fn (PmWaterReading $r) => (float) $r->units_used)->all();
            $avg = count($values) > 0 ? array_sum($values) / count($values) : 0.0;

            foreach ($sorted as $index => $reading) {
                $used = (float) $reading->units_used;
                $prior = $index > 0 ? (float) $sorted[$index - 1]->units_used : null;
                $rolling = array_slice($values, max(0, $index - 6), min(6, $index));
                $rollingAvg = count($rolling) > 0 ? array_sum($rolling) / count($rolling) : 0.0;
                $base = $rollingAvg > 0 ? $rollingAvg : $avg;

                if ($used <= 0 && ! $reading->is_meter_reset) {
                    $unitStatus = (string) ($reading->unit?->status ?? '');
                    if ($unitStatus === PropertyUnit::STATUS_OCCUPIED) {
                        $anomalies[] = $this->anomalyRow($reading, 'zero_usage', 'critical', 'Zero usage on occupied unit', $used, $base);
                    } else {
                        $anomalies[] = $this->anomalyRow($reading, 'zero_usage', 'warning', 'Zero usage recorded', $used, $base);
                    }
                }

                if ($base > 0 && $used >= ($base * self::SPIKE_MULTIPLIER) && ($used - $base) >= 3) {
                    $anomalies[] = $this->anomalyRow($reading, 'spike', 'critical', 'Sudden usage spike vs rolling average', $used, $base);
                }

                if ($base >= 5 && $used > 0 && $used <= ($base * self::DROP_RATIO)) {
                    $anomalies[] = $this->anomalyRow($reading, 'abnormal_drop', 'warning', 'Abnormal usage drop vs history', $used, $base);
                }

                if ($prior !== null && $used < $prior * 0.5 && ! $reading->is_meter_reset && $prior >= 5) {
                    $anomalies[] = $this->anomalyRow($reading, 'possible_tampering', 'critical', 'Possible meter tampering — sharp decline without reset', $used, $prior);
                }

                if ((float) $reading->current_reading < (float) $reading->previous_reading && ! $reading->is_meter_reset) {
                    $anomalies[] = $this->anomalyRow($reading, 'possible_tampering', 'critical', 'Current reading below previous without meter reset flag', $used, (float) $reading->previous_reading);
                }

                if ($reading->is_estimated) {
                    $consecutiveEstimated = 0;
                    for ($i = $index; $i >= 0; $i--) {
                        if ($sorted[$i]->is_estimated) {
                            $consecutiveEstimated++;
                        } else {
                            break;
                        }
                    }
                    if ($consecutiveEstimated >= 3) {
                        $anomalies[] = $this->anomalyRow($reading, 'estimated_abuse', 'warning', $consecutiveEstimated.' consecutive estimated readings', $used, $base);
                    }
                }

                if ($base > 0 && $used >= ($base * 2) && $used >= 20) {
                    $anomalies[] = $this->anomalyRow($reading, 'excessive_consumption', 'warning', 'Excessive consumption vs portfolio baseline', $used, $base);
                }
            }

            $recentHigh = $sorted->slice(-self::LEAK_CONSECUTIVE);
            if ($recentHigh->count() === self::LEAK_CONSECUTIVE && $avg > 0) {
                $allHigh = $recentHigh->every(fn (PmWaterReading $r) => (float) $r->units_used >= ($avg * self::LEAK_MULTIPLIER));
                if ($allHigh) {
                    $last = $recentHigh->last();
                    if ($last) {
                        $anomalies[] = $this->anomalyRow($last, 'possible_leak', 'critical', 'Sustained high usage — possible leak', (float) $last->units_used, $avg);
                    }
                }
            }
        }

        foreach ($this->missingReadingUnits($propertyId) as $missing) {
            $anomalies[] = [
                'reading_id' => null,
                'unit_id' => $missing['unit_id'],
                'property_id' => $missing['property_id'],
                'billing_month' => $missing['billing_month'],
                'property' => $missing['property_name'],
                'unit' => $missing['unit_label'],
                'type' => 'missing_reading',
                'severity' => 'warning',
                'label' => 'Missing reading',
                'message' => 'No water reading for billing month '.$missing['billing_month'],
                'units_used' => 0.0,
                'baseline' => 0.0,
                'trend' => 'flat',
            ];
        }

        usort($anomalies, fn ($a, $b) => [$b['severity'] <=> $a['severity'], $b['billing_month'] <=> $a['billing_month']]);

        return $anomalies;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingReadingUnits(?int $propertyId): array
    {
        $months = [now()->format('Y-m'), now()->subMonth()->format('Y-m')];
        $missing = [];

        $units = PropertyUnit::query()
            ->with('property:id,name')
            ->where('status', PropertyUnit::STATUS_OCCUPIED)
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->get(['id', 'property_id', 'label']);

        foreach ($months as $month) {
            $existing = PmWaterReading::query()
                ->where('billing_month', $month)
                ->pluck('property_unit_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            foreach ($units as $unit) {
                if ($existing->has((int) $unit->id)) {
                    continue;
                }
                $missing[] = [
                    'unit_id' => (int) $unit->id,
                    'property_id' => (int) $unit->property_id,
                    'property_name' => (string) ($unit->property?->name ?? '—'),
                    'unit_label' => (string) ($unit->label ?? '—'),
                    'billing_month' => $month,
                ];
            }
        }

        return $missing;
    }

    private function anomalyRow(
        PmWaterReading $reading,
        string $type,
        string $severity,
        string $message,
        float $used,
        float $baseline,
    ): array {
        $trend = 'flat';
        if ($baseline > 0) {
            $ratio = $used / $baseline;
            $trend = $ratio > 1.15 ? 'up' : ($ratio < 0.85 ? 'down' : 'flat');
        }

        return [
            'reading_id' => (int) $reading->id,
            'unit_id' => (int) $reading->property_unit_id,
            'property_id' => (int) ($reading->unit?->property_id ?? 0),
            'billing_month' => (string) $reading->billing_month,
            'property' => (string) ($reading->unit?->property?->name ?? '—'),
            'unit' => (string) ($reading->unit?->label ?? '—'),
            'type' => $type,
            'severity' => $severity,
            'label' => str_replace('_', ' ', ucfirst($type)),
            'message' => $message,
            'units_used' => round($used, 3),
            'baseline' => round($baseline, 3),
            'trend' => $trend,
            'is_estimated' => (bool) $reading->is_estimated,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     * @return list<array<string, mixed>>
     */
    private function buildAlerts(array $anomalies, ?int $propertyId): array
    {
        $alerts = [];
        $critical = array_filter($anomalies, fn ($a) => ($a['severity'] ?? '') === 'critical');
        $warnings = array_filter($anomalies, fn ($a) => ($a['severity'] ?? '') === 'warning');

        if (count($critical) > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'title' => count($critical).' critical utility signal(s)',
                'description' => 'Review spikes, tampering flags, and possible leaks immediately.',
                'count' => count($critical),
            ];
        }

        $missing = array_filter($anomalies, fn ($a) => ($a['type'] ?? '') === 'missing_reading');
        if (count($missing) > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => count($missing).' missing reading(s)',
                'description' => 'Occupied units without readings for recent billing months.',
                'count' => count($missing),
            ];
        }

        $estimated = array_filter($anomalies, fn ($a) => ($a['type'] ?? '') === 'estimated_abuse');
        if (count($estimated) > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Estimated reading pattern detected',
                'description' => count($estimated).' unit(s) with consecutive estimated readings.',
                'count' => count($estimated),
            ];
        }

        $estimatedPct = $this->estimatedReadingPercent($propertyId);
        if ($estimatedPct >= 30) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'High estimated reading rate',
                'description' => round($estimatedPct, 1).'% of recent readings are estimated.',
                'count' => (int) round($estimatedPct),
            ];
        }

        if ($alerts === [] && count($warnings) > 0) {
            $alerts[] = [
                'severity' => 'info',
                'title' => count($warnings).' utility watch item(s)',
                'description' => 'Minor anomalies detected — monitor trends on the analytics dashboard.',
                'count' => count($warnings),
            ];
        }

        return $alerts;
    }

    private function estimatedReadingPercent(?int $propertyId): float
    {
        $from = now()->subMonths(3)->format('Y-m');
        $query = PmWaterReading::query()
            ->when($propertyId, fn ($q) => $q->whereHas('unit', fn ($u) => $u->where('property_id', $propertyId)))
            ->where('billing_month', '>=', $from);

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            return 0.0;
        }

        $estimated = (int) (clone $query)->where('is_estimated', true)->count();

        return ($estimated / $total) * 100;
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return list<array<string, mixed>>
     */
    private function monthlyTrends(Collection $readings): array
    {
        return $readings
            ->groupBy('billing_month')
            ->sortKeys()
            ->map(fn (Collection $rows, string $month) => [
                'month' => $month,
                'total_units' => round((float) $rows->sum('units_used'), 3),
                'total_amount' => round((float) $rows->sum('amount'), 2),
                'readings' => $rows->count(),
                'avg_units' => round((float) $rows->avg('units_used'), 3),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return list<array<string, mixed>>
     */
    private function propertyTrends(Collection $readings): array
    {
        return $readings
            ->groupBy(fn (PmWaterReading $r) => (int) ($r->unit?->property_id ?? 0))
            ->map(function (Collection $rows, int $propertyId) {
                $name = (string) ($rows->first()?->unit?->property?->name ?? 'Property #'.$propertyId);

                return [
                    'property_id' => $propertyId,
                    'property' => $name,
                    'total_units' => round((float) $rows->sum('units_used'), 3),
                    'avg_units' => round((float) $rows->avg('units_used'), 3),
                    'readings' => $rows->count(),
                    'total_amount' => round((float) $rows->sum('amount'), 2),
                ];
            })
            ->sortByDesc('total_units')
            ->values()
            ->take(15)
            ->all();
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return list<array<string, mixed>>
     */
    private function unitTypeTrends(Collection $readings): array
    {
        return $readings
            ->groupBy(fn (PmWaterReading $r) => (string) ($r->unit?->unit_type ?? 'unknown'))
            ->map(function (Collection $rows, string $type) {
                $label = $rows->first()?->unit?->unitTypeLabel() ?? ucfirst($type);

                return [
                    'unit_type' => $type,
                    'label' => $label,
                    'total_units' => round((float) $rows->sum('units_used'), 3),
                    'avg_units' => round((float) $rows->avg('units_used'), 3),
                    'readings' => $rows->count(),
                ];
            })
            ->sortByDesc('total_units')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return list<array<string, mixed>>
     */
    private function tenantTrends(Collection $readings): array
    {
        $unitIds = $readings->pluck('property_unit_id')->unique()->map(fn ($id) => (int) $id)->all();
        $tenantByUnit = $this->activeTenantNamesByUnit($unitIds);

        return $readings
            ->groupBy(fn (PmWaterReading $r) => $tenantByUnit[(int) $r->property_unit_id] ?? 'Unassigned')
            ->map(function (Collection $rows, string $tenantName) {
                return [
                    'tenant' => $tenantName,
                    'total_units' => round((float) $rows->sum('units_used'), 3),
                    'avg_units' => round((float) $rows->avg('units_used'), 3),
                    'readings' => $rows->count(),
                ];
            })
            ->sortByDesc('total_units')
            ->values()
            ->take(12)
            ->all();
    }

    /**
     * @param  array<int>  $unitIds
     * @return array<int, string>
     */
    private function activeTenantNamesByUnit(array $unitIds): array
    {
        if ($unitIds === []) {
            return [];
        }

        return DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->join('pm_tenants as t', 't.id', '=', 'l.pm_tenant_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->whereIn('lu.property_unit_id', $unitIds)
            ->pluck('t.name', 'lu.property_unit_id')
            ->mapWithKeys(fn ($name, $unitId) => [(int) $unitId => (string) $name])
            ->all();
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return array{labels: list<string>, values: list<float>}
     */
    private function seasonalUsage(Collection $readings): array
    {
        $buckets = array_fill(1, 12, ['sum' => 0.0, 'count' => 0]);
        foreach ($readings as $reading) {
            $monthNum = (int) substr((string) $reading->billing_month, 5, 2);
            if ($monthNum < 1 || $monthNum > 12) {
                continue;
            }
            $buckets[$monthNum]['sum'] += (float) $reading->units_used;
            $buckets[$monthNum]['count']++;
        }

        $labels = [];
        $values = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = date('M', mktime(0, 0, 0, $m, 1));
            $count = $buckets[$m]['count'];
            $values[] = $count > 0 ? round($buckets[$m]['sum'] / $count, 3) : 0.0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @return array{months: list<string>, properties: list<string>, matrix: list<list<float>>}
     */
    private function consumptionHeatmap(Collection $readings, int $months): array
    {
        $monthKeys = $readings->pluck('billing_month')->unique()->sort()->values()->all();
        if (count($monthKeys) > $months) {
            $monthKeys = array_slice($monthKeys, -$months);
        }

        $propertyGroups = $readings
            ->groupBy(fn (PmWaterReading $r) => (int) ($r->unit?->property_id ?? 0))
            ->map(fn (Collection $rows, int $propertyId) => [
                'id' => $propertyId,
                'name' => (string) ($rows->first()?->unit?->property?->name ?? 'Unknown'),
            ])
            ->sortBy('name')
            ->take(12);

        $matrix = [];
        $propertyNames = [];
        foreach ($propertyGroups as $propertyId => $meta) {
            $propertyNames[] = $meta['name'];
            $row = [];
            foreach ($monthKeys as $month) {
                $sum = (float) $readings
                    ->filter(fn (PmWaterReading $r) => (int) ($r->unit?->property_id ?? 0) === (int) $propertyId && $r->billing_month === $month)
                    ->sum('units_used');
                $row[] = round($sum, 3);
            }
            $matrix[] = $row;
        }

        return [
            'months' => array_values($monthKeys),
            'properties' => $propertyNames,
            'matrix' => $matrix,
        ];
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @param  Collection<int, Collection<int, PmWaterReading>>  $histories
     * @return array<string, mixed>
     */
    private function efficiencyKpis(Collection $readings, Collection $histories, ?int $propertyId): array
    {
        $totalUnits = round((float) $readings->sum('units_used'), 3);
        $readingCount = max(1, $readings->count());
        $avgUsage = round($totalUnits / $readingCount, 3);

        $bedroomQuery = PropertyUnit::query()
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId));
        $totalBedrooms = max(1, (int) (clone $bedroomQuery)->sum('bedrooms'));
        $occupiedUnits = max(1, (int) (clone $bedroomQuery)->where('status', PropertyUnit::STATUS_OCCUPIED)->count());

        $usagePerBedroom = round($totalUnits / $totalBedrooms, 3);
        $occupancyAdjusted = round($totalUnits / $occupiedUnits, 3);

        $latestMonth = $readings->max('billing_month') ?? now()->format('Y-m');
        $latestTotal = (float) $readings->where('billing_month', $latestMonth)->sum('units_used');
        $priorMonth = now()->createFromFormat('Y-m', $latestMonth)->subMonth()->format('Y-m');
        $priorTotal = (float) $readings->where('billing_month', $priorMonth)->sum('units_used');
        $momChange = $priorTotal > 0 ? round((($latestTotal - $priorTotal) / $priorTotal) * 100, 1) : 0.0;

        return [
            'average_usage' => $avgUsage,
            'usage_per_bedroom' => $usagePerBedroom,
            'occupancy_adjusted_usage' => $occupancyAdjusted,
            'monitored_units' => $histories->count(),
            'total_readings' => $readings->count(),
            'mom_change_pct' => $momChange,
            'latest_month' => $latestMonth,
        ];
    }

    /**
     * @param  Collection<int, PmWaterReading>  $readings
     * @param  array<string, mixed>  $kpis
     * @return list<array<string, mixed>>
     */
    private function propertyPerformance(Collection $readings, array $kpis): array
    {
        return $this->propertyPerformanceScores($readings);
    }

    /**
     * @param  list<array<string, mixed>>  $monthlyTrends
     * @param  list<array<string, mixed>>  $propertyTrends
     * @param  list<array<string, mixed>>  $anomalies
     * @return array<string, mixed>
     */
    private function comparisonWidgets(array $monthlyTrends, array $propertyTrends, array $anomalies): array
    {
        $latest = $monthlyTrends !== [] ? $monthlyTrends[array_key_last($monthlyTrends)] : null;
        $previous = count($monthlyTrends) > 1 ? $monthlyTrends[array_key_last($monthlyTrends) - 1] : null;

        $topProperty = $propertyTrends[0] ?? null;
        $anomalyCounts = collect($anomalies)->countBy('type')->sortDesc();

        return [
            'latest_month' => $latest,
            'previous_month' => $previous,
            'top_property' => $topProperty,
            'anomaly_counts' => $anomalyCounts->all(),
            'total_anomalies' => count($anomalies),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $anomalies
     * @return array<int, list<array<string, mixed>>>
     */
    private function indexAnomaliesByReading(array $anomalies): array
    {
        $indexed = [];
        foreach ($anomalies as $anomaly) {
            $readingId = $anomaly['reading_id'] ?? null;
            if ($readingId === null) {
                continue;
            }
            $indexed[(int) $readingId][] = $anomaly;
        }

        return $indexed;
    }

    /**
     * Enriched property performance with score vs portfolio.
     *
     * @param  Collection<int, PmWaterReading>  $readings
     * @return list<array<string, mixed>>
     */
    public function propertyPerformanceScores(Collection $readings): array
    {
        $portfolioAvg = max(0.001, (float) $readings->avg('units_used'));

        return collect($this->propertyTrends($readings))
            ->map(function (array $row) use ($portfolioAvg) {
                $ratio = ($row['avg_units'] ?? 0) / $portfolioAvg;
                $score = round(max(0, min(100, 100 - (abs(1 - $ratio) * 40))), 1);
                $row['performance_score'] = $score;
                $row['vs_portfolio'] = $ratio > 1.1 ? 'above' : ($ratio < 0.9 ? 'below' : 'inline');

                return $row;
            })
            ->all();
    }
}
