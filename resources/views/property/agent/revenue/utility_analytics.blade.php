@php
    $chartPayload = json_encode([
        'monthly_trends' => $data['monthly_trends'] ?? [],
        'seasonal' => $data['seasonal'] ?? [],
        'property_trends' => $data['property_trends'] ?? [],
        'unit_type_trends' => $data['unit_type_trends'] ?? [],
        'comparison' => $data['comparison'] ?? [],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $heatmap = $data['heatmap'] ?? ['months' => [], 'properties' => [], 'matrix' => []];
    $heatmapMax = 0.0;
    foreach ($heatmap['matrix'] ?? [] as $row) {
        foreach ($row as $cell) {
            $heatmapMax = max($heatmapMax, (float) $cell);
        }
    }
    $heatmapMax = max($heatmapMax, 0.001);
@endphp

<x-property.workspace
    title="Utility intelligence"
    subtitle="Usage analytics, anomaly detection, efficiency KPIs, and risk alerts."
    back-route="property.revenue.utilities.reconciliation"
    :stats="$stats"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities', absolute: false) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Utilities hub</a>
        <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Ledgers</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.utilities.analytics', absolute: false) }}" class="flex flex-wrap items-end gap-3" data-turbo-frame="property-main">
            <div>
                <label class="text-xs text-slate-600">Property</label>
                <select name="property" class="mt-1 rounded-lg border-slate-300 text-sm min-w-[160px]">
                    <option value="">All properties</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" @selected(($filters['property'] ?? '') == (string) $property->id)>{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-600">Trend window</label>
                <select name="months" class="mt-1 rounded-lg border-slate-300 text-sm">
                    @foreach ([6, 12, 18, 24] as $m)
                        <option value="{{ $m }}" @selected(($filters['months'] ?? '12') == (string) $m)>{{ $m }} months</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-800">Apply</button>
        </form>
    </x-slot>

    <div id="utility-analytics-charts" class="hidden" data-payload='{!! $chartPayload !!}'></div>

    @if (! empty($data['alerts']))
        <div class="mb-6 space-y-2">
            @foreach ($data['alerts'] as $alert)
                <div class="rounded-xl border px-4 py-3 flex items-start gap-3
                    @if(($alert['severity'] ?? '') === 'critical') border-red-200 bg-red-50
                    @elseif(($alert['severity'] ?? '') === 'warning') border-amber-200 bg-amber-50
                    @else border-sky-200 bg-sky-50 @endif">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5
                        @if(($alert['severity'] ?? '') === 'critical') text-red-600
                        @elseif(($alert['severity'] ?? '') === 'warning') text-amber-600
                        @else text-sky-600 @endif" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $alert['title'] ?? 'Alert' }}</p>
                        <p class="text-xs text-slate-600 mt-0.5">{{ $alert['description'] ?? '' }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <x-property.responsive.kpi-card-grid :kpis="$kpiCards" class="mb-6" />

    <div class="mb-6 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Monthly consumption trend</h3>
            <div class="h-56 w-full"><canvas id="utility-chart-monthly" aria-label="Monthly utility trend"></canvas></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Seasonal usage pattern</h3>
            <div class="h-56 w-full"><canvas id="utility-chart-seasonal" aria-label="Seasonal usage chart"></canvas></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Property comparison</h3>
            <div class="h-56 w-full"><canvas id="utility-chart-properties" aria-label="Property usage comparison"></canvas></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Usage by unit type</h3>
            <div class="h-56 w-full"><canvas id="utility-chart-unit-types" aria-label="Unit type breakdown"></canvas></div>
        </div>
    </div>

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">Consumption heatmap (property × month)</h3>
        @if (count($heatmap['properties'] ?? []) === 0)
            <p class="text-sm text-slate-500 py-6 text-center">Not enough data for heatmap.</p>
        @else
            <div class="overflow-x-auto">
                <table class="text-xs min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left py-2 pr-3 text-slate-500 font-semibold">Property</th>
                            @foreach ($heatmap['months'] as $month)
                                <th class="px-1 py-2 text-slate-500 font-medium whitespace-nowrap">{{ $month }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap['properties'] as $pIndex => $propertyName)
                            <tr>
                                <td class="py-1 pr-3 font-medium text-slate-800 whitespace-nowrap">{{ $propertyName }}</td>
                                @foreach ($heatmap['matrix'][$pIndex] ?? [] as $cell)
                                    @php
                                        $intensity = min(1, (float) $cell / $heatmapMax);
                                        $alpha = 0.12 + ($intensity * 0.78);
                                        $bg = "rgba(13, 148, 136, {$alpha})";
                                    @endphp
                                    <td class="p-0.5">
                                        <div class="rounded px-1.5 py-2 text-center tabular-nums font-medium" style="background-color: {{ $bg }}" title="{{ number_format((float) $cell, 2) }} m³">
                                            {{ (float) $cell > 0 ? number_format((float) $cell, 1) : '—' }}
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Property utility performance</h3>
            </div>
            <x-property.responsive.table-wrapper minWidth="640">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Property</th>
                            <th class="px-4 py-3 text-right">Avg m³</th>
                            <th class="px-4 py-3 text-right">Score</th>
                            <th class="px-4 py-3">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['property_performance'] ?? [] as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-3">{{ $row['property'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $row['avg_units'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $row['performance_score'] ?? '—' }}</td>
                                <td class="px-4 py-3">@include('property.agent.partials.utility_trend_indicator', ['trend' => $row['vs_portfolio'] ?? 'inline'])</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No property data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-property.responsive.table-wrapper>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Top tenant consumption</h3>
            </div>
            <x-property.responsive.table-wrapper minWidth="480">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Tenant</th>
                            <th class="px-4 py-3 text-right">Total m³</th>
                            <th class="px-4 py-3 text-right">Avg</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['tenant_trends'] ?? [] as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-3">{{ $row['tenant'] }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $row['total_units'], 2) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $row['avg_units'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-500">No tenant trends yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-property.responsive.table-wrapper>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-1 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">Anomaly breakdown</h3>
            <div class="h-48 w-full"><canvas id="utility-chart-anomalies" aria-label="Anomaly types chart"></canvas></div>
        </div>
        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">Detected anomalies & risk signals</h3>
                <span class="text-xs text-slate-500">{{ count($data['anomalies'] ?? []) }} total</span>
            </div>
            <x-property.responsive.table-wrapper minWidth="900">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Month</th>
                            <th class="px-4 py-3">Property / Unit</th>
                            <th class="px-4 py-3">Signal</th>
                            <th class="px-4 py-3 text-right">Used</th>
                            <th class="px-4 py-3">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($data['anomalies'] ?? [], 0, 40) as $anomaly)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $anomaly['billing_month'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $anomaly['property'] ?? '—' }} / {{ $anomaly['unit'] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @include('property.agent.partials.utility_anomaly_badge', ['anomaly' => $anomaly])
                                    <p class="text-xs text-slate-500 mt-1">{{ $anomaly['message'] ?? '' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) ($anomaly['units_used'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3">@include('property.agent.partials.utility_trend_indicator', ['trend' => $anomaly['trend'] ?? 'flat'])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-emerald-700">No anomalies detected in this window.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-property.responsive.table-wrapper>
        </div>
    </div>
</x-property.workspace>
