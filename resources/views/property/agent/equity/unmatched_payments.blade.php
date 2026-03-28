<x-property-layout>
    <x-slot name="header">Unmatched Equity Payments</x-slot>

    <x-property.page
        title="Unmatched Equity Payments"
        subtitle="Transactions that could not be auto-matched and require agent review."
    >
        <form method="get" class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="block rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="block rounded-xl border-slate-300 shadow-sm">
            </div>
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Date</th>
                        <th class="px-4 py-3 text-left font-bold">Transaction</th>
                        <th class="px-4 py-3 text-right font-bold">Amount</th>
                        <th class="px-4 py-3 text-left font-bold">Account</th>
                        <th class="px-4 py-3 text-left font-bold">Phone</th>
                        <th class="px-4 py-3 text-left font-bold">Reason</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3">{{ optional($item->created_at)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ $item->transaction_id }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $item->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $item->account_number ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $item->phone ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $item->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No unmatched transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    </x-property.page>
</x-property-layout>

