@include('property.agent.partials.utility_ledger_drilldown_styles')

<x-property.workspace
    :title="'Utility statement — '.$tenant->name"
    subtitle="Chronological water & utility ledger with debit/credit running balance."
    back-route="property.revenue.utilities.ledger"
    :stats="[
        ['label' => 'Current utility balance', 'value' => \App\Services\Property\PropertyMoney::kes((float) $currentBalance), 'hint' => 'Open water/mixed AR'],
        ['label' => 'Closing balance (period)', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($ledger['closing_balance'] ?? 0)), 'hint' => 'After filtered rows'],
        ['label' => 'Period debits', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($ledger['total_debit'] ?? 0)), 'hint' => 'Charges & penalties'],
        ['label' => 'Period credits', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($ledger['total_credit'] ?? 0)), 'hint' => 'Payments & reversals'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.tenants.utility.statement', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'pdf']), false) }}" target="_blank" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 min-h-[44px]">Download PDF</a>
        <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-xl bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 min-h-[44px] print-hide">Print</button>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.tenants.utility.statement', $tenant, false) }}" class="flex flex-wrap items-end gap-2 w-full print-hide" data-turbo-frame="property-main">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" aria-label="From date" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" aria-label="To date" />
            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 min-h-[44px]">Apply range</button>
            <a href="{{ route('property.tenants.utility.statement', array_merge(['tenant' => $tenant->id], request()->query(), ['export' => 'csv']), false) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 min-h-[44px] inline-flex items-center">Export CSV</a>
        </form>
    </x-slot>

    <div class="space-y-4 w-full min-w-0">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm print-hide">
            <p class="font-semibold text-slate-900">{{ $tenant->name }}</p>
            @if ($tenant->phone)
                <p class="text-slate-600">{{ $tenant->phone }}</p>
            @endif
            @if (($filters['from'] ?? '') || ($filters['to'] ?? ''))
                <p class="text-xs text-slate-500 mt-1">Period: {{ $filters['from'] ?: 'start' }} → {{ $filters['to'] ?: 'today' }}</p>
            @endif
        </div>

        <x-property.responsive.table-wrapper minWidth="980">
            <table class="property-erp-table min-w-full text-sm utility-ledger-table">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Debit</th>
                        <th class="px-4 py-3 text-right">Credit</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                        <th class="px-4 py-3 print-hide">Trace</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledger['rows'] as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-4 py-3">
                                @include('property.agent.partials.utility_ledger_type_badge', ['entryType' => $row['entry_type'], 'label' => $row['type_label']])
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $row['reference'] }}</td>
                            <td class="px-4 py-3 text-slate-600 max-w-xs">{{ $row['description'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-amber-900">{{ $row['debit_display'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-emerald-800">{{ $row['credit_display'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-semibold">{{ $row['balance_display'] }}</td>
                            <td class="px-4 py-3 print-hide">
                                @include('property.agent.partials.utility_ledger_drilldown', [
                                    'drilldown' => $row['drilldown'] ?? [],
                                    'tenantId' => $tenant->id,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No utility ledger entries in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>
    </div>
</x-property.workspace>
