<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <form method="get" action="{{ route('loan.book.collection_reports') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-slate-200 text-sm" />
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
            </form>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-700">Collections by branch</h2>
                <p class="text-xs text-slate-500 mt-1">{{ $from }} → {{ $to }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3 text-right">Lines</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byBranch as $row)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $row->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ (int) $row->receipt_count }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-semibold text-slate-900">{{ number_format((float) $row->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-5 py-12 text-center text-slate-500">No receipts in this range.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
