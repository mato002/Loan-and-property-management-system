@php
    use App\Support\Property\ResponsiveTableColumns;

    $isMonthScoped = (bool) ($isMonthScoped ?? false);
    $monthlyBreakdown = collect($monthlyBreakdown ?? []);
    $monthSettlements = collect($monthSettlements ?? []);
    $fy = (int) ($fyValue ?? now()->year);
    $openMonth = $isMonthScoped ? (string) ($monthValue ?? '') : '';

    $fyOverviewUrl = route('property.landlords.show', [
        'landlord' => $landlord->id,
        'tab' => 'statement',
        'fy' => $fy,
    ], false);

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
@endphp

<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm w-full min-w-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white break-words">{{ $landlord->name }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300 break-all">{{ $landlord->email ?: ($landlord->phone ?: '—') }}</p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Use <strong>+</strong> on a month row to expand the unit-level statement under that row. Export / Print stay available for that month.</p>
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

<div class="property-erp-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 overflow-visible mt-4">
    <div class="px-3 sm:px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Month-by-month (FY {{ $fy }})</h3>
    </div>

    <div class="w-full min-w-0">
        <x-property.responsive.table-wrapper min-width="760px">
            <table class="property-erp-table w-full table-auto border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_th]:dark:border-slate-700 [&_td]:border [&_td]:border-slate-200 [&_td]:dark:border-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Month</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Gross collected</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Owner share</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Your earnings</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Active properties</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($monthlyBreakdown as $row)
                        @php
                            $ym = (string) ($row['month'] ?? '');
                            $isOpen = $openMonth !== '' && $ym === $openMonth;
                            $expandUrl = route('property.landlords.show', [
                                'landlord' => $landlord->id,
                                'tab' => 'statement',
                                'month' => $ym,
                                'fy' => $fy,
                            ], false);
                            $collapseUrl = $fyOverviewUrl;
                            $toggleUrl = $isOpen ? $collapseUrl : $expandUrl;
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
                        @endphp
                        <tr @class([
                            'border-t border-slate-100 dark:border-slate-700/80',
                            'bg-teal-50/70 dark:bg-teal-900/20' => $isOpen,
                            'hover:bg-slate-50/80 dark:hover:bg-slate-800/40' => ! $isOpen,
                        ])>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 align-middle">
                                <div class="flex items-center gap-2 min-w-0">
                                    <a
                                        href="{{ $toggleUrl }}"
                                        data-turbo-frame="property-main"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                        aria-label="{{ $isOpen ? 'Collapse '.$row['month_label'] : 'Expand '.$row['month_label'] }}"
                                        title="{{ $isOpen ? 'Collapse month detail' : 'Expand month detail' }}"
                                    >{{ $isOpen ? '−' : '+' }}</a>
                                    <span @class(['font-semibold text-teal-900 dark:text-teal-100' => $isOpen])>{{ $row['month_label'] ?? $ym }}</span>
                                </div>
                            </td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['gross_collected'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['agent_earning'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ (int) ($row['active_properties'] ?? 0) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 align-top">
                                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                                    <a href="{{ $exportUrl }}" data-turbo="false" class="text-slate-700 hover:underline">Export statement</a>
                                    <a href="{{ $printUrl }}" target="_blank" rel="noopener" data-turbo="false" class="text-teal-700 hover:underline">Print statement</a>
                                </div>
                            </td>
                        </tr>
                        @if ($isOpen)
                            <tr class="border-t border-teal-200 dark:border-teal-800 bg-slate-50/80 dark:bg-slate-900/40">
                                <td colspan="6" class="px-3 sm:px-4 py-4 align-top">
                                    @if ($monthSettlements->isEmpty())
                                        <p class="text-sm text-slate-500">No property statement detail for this month.</p>
                                    @else
                                        <div class="space-y-6">
                                            @foreach ($monthSettlements as $settlement)
                                                @include('property.agent.landlords.partials.month-statement-detail', [
                                                    'settlement' => $settlement,
                                                ])
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
                                <p class="font-medium text-slate-700 dark:text-slate-200">No monthly activity</p>
                                <p class="text-sm mt-1">No completed collections in this period yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>
    </div>
</div>

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
