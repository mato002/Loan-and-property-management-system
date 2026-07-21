@php
    use App\Support\Property\ResponsiveTableColumns;
    use Illuminate\Support\HtmlString;

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

<x-property.workspace
    :title="'Landlord Statement: '.$landlord->name"
    :subtitle="'Printable period snapshot · '.$periodLabel"
    back-route="property.landlords.index"
    :stats="[]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlords.show', ['landlord' => $landlord->id, 'month' => $monthValue, 'fy' => $fyValue], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-gray-900 dark:text-slate-200">Back to profile</a>
        <button type="button" onclick="window.print()" class="inline-flex min-h-[44px] w-full sm:w-auto items-center justify-center rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Print</button>
    </x-slot>

    <style>
        @media print {
            .statement-no-print { display: none !important; }
        }
    </style>

    <div class="space-y-4 sm:space-y-5 w-full min-w-0">
        <div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm w-full min-w-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white break-words">{{ $landlord->name }}</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300 break-all">{{ $landlord->email ?: ($landlord->phone ?: '—') }}</p>
                </div>
                <div class="text-sm text-slate-600 dark:text-slate-300 shrink-0">
                    <p><span class="font-medium text-slate-900 dark:text-white">Period:</span> {{ $periodLabel }}</p>
                    <p><span class="font-medium text-slate-900 dark:text-white">Generated:</span> {{ now()->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3 mt-4">
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Properties</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-white tabular-nums">{{ $totals['properties'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Ownership %</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-white tabular-nums">{{ number_format((float) ($totals['ownership_sum'] ?? 0), 2) }}%</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Owner share</p>
                    <p class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['owner_share'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Pending share</p>
                    <p class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['pending_share'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3 col-span-2 sm:col-span-1">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Your earnings</p>
                    <p class="text-sm sm:text-base font-semibold text-slate-900 dark:text-white tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($totals['agent_earning'] ?? 0)) }}</p>
                </div>
            </div>
        </div>

        @include('property.agent.landlords.partials.responsive-table-section', [
            'title' => 'Property breakdown',
            'columns' => $breakdownColumns,
            'rows' => $breakdownRows,
            'columnConfig' => ResponsiveTableColumns::landlordStatementBreakdown(),
            'emptyTitle' => 'No linked properties',
            'emptyHint' => 'Link this landlord to a property to see breakdown rows.',
            'tableMinWidth' => '720px',
        ])
    </div>
</x-property.workspace>
