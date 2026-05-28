<x-property.workspace
    title="Utility reconciliation"
    subtitle="Portfolio utility billing totals, KPIs, aging, and GL tie-out."
    back-route="property.revenue.utilities"
    :stats="$stats"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.utilities.analytics', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-violet-300 bg-violet-50 px-3 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100 min-h-[44px]">Intelligence</a>
        <a href="{{ route('property.revenue.utilities.ledger', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 min-h-[44px]">Tenant ledgers</a>
        <a href="{{ route('property.revenue.utilities.periods', absolute: false) }}" class="inline-flex items-center justify-center rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800 hover:bg-rose-100 min-h-[44px]">Period closing</a>
        @include('property.agent.partials.export_dropdown', [
            'csvUrl' => route('property.revenue.utilities.reconciliation', array_merge(request()->query(), ['export' => 'csv']), false),
            'pdfUrl' => route('property.revenue.utilities.reconciliation', array_merge(request()->query(), ['export' => 'pdf']), false),
        ])
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="flex flex-wrap items-end gap-2 w-full" data-turbo-frame="property-main">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" aria-label="From date" />
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px]" aria-label="To date" />
            <select name="property" class="rounded-lg border border-slate-200 bg-white text-sm px-3 py-2 min-h-[44px] min-w-[160px]">
                <option value="">All properties</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}" @selected(($filters['property'] ?? '') == (string) $property->id)>{{ $property->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800 min-h-[44px]">Apply</button>
            <a href="{{ route('property.revenue.utilities.reconciliation', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 min-h-[44px] inline-flex items-center">Reset</a>
        </form>
    </x-slot>

    @php $totals = $data['totals']; @endphp

    <div class="utility-ops-shell space-y-4 w-full min-w-0">
        <x-property.utility.compact-kpi-strip :items="$kpiCards" />

        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2">
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Total billed</p>
                <p class="utility-kpi-tile-value text-slate-900">{{ \App\Services\Property\PropertyMoney::kes($totals['total_billed']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Penalties</p>
                <p class="utility-kpi-tile-value text-amber-800">{{ \App\Services\Property\PropertyMoney::kes($totals['total_penalties']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Collected</p>
                <p class="utility-kpi-tile-value text-emerald-700">{{ \App\Services\Property\PropertyMoney::kes($totals['total_collected']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Credit applied</p>
                <p class="utility-kpi-tile-value text-slate-900">{{ \App\Services\Property\PropertyMoney::kes($totals['total_credited']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Reversed</p>
                <p class="utility-kpi-tile-value text-amber-800">{{ \App\Services\Property\PropertyMoney::kes($totals['total_reversed']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Unapplied credit</p>
                <p class="utility-kpi-tile-value text-emerald-800">{{ \App\Services\Property\PropertyMoney::kes($totals['unapplied_funds']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Suspense (1250)</p>
                <p class="utility-kpi-tile-value text-amber-900">{{ \App\Services\Property\PropertyMoney::kes($totals['suspense_balance']) }}</p>
            </div>
            <div class="utility-kpi-tile">
                <p class="utility-kpi-tile-label">Utility AR (1210)</p>
                <p class="utility-kpi-tile-value text-slate-900">{{ \App\Services\Property\PropertyMoney::kes($totals['utility_ar_gl']) }}</p>
            </div>
            <div class="utility-kpi-tile col-span-2 sm:col-span-1">
                <p class="utility-kpi-tile-label">GL variance</p>
                <p class="utility-kpi-tile-value {{ abs($totals['gl_subledger_variance']) > 0.01 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ \App\Services\Property\PropertyMoney::kes($totals['gl_subledger_variance']) }}
                </p>
                <p class="utility-kpi-tile-hint">1210 − open invoice AR</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Utility AR aging</h3>
                <a href="{{ route('property.reports.expense.utility_aging', absolute: false) }}" class="text-xs font-semibold text-indigo-600 hover:underline min-h-[44px] inline-flex items-center">Full aging report</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 divide-x divide-y md:divide-y-0 divide-slate-100">
                @foreach ($data['aging'] as $bucket)
                    <div class="p-3 sm:p-4 text-center">
                        <p class="text-[10px] sm:text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $bucket['label'] }}</p>
                        <p class="mt-1 text-base sm:text-lg font-bold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($bucket['amount']) }}</p>
                        <p class="text-[11px] text-slate-500">{{ $bucket['count'] }} invoice(s)</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="md:hidden space-y-2">
            @forelse (array_slice($data['aging_rows'], 0, 50) as $row)
                <article class="utility-invoice-panel">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <a href="{{ route('property.revenue.invoices.show', $row['invoice_id'], false) }}" class="font-semibold text-indigo-600 hover:underline">{{ $row['invoice_no'] }}</a>
                            <p class="text-xs text-slate-600 mt-0.5">{{ $row['tenant'] }}</p>
                            <p class="text-[11px] text-slate-500">{{ $row['property'] }} / {{ $row['unit'] }}</p>
                        </div>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold shrink-0
                            @if($row['bucket_key'] === 'current') bg-emerald-100 text-emerald-800
                            @elseif($row['bucket_key'] === '90_plus') bg-red-100 text-red-800
                            @else bg-amber-100 text-amber-800 @endif">
                            {{ $row['bucket'] }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <span class="text-slate-500">Due {{ $row['due_date'] }}</span>
                        <span class="font-bold text-slate-900 tabular-nums">{{ $row['balance_display'] }}</span>
                    </div>
                    <a href="{{ route('property.tenants.utility.statement', $row['tenant_id'], false) }}" class="mt-2 inline-flex text-xs font-semibold text-indigo-600 hover:underline min-h-[44px] items-center">Tenant statement</a>
                </article>
            @empty
                <p class="text-sm text-slate-500 py-8 text-center">No open utility invoices.</p>
            @endforelse
        </div>

        <x-property.responsive.table-wrapper minWidth="960" class="hidden md:block">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3">Property / Unit</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Bucket</th>
                        <th class="px-4 py-3 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (array_slice($data['aging_rows'], 0, 50) as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <a href="{{ route('property.revenue.invoices.show', $row['invoice_id'], false) }}" class="font-medium text-indigo-600 hover:underline">{{ $row['invoice_no'] }}</a>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('property.tenants.utility.statement', $row['tenant_id'], false) }}" class="text-slate-800 hover:underline">{{ $row['tenant'] }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['property'] }} / {{ $row['unit'] }}</td>
                            <td class="px-4 py-3">{{ $row['due_date'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold
                                    @if($row['bucket_key'] === 'current') bg-emerald-100 text-emerald-800
                                    @elseif($row['bucket_key'] === '90_plus') bg-red-100 text-red-800
                                    @else bg-amber-100 text-amber-800 @endif">
                                    {{ $row['bucket'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $row['balance_display'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">No open utility invoices.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-property.responsive.table-wrapper>
    </div>
</x-property.workspace>
