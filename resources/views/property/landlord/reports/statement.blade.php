<x-property-layout>
    <x-slot name="header">Monthly statement</x-slot>

    <x-property.page
        title="Monthly statement"
        subtitle="Owner statement pack with opening/closing balances, invoice performance, and maintenance spend."
    >
        <form method="get" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Statement month</label>
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm" />
            </div>
            @if (!empty($selectedPropertyId))
                <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}" />
            @endif
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply</button>
            <a href="{{ route('property.landlord.reports.statement.export', array_filter(['month' => $month, 'property_id' => $selectedPropertyId ?? null])) }}" data-turbo="false" class="rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60">Download CSV</a>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Opening balance</p><p class="text-lg font-semibold">{{ $openingBalance }}</p></div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Income billed</p><p class="text-lg font-semibold">{{ $incomeBilled }}</p></div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Income collected</p><p class="text-lg font-semibold">{{ $incomeCollected }}</p></div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Maintenance booked</p><p class="text-lg font-semibold">{{ $maintenanceBooked }}</p></div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Ledger credits</p><p class="text-lg font-semibold">{{ $ledgerCredits }}</p></div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4"><p class="text-xs text-slate-500">Ledger debits</p><p class="text-lg font-semibold">{{ $ledgerDebits }}</p></div>
            <div class="rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20 p-4 sm:col-span-2"><p class="text-xs text-slate-500">Closing balance</p><p class="text-2xl font-semibold">{{ $closingBalance }}</p></div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-4">
            <h3 class="text-sm font-semibold mb-3">Invoice lines ({{ $invoiceRows->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr><th class="py-2 pr-3">Invoice</th><th class="py-2 pr-3">Property / Unit</th><th class="py-2 pr-3">Amount</th><th class="py-2">Paid</th></tr>
                    </thead>
                    <tbody>
                        @forelse($invoiceRows as $i)
                            <tr class="border-b border-slate-100 dark:border-slate-700/70"><td class="py-2 pr-3">{{ $i->invoice_no }}</td><td class="py-2 pr-3">{{ $i->unit?->property?->name ?? '—' }} / {{ $i->unit?->label ?? '—' }}</td><td class="py-2 pr-3">{{ \App\Services\Property\PropertyMoney::kes((float)$i->amount) }}</td><td class="py-2">{{ \App\Services\Property\PropertyMoney::kes((float)$i->amount_paid) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-slate-500">No invoices in this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
