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
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Branch search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Branch..." class="rounded-lg border-slate-200 text-sm" />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="rounded-lg border-slate-200 text-sm">
                        @foreach ([10, 20, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Apply</button>
                <a href="{{ route('loan.book.collection_reports') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'csv'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'xls'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('loan.book.collection_reports', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
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
            @if (method_exists($byBranch, 'hasPages') && $byBranch->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $byBranch->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
