@php
    $billedCharges = $billedCharges ?? collect();
    $billedTotal = (float) ($billedTotal ?? 0);
    $billedMonth = trim((string) ($filters['month'] ?? ''));
    $billedMonthLabel = '';
    if (preg_match('/^\d{4}-\d{2}$/', $billedMonth) === 1) {
        try {
            $billedMonthLabel = \Illuminate\Support\Carbon::parse($billedMonth.'-01')->format('M Y');
        } catch (\Throwable) {
            $billedMonthLabel = $billedMonth;
        }
    }
    $standingExportQuery = array_merge(request()->query(), ['export' => 'standing', 'ops_tab' => 'standing']);
@endphp
<div class="property-compact-panel rounded-xl sm:rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 shadow-sm space-y-3 p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Charges billed</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Utility charges already billed to tenants, with the month each one is for. The rates are set on the property.
            </p>
        </div>
        <a href="{{ route('property.revenue.utilities', $standingExportQuery, false) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 hover:bg-slate-50 min-h-[40px]">
            Export CSV
        </a>
    </div>
    <p class="text-sm text-slate-700">
        <span class="font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($billedTotal) }}</span>
        <span class="text-slate-500"> charged</span>
        @if ($billedMonthLabel !== '')
            <span class="text-slate-500"> for {{ $billedMonthLabel }}</span>
        @else
            <span class="text-slate-500"> across all months</span>
        @endif
        @if ($billedCharges instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <span class="text-slate-400"> · </span>{{ $billedCharges->total() }} {{ \Illuminate\Support\Str::plural('charge', $billedCharges->total()) }}
        @endif
    </p>
    <x-property.responsive.table-wrapper>
        <table class="property-erp-table min-w-full border-collapse text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Month</th>
                    <th class="px-3 py-2">Tenant</th>
                    <th class="px-3 py-2">Account</th>
                    <th class="px-3 py-2">Property / unit</th>
                    <th class="px-3 py-2">Charge</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($billedCharges as $invoice)
                    @php
                        $period = trim((string) ($invoice->billing_period ?? ''));
                        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1 && $invoice->issue_date) {
                            $period = $invoice->issue_date->format('Y-m');
                        }
                        $monthLabel = '—';
                        if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
                            try {
                                $monthLabel = \Illuminate\Support\Carbon::parse($period.'-01')->format('M Y');
                            } catch (\Throwable) {
                                $monthLabel = $period;
                            }
                        }
                        $balance = round(max(0, (float) $invoice->amount - (float) $invoice->amount_paid), 2);
                        $status = $balance <= 0.009 ? 'Paid' : (((float) $invoice->amount_paid > 0.009) ? 'Partial' : 'Unpaid');
                    @endphp
                    <tr class="border-t border-slate-100 hover:bg-slate-50/80">
                        <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $monthLabel }}</td>
                        <td class="px-3 py-2 font-medium">
                            @if ((int) ($invoice->pm_tenant_id ?? 0) > 0)
                                <a href="{{ route('property.tenants.show', ['tenant' => $invoice->pm_tenant_id], false) }}" data-turbo-frame="property-main" class="text-indigo-700 hover:underline">{{ $invoice->tenant?->name ?? '—' }}</a>
                            @else
                                {{ $invoice->tenant?->name ?? '—' }}
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums text-slate-600">{{ $invoice->tenant?->account_number ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $invoice->unit?->property?->name ?? '—' }} / {{ $invoice->unit?->label ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div>{{ $invoice->chargeCategoryLabel() }}</div>
                            @if (filled($invoice->invoice_no))
                                <div class="text-xs text-slate-500">{{ $invoice->invoice_no }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums font-semibold">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                        <td class="px-3 py-2 text-xs font-semibold {{ $status === 'Paid' ? 'text-emerald-700' : ($status === 'Partial' ? 'text-amber-700' : 'text-slate-600') }}">{{ $status }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('property.revenue.invoices.show', ['invoice' => $invoice->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-600 hover:underline">Invoice</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No utility charges have been billed for this filter. Rates are set on the property. Use Billing month to see one month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-property.responsive.table-wrapper>
    @if ($billedCharges instanceof \Illuminate\Contracts\Pagination\Paginator && $billedCharges->hasPages())
        <div class="pt-2">
            {{ $billedCharges->links() }}
        </div>
    @endif
</div>
