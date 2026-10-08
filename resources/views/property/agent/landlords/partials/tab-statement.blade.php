@php
    use App\Support\Property\ResponsiveTableColumns;
    use App\Support\Property\LandlordMonthlyShareTotals;
    use Illuminate\Support\HtmlString;

    $isMonthScoped = (bool) ($isMonthScoped ?? false);
    $monthlyBreakdown = collect($monthlyBreakdown ?? []);
    $monthSettlements = collect($monthSettlements ?? []);
    $fy = (int) ($fyValue ?? now()->year);
    $uptoMonth = LandlordMonthlyShareTotals::uptoMonth($fy);
    $toDateLabel = LandlordMonthlyShareTotals::periodHint($fy, $uptoMonth);
    $shareToDate = $shareToDate ?? LandlordMonthlyShareTotals::toDateForProperties($propertyBreakdown ?? [], $uptoMonth);
    $openMonth = $isMonthScoped ? (string) ($monthValue ?? '') : '';
    $currentShareMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($monthValue ?? '')) ? (string) $monthValue : now()->format('Y-m');
    $linkedProperties = collect($propertyBreakdown ?? [])->map(fn ($row) => [
        'id' => (int) ($row['property_id'] ?? 0),
        'name' => (string) ($row['property_name'] ?? ''),
    ])->filter(fn ($row) => $row['id'] > 0)->values();

    $fyOverviewUrl = route('property.landlords.show', [
        'landlord' => $landlord->id,
        'tab' => 'statement',
        'fy' => $fy,
    ], false);

    $reportUrl = function (array $extra = []) use ($landlord, $fy) {
        return route('property.landlords.show', array_filter(array_merge([
            'landlord' => $landlord->id,
            'tab' => 'statement',
            'fy' => $fy,
        ], $extra), static fn ($v) => $v !== null && $v !== ''), false);
    };

    $printUrlFor = function (array $extra = []) use ($landlord, $fy) {
        return route('property.landlords.statement.print', array_filter(array_merge([
            'landlord' => $landlord->id,
            'fy' => $fy,
            'print' => 1,
        ], $extra), static fn ($v) => $v !== null && $v !== ''), false);
    };

    $breakdownColumns = ['Property', 'Ownership %', 'Owner share', 'Pending share', 'Agent earning', 'Last collection', 'Reports'];
    $breakdownRows = [];
    $breakdownExpansions = [];
    foreach ($propertyBreakdown as $row) {
        $pid = (int) ($row['property_id'] ?? 0);
        $ytd = LandlordMonthlyShareTotals::toDate($row['monthly_shares'] ?? [], $uptoMonth);
        $propertyUrl = $pid > 0 ? route('property.properties.show', ['property' => $pid], false) : '#';
        $breakdownExpansions[] = new HtmlString(
            view('property.agent.landlords.partials.monthly-share-panel', [
                'months' => $row['monthly_shares'] ?? [],
                'currentMonth' => $currentShareMonth,
            ])->render()
        );

        $summaryPrint = $printUrlFor(['property_id' => $pid, 'report' => 'summary']);
        $detailPrint = $printUrlFor(['property_id' => $pid, 'report' => 'detail']);
        $summaryCsv = $reportUrl(['property_id' => $pid, 'report' => 'summary', 'export' => 'csv', 'export_scope' => 'properties']);
        $detailCsv = $reportUrl(['property_id' => $pid, 'report' => 'detail', 'export' => 'csv']);
        $detailXls = $reportUrl(['property_id' => $pid, 'report' => 'detail', 'export' => 'xls']);
        $detailPdf = $reportUrl(['property_id' => $pid, 'report' => 'detail', 'export' => 'pdf']);

        $breakdownRows[] = [
            new HtmlString(
                view('property.agent.landlords.partials.monthly-share-toggle', [
                    'propertyUrl' => $propertyUrl,
                    'propertyName' => (string) ($row['property_name'] ?? ''),
                ])->render()
            ),
            number_format((float) ($row['ownership_percent'] ?? 0), 2).'%',
            \App\Services\Property\PropertyMoney::kes($ytd['owner_share']),
            \App\Services\Property\PropertyMoney::kes($ytd['pending_share'] > 0.009 ? $ytd['pending_share'] : (float) ($row['pending_share'] ?? 0)),
            \App\Services\Property\PropertyMoney::kes($ytd['agent_earning']),
            ! empty($row['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string) $row['last_paid_at'])->format('Y-m-d') : '—',
            new HtmlString(
                '<div class="flex flex-col gap-1 text-xs font-semibold">'
                .'<a href="'.e($summaryPrint).'" target="_blank" rel="noopener" data-turbo="false" class="text-slate-700 hover:underline">Print FY summary</a>'
                .'<a href="'.e($detailPrint).'" target="_blank" rel="noopener" data-turbo="false" class="text-teal-700 hover:underline">Print FY full</a>'
                .'<a href="'.e($summaryCsv).'" data-turbo="false" class="text-slate-600 hover:underline">CSV summary</a>'
                .'<a href="'.e($detailCsv).'" data-turbo="false" class="text-slate-600 hover:underline">CSV full</a>'
                .'<a href="'.e($detailXls).'" data-turbo="false" class="text-slate-600 hover:underline">Excel full</a>'
                .'<a href="'.e($detailPdf).'" data-turbo="false" class="text-indigo-700 hover:underline">PDF full</a>'
                .'</div>'
            ),
        ];
    }
@endphp

<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm w-full min-w-0 overflow-visible">
    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white break-words">{{ $landlord->name }}</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300 break-all">
                @if ($landlord->email)
                    <a href="mailto:{{ $landlord->email }}" class="hover:underline">{{ $landlord->email }}</a>
                @endif
                @if ($landlord->phone)
                    @if ($landlord->email) · @endif
                    <x-phone-link :value="$landlord->phone" />
                @endif
                @if (! $landlord->email && ! $landlord->phone)
                    —
                @endif
            </p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Use <strong>+</strong> on a month row to expand the unit-level statement. Use Print / Export Statement for any property, period, summary, or full detail.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @include('property.agent.partials.statement_report_builder', [
                'showUrl' => route('property.landlords.show', ['landlord' => $landlord->id], false),
                'printUrl' => route('property.landlords.statement.print', ['landlord' => $landlord->id], false),
                'properties' => $linkedProperties,
                'showProperty' => true,
                'showDetailLevel' => true,
                'defaultPeriod' => $isMonthScoped ? 'month' : 'fy',
                'defaultReport' => 'detail',
                'defaultFy' => $fy,
                'defaultMonth' => $openMonth !== '' ? $openMonth : now()->format('Y-m'),
                'extraQuery' => ['tab' => 'statement'],
                'layout' => 'split',
                'buttonLabel' => 'Print / Export Statement',
                'formats' => ['pdf', 'xls', 'csv', 'word'],
                'quickActions' => [
                    ['label' => 'Print FY summary', 'href' => $printUrlFor(['report' => 'summary']), 'kind' => 'print'],
                    ['label' => 'Print FY full', 'href' => $printUrlFor(['report' => 'detail']), 'kind' => 'print'],
                ],
            ])
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
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($shareToDate['owner_share'] ?? 0)) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Still to pay</p>
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) (($shareToDate['pending_share'] ?? 0) > 0.009 ? $shareToDate['pending_share'] : ($totals['pending_share'] ?? 0))) }}</p>
        </div>
        <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3 col-span-2 sm:col-span-1">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Your earnings</p>
            <p class="text-sm sm:text-base font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($shareToDate['agent_earning'] ?? 0)) }}</p>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-500">Totals {{ $toDateLabel }}. Monthly figures are in the tables below. Generated {{ now()->format('Y-m-d H:i') }}</p>
</div>

<div class="property-erp-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm w-full min-w-0 overflow-visible mt-4">
    <div class="px-3 sm:px-4 py-3 border-b border-slate-100 dark:border-slate-700/80">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Month-by-month (FY {{ $fy }})</h3>
    </div>

    <div class="w-full min-w-0">
        <x-property.responsive.table-wrapper min-width="960px">
            <table class="property-erp-table w-full table-auto border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_th]:dark:border-slate-700 [&_td]:border [&_td]:border-slate-200 [&_td]:dark:border-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <tr>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Month</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Gross collected</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Paid to landlord</th>
                        <th class="px-3 sm:px-4 py-2.5 sm:py-3 whitespace-normal">Pending</th>
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
                            $exportDetailUrl = $reportUrl([
                                'month' => $ym,
                                'report' => 'detail',
                                'export' => 'csv',
                                'export_scope' => 'statement',
                            ]);
                            $exportSummaryUrl = $reportUrl([
                                'month' => $ym,
                                'report' => 'summary',
                                'export' => 'csv',
                                'export_scope' => 'properties',
                            ]);
                            $printDetailUrl = $printUrlFor([
                                'month' => $ym,
                                'report' => 'detail',
                            ]);
                            $printSummaryUrl = $printUrlFor([
                                'month' => $ym,
                                'report' => 'summary',
                            ]);
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
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['paid_share'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['pending_share'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['owner_share'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums whitespace-nowrap">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['agent_earning'] ?? 0)) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 tabular-nums">{{ (int) ($row['active_properties'] ?? 0) }}</td>
                            <td class="px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 dark:text-slate-200 align-top">
                                <div class="flex flex-col gap-1 text-xs font-semibold">
                                    <a href="{{ $printDetailUrl }}" target="_blank" rel="noopener" data-turbo="false" class="text-teal-700 hover:underline">Print full</a>
                                    <a href="{{ $printSummaryUrl }}" target="_blank" rel="noopener" data-turbo="false" class="text-slate-700 hover:underline">Print summary</a>
                                    <a href="{{ $exportDetailUrl }}" data-turbo="false" class="text-slate-600 hover:underline">CSV full</a>
                                    <a href="{{ $exportSummaryUrl }}" data-turbo="false" class="text-slate-600 hover:underline">CSV summary</a>
                                </div>
                            </td>
                        </tr>
                        @if ($isOpen)
                            <tr class="border-t border-teal-200 dark:border-teal-800 bg-slate-50/80 dark:bg-slate-900/40">
                                <td colspan="8" class="px-3 sm:px-4 py-4 align-top">
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
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500 dark:text-slate-400">
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

@include('property.agent.landlords.partials.responsive-table-section', [
        'title' => 'Property breakdown ('.$toDateLabel.')',
        'columns' => $breakdownColumns,
        'rows' => $breakdownRows,
        'rowExpansions' => $breakdownExpansions,
        'columnConfig' => ResponsiveTableColumns::landlordStatementBreakdown(),
        'emptyTitle' => 'No linked properties',
        'emptyHint' => 'Link this landlord to a property to see breakdown rows.',
        'tableMinWidth' => '860px',
    ])
