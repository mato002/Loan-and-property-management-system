<x-property-layout>
    <x-slot name="header">Unmatched Payments</x-slot>

    <x-property.page
        title="Unmatched Payments"
        subtitle="Transactions from Equity and SMS Forwarder that could not be auto-matched and require manual assignment."
    >
        <form method="get" class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs text-slate-500">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Txn, phone, reason..." class="block rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="block rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="block rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Source</label>
                <select name="source" class="block rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    <option value="equity" @selected(($filters['source'] ?? '') === 'equity')>Equity</option>
                    <option value="sms_forwarder" @selected(($filters['source'] ?? '') === 'sms_forwarder')>SMS Forwarder</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Per page</label>
                <select name="per_page" class="block rounded-xl border-slate-300 shadow-sm">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
            <a href="{{ route('property.equity.unmatched') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Export CSV
            </a>
            <a href="{{ route('property.equity.unmatched.export', array_merge(request()->query(), ['format' => 'xls'])) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Export XLS
            </a>
            <a href="{{ route('property.equity.unmatched.print', request()->query()) }}" target="_blank" rel="noopener" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Print
            </a>
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
                        <th class="px-4 py-3 text-left font-bold">Source</th>
                        <th class="px-4 py-3 text-left font-bold">Reason</th>
                        <th class="px-4 py-3 text-right font-bold">Action</th>
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
                            <td class="px-4 py-3">
                                @if (($item->payment_method ?? '') === 'sms_forwarder')
                                    SMS Forwarder
                                @else
                                    Equity
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->reason }}</td>
                            <td class="px-4 py-3 text-right">
                                <a
                                    href="{{ route('property.equity.unmatched.show', $item) }}"
                                    class="inline-flex items-center rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700"
                                >
                                    Assign
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No unmatched transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}
            </p>
            {{ $items->links() }}
        </div>
    </x-property.page>
</x-property-layout>

