@php
    use App\Support\Property\ResponsiveTableColumns;
    use Illuminate\Support\HtmlString;

    $isMonthScoped = (bool) ($isMonthScoped ?? false);
    $monthlyBreakdown = collect($monthlyBreakdown ?? []);
    $monthSettlements = collect($monthSettlements ?? []);
    $fy = (int) ($fyValue ?? now()->year);

    $breakdownColumns = ['Property', 'Ownership %', 'Owner share', 'Pending share', 'Agent earning', 'Last collection'];
    $breakdownRows = [];
    foreach ($propertyBreakdown as $row) {
        $breakdownRows[] = [
            (string) ($row['property_name'] ?? ''),
            number_format((float) ($row['ownership_percent'] ?? 0), 2).'%',
            \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)),
            \App\Services\Property\PropertyMoney::kes((float) ($row['pending_share'] ?? 0)),
            \App\Services\Property\PropertyMoney::kes((float) ($row['agent_earning'] ?? 0)),
            ! empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—',
        ];
    }

    $monthlyColumns = ['Month', 'Gross collected', 'Owner share', 'Your earnings', 'Active properties', 'Actions'];
    $monthlyRows = [];
    foreach ($monthlyBreakdown as $row) {
        $ym = (string) ($row['month'] ?? '');
        $isCurrentMonth = $isMonthScoped && $ym === (string) ($monthValue ?? '');
        $monthUrl = route('property.landlords.show', [
            'landlord' => $landlord->id,
            'tab' => 'statement',
            'month' => $ym,
            'fy' => $fy,
        ], false);
        $exportUrl = route('property.landlords.show', [
            'landlord' => $landlord->id,
            'tab' => 'statement',
            'month' => $ym,
            'fy' => $fy,
            'export' => 'csv',
            'export_scope' => 'statement',
        ], false);
        $printUrl = route('property.landlords.statement.print', [
            'landlord' => $landlord->id,
            'month' => $ym,
            'fy' => $fy,
            'print' => 1,
        ], false);

        $actions = new HtmlString(
            '<div class="flex flex-wrap gap-2 text-xs font-semibold">'
            .'<a href="'.e($monthUrl).'" data-turbo-frame="property-main" class="text-indigo-700 hover:underline">'.($isCurrentMonth ? 'Viewing' : 'Open month').'</a>'
            .'<a href="'.e($exportUrl).'" data-turbo="false" class="text-slate-700 hover:underline">Export statement</a>'
            .'<a href="'.e($printUrl).'" target="_blank" rel="noopener" data-turbo="false" class="text-teal-700 hover:underline">Print statement</a>'
            .'</div>'
        );

        $monthlyRows[] = [
            ($isCurrentMonth ? '▸ ' : '').(string) ($row['month_label'] ?? $ym),
            \App\Services\Property\PropertyMoney::kes((float) ($row['gross_collected'] ?? 0)),
            \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)),
            \App\Services\Property\PropertyMoney::kes((float) ($row['agent_earning'] ?? 0)),
            (string) (int) ($row['active_properties'] ?? 0),
            $actions,
        ];
    }
@endphp

<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm w-full min-w-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white break-words">{{ $landlord->name }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300 break-all">{{ $landlord->email ?: ($landlord->phone ?: '—') }}</p>
            @if ($isMonthScoped)
                <p class="mt-1 text-xs text-teal-800 dark:text-teal-200">Showing property account statement detail for <strong>{{ $periodLabel }}</strong> (units, balances, invoiced, received, additions &amp; deductions).</p>
            @else
                <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">FY overview below. Open a month (or use Export / Print on a month row) for the full unit-level statement like the legacy register.</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if (! $isMonthScoped)
                <a
                    href="{{ route('property.landlords.show', array_merge(['landlord' => $landlord->id, 'tab' => 'statement', 'fy' => $fy], ['export' => 'csv', 'export_scope' => 'monthly']), false) }}"
                    data-turbo="false"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >Export months CSV</a>
            @else
                <a
                    href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'statement', 'month' => $monthValue, 'fy' => $fy, 'export' => 'csv', 'export_scope' => 'statement'], false) }}"
                    data-turbo="false"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >Export month CSV</a>
                <a
                    href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'tab' => 'statement', 'month' => $monthValue, 'fy' => $fy, 'export' => 'pdf', 'export_scope' => 'statement'], false) }}"
                    data-turbo="false"
                    class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >Export month PDF</a>
            @endif
            <a
                href="{{ route('property.landlords.statement.print', array_filter(['landlord' => $landlord->id, 'month' => $monthValue ?? null, 'fy' => $fyValue ?? null, 'print' => 1]), false) }}"
                target="_blank"
                rel="noopener"
                data-turbo="false"
                class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"
            >{{ $isMonthScoped ? 'Print month statement' : 'Print FY overview' }}</a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3 mt-4">
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Properties</p>
            <p class="text-base font-semibold tabular-nums">{{ $totals['properties'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Ownership %</p>
            <p class="text-base font-semibold tabular-nums">{{ number_format((float) ($totals['ownership_sum'] ?? 0), 2) }}%</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Owner share</p>
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Pending share</p>
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3 col-span-2 sm:col-span-1">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Your earnings</p>
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)) }}</p>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-500">Period: {{ $periodLabel }} · Generated {{ now()->format('Y-m-d H:i') }}</p>
</div>

@if ($isMonthScoped && $monthSettlements->isNotEmpty())
    @foreach ($monthSettlements as $settlement)
        @php
            $unitColumns = ['Unit', 'Tenant', 'Per month', 'B/F rent', 'B/F garbage', 'B/F water', 'Inv. rent', 'Inv. garbage', 'Inv. water', 'Rec. rent', 'Rec. garbage', 'Rec. water'];
            $unitRows = [];
            foreach (($settlement['unit_lines'] ?? []) as $line) {
                $unitRows[] = [
                    (string) ($line['unit_label'] ?? '—'),
                    (string) ($line['tenant_name'] ?? '—'),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['rent_per_month'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['rent_bf'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_bf'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['water_bf'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['rent_billed'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_billed'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['water_billed'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['rent_received'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_received'] ?? 0)),
                    \App\Services\Property\PropertyMoney::kes((float) ($line['water_received'] ?? 0)),
                ];
            }
            $unitStats = $settlement['unit_stats'] ?? [];
        @endphp

        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-teal-200 dark:border-teal-800 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm w-full min-w-0 mt-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $settlement['property_name'] ?? 'Property' }}</h3>
                    <p class="text-xs text-slate-500">
                        {{ $settlement['period_label'] ?? $periodLabel }}
                        @if (! empty($settlement['period_range_label']))
                            ({{ $settlement['period_range_label'] }})
                        @endif
                        · Occupied {{ (int) ($unitStats['units_occupied'] ?? 0) }} · Vacant {{ (int) ($unitStats['units_vacant'] ?? 0) }}
                    </p>
                </div>
                <p class="text-sm font-semibold text-teal-800 dark:text-teal-200 tabular-nums">
                    Net due {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['net_amount_due'] ?? 0)) }}
                </p>
            </div>
        </div>

        @include('property.agent.landlords.partials.responsive-table-section', [
            'title' => 'Units — '.$settlement['property_name'],
            'columns' => $unitColumns,
            'rows' => $unitRows,
            'columnConfig' => ResponsiveTableColumns::landlordStatementUnits(),
            'emptyTitle' => 'No units',
            'emptyHint' => 'No units linked to this property.',
            'tableMinWidth' => '980px',
        ])

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">Additions</h4>
                <ul class="space-y-1 text-sm">
                    @forelse (($settlement['additions'] ?? []) as $addition)
                        <li class="flex justify-between gap-3">
                            <span class="text-slate-600 dark:text-slate-300">{{ $addition['description'] ?? 'Addition' }}</span>
                            <span class="tabular-nums text-emerald-700 font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($addition['amount'] ?? 0)) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No additions in this month.</li>
                    @endforelse
                    <li class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 font-semibold">
                        <span>Total additions</span>
                        <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['additions_total'] ?? 0)) }}</span>
                    </li>
                </ul>
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-2 mt-4">Deductions / disbursements</h4>
                <ul class="space-y-1 text-sm">
                    @forelse (($settlement['deductions'] ?? []) as $deduction)
                        <li class="flex justify-between gap-3">
                            <span class="text-slate-600 dark:text-slate-300">{{ $deduction['description'] ?? 'Deduction' }}</span>
                            <span class="tabular-nums text-rose-700 font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($deduction['amount'] ?? 0)) }}</span>
                        </li>
                    @empty
                        <li class="text-slate-500">No deductions in this month.</li>
                    @endforelse
                    <li class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 font-semibold">
                        <span>Total deductions</span>
                        <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['deductions_total'] ?? 0)) }}</span>
                    </li>
                </ul>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-2">Statement summary</h4>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Rent received</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['rent_received'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Total utility</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['utility_received'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Less management fee ({{ rtrim(rtrim(number_format((float) ($settlement['commission_percent'] ?? 0), 2, '.', ''), '0'), '.') }}%)</dt><dd class="tabular-nums font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['management_fee'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Add additions</dt><dd class="tabular-nums font-medium text-emerald-700">+ {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['additions_total'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Less deductions</dt><dd class="tabular-nums font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['deductions_total'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Balance B/F</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['balance_brought_forward'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-1"><dt class="font-semibold">Net amount due</dt><dd class="tabular-nums font-semibold text-teal-800 dark:text-teal-200">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['net_amount_due'] ?? 0)) }}</dd></div>
                </dl>
            </div>
        </div>
    @endforeach
@endif

@include('property.agent.landlords.partials.responsive-table-section', [
    'title' => 'Month-by-month (FY '.$fy.')',
    'columns' => $monthlyColumns,
    'rows' => $monthlyRows,
    'columnConfig' => ResponsiveTableColumns::landlordStatementMonthly(),
    'emptyTitle' => 'No monthly activity',
    'emptyHint' => 'No completed collections in this period yet.',
    'tableMinWidth' => '760px',
])

@if (! $isMonthScoped)
    @include('property.agent.landlords.partials.responsive-table-section', [
        'title' => 'Property breakdown (full period)',
        'columns' => $breakdownColumns,
        'rows' => $breakdownRows,
        'columnConfig' => ResponsiveTableColumns::landlordStatementBreakdown(),
        'emptyTitle' => 'No linked properties',
        'emptyHint' => 'Link this landlord to a property to see breakdown rows.',
        'tableMinWidth' => '720px',
    ])
@endif
