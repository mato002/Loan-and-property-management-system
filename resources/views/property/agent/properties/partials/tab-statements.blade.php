@php
    $packet = $propertyStatement ?? [];
    $fy = (int) ($packet['fy'] ?? ($fyValue ?? now()->year));
    $openMonth = (string) ($packet['open_month'] ?? '');
    $yearSettlement = $packet['year_settlement'] ?? null;
    $monthSettlement = $packet['month_settlement'] ?? null;
    $monthly = collect($packet['monthly'] ?? []);
    $unitMonths = collect($packet['unit_months'] ?? []);
    $monthKeys = $packet['month_keys'] ?? [];
    $kes = static fn (float $n): string => \App\Services\Property\PropertyMoney::kes($n);
    $yearTotals = is_array($yearSettlement) ? ($yearSettlement['unit_totals'] ?? []) : [];
    $baseQuery = array_filter([
        'property' => $property->id,
        'tab' => 'statements',
        'fy' => $fy,
    ]);
    $printYearUrl = route('property.properties.show', $baseQuery + ['print_scope' => 'year'], false);
    $printMonthUrl = $openMonth !== ''
        ? route('property.properties.show', $baseQuery + ['month' => $openMonth, 'print_scope' => 'month'], false)
        : null;
    $exportClass = 'rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50';
    $exportFor = function (string $scope) use ($openMonth, $exportClass) {
        return [
            'current' => true,
            'query' => array_filter([
                'tab' => 'statements',
                'month' => $openMonth !== '' ? $openMonth : null,
                'export_scope' => $scope,
            ]),
            'formats' => \App\Support\TableExportLinks::STANDARD_FORMATS,
            'class' => $exportClass,
        ];
    };
@endphp

<div class="mt-5 space-y-5">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Property statement — {{ $property->name }}</h3>
                <p class="mt-1 text-xs text-slate-500">FY {{ $fy }} for this property only. Yearly unit totals sit below; use + on a month to open the unit matrix (B/F, invoiced, received).</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @include('property.agent.partials.table_export_dropdown', $exportFor('year_units'))
                <a href="{{ $printYearUrl }}" target="_blank" rel="noopener" data-turbo="false" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print year</a>
                @if ($printMonthUrl)
                    @include('property.agent.partials.table_export_dropdown', $exportFor('month_units'))
                    <a href="{{ $printMonthUrl }}" target="_blank" rel="noopener" data-turbo="false" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">Print {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $openMonth)->format('M Y') }}</a>
                @endif
            </div>
        </div>
        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                <p class="text-[11px] uppercase tracking-wide text-slate-500">Invoiced (FY)</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900">{{ $kes((float) ($yearTotals['total_billed'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-teal-100 bg-teal-50 p-3">
                <p class="text-[11px] uppercase tracking-wide text-teal-700">Received (FY)</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-teal-900">{{ $kes((float) ($yearTotals['total_received'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-3">
                <p class="text-[11px] uppercase tracking-wide text-amber-700">B/F at 1 Jan</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-amber-900">{{ $kes((float) (($yearTotals['rent_bf'] ?? 0) + ($yearTotals['garbage_bf'] ?? 0) + ($yearTotals['water_bf'] ?? 0))) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-3">
                <p class="text-[11px] uppercase tracking-wide text-indigo-700">Closing</p>
                <p class="mt-1 text-lg font-semibold tabular-nums text-indigo-900">{{ $kes((float) (($yearTotals['rent_closing'] ?? 0) + ($yearTotals['garbage_closing'] ?? 0) + ($yearTotals['water_closing'] ?? 0))) }}</p>
            </div>
        </div>
    </div>

    @if (is_array($yearSettlement))
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Yearly unit statement</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Each unit on its own row for FY {{ $fy }} — opening B/F, year invoiced, year received.</p>
                </div>
                @include('property.agent.partials.table_export_dropdown', $exportFor('year_units'))
            </div>
            <div class="mt-3">
                @include('property.agent.landlords.partials.month-statement-detail', ['settlement' => $yearSettlement])
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Month-by-month (FY {{ $fy }})</h3>
            @include('property.agent.partials.table_export_dropdown', $exportFor('months'))
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2 text-right">Invoiced</th>
                        <th class="px-3 py-2 text-right">Received</th>
                        <th class="px-3 py-2">Units</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($monthly as $row)
                        @php
                            $ym = (string) ($row['month'] ?? '');
                            $isOpen = $openMonth !== '' && $ym === $openMonth;
                            $toggleUrl = $isOpen
                                ? route('property.properties.show', $baseQuery, false)
                                : route('property.properties.show', $baseQuery + ['month' => $ym], false);
                        @endphp
                        <tr @class([
                            'border-t border-slate-100',
                            'bg-teal-50/70' => $isOpen,
                            'hover:bg-slate-50/70' => ! $isOpen,
                        ])>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a href="{{ $toggleUrl }}" data-turbo-frame="property-main" class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-300 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50" aria-expanded="{{ $isOpen ? 'true' : 'false' }}">{{ $isOpen ? '−' : '+' }}</a>
                                    <span @class(['font-semibold text-teal-900' => $isOpen])>{{ $row['month_label'] ?? $ym }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kes((float) ($row['billed'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $kes((float) ($row['received'] ?? 0)) }}</td>
                            <td class="px-3 py-2 text-xs">
                                <a href="{{ $toggleUrl }}" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">{{ $isOpen ? 'Hide units' : 'Unit breakdown' }}</a>
                            </td>
                        </tr>
                        @if ($isOpen)
                            <tr class="bg-slate-50/80">
                                <td colspan="4" class="px-3 py-4">
                                    @if (is_array($monthSettlement))
                                        @include('property.agent.landlords.partials.month-statement-detail', ['settlement' => $monthSettlement])
                                    @else
                                        <p class="text-sm text-slate-500">No unit statement for this month.</p>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No monthly activity in this year.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Unit monthly grid (FY {{ $fy }})</h3>
                <p class="text-xs text-slate-500 mt-0.5">Each unit across the year. Top figure is invoiced, bottom is received.</p>
            </div>
            @include('property.agent.partials.table_export_dropdown', $exportFor('unit_grid'))
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse text-[11px] [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-2 py-2 sticky left-0 bg-slate-50 z-10">Unit</th>
                        <th class="px-2 py-2">Tenant</th>
                        @foreach ($monthKeys as $ym)
                            <th class="px-2 py-2 text-right whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $ym)->format('M') }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-right bg-slate-100">Year</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unitMonths as $unit)
                        <tr class="border-t border-slate-100">
                            <td class="px-2 py-1.5 font-medium text-slate-900 sticky left-0 bg-white z-10 whitespace-nowrap">{{ $unit['unit_label'] ?? '—' }}</td>
                            <td class="px-2 py-1.5 text-slate-600 whitespace-nowrap max-w-[10rem] truncate">{{ $unit['tenant_name'] ?? '—' }}</td>
                            @foreach ($monthKeys as $ym)
                                @php
                                    $cell = $unit['months'][$ym] ?? ['billed' => 0, 'received' => 0];
                                    $billed = (float) ($cell['billed'] ?? 0);
                                    $received = (float) ($cell['received'] ?? 0);
                                @endphp
                                <td class="px-2 py-1 text-right tabular-nums">
                                    @if ($billed <= 0.009 && $received <= 0.009)
                                        <span class="text-slate-300">—</span>
                                    @else
                                        <div>{{ $billed > 0.009 ? number_format($billed, 0) : '—' }}</div>
                                        <div class="text-teal-700">{{ $received > 0.009 ? number_format($received, 0) : '—' }}</div>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-2 py-1 text-right tabular-nums bg-slate-50 font-semibold">
                                <div>{{ number_format((float) ($unit['year_billed'] ?? 0), 0) }}</div>
                                <div class="text-teal-800">{{ number_format((float) ($unit['year_received'] ?? 0), 0) }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 3 + count($monthKeys) }}" class="px-4 py-8 text-center text-slate-500">No units on this property.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
