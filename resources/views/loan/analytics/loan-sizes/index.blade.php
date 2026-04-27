<x-loan-layout>
    <x-loan.page
        title="Loan sizes"
        subtitle="Define principal bands for reporting and portfolio segmentation (e.g. micro, SME, corporate)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.loan_sizes.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add size band
            </a>
        </x-slot>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Bands</h2>
                <p class="text-xs text-slate-500">{{ $sizes->total() }} defined</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Order</th>
                            <th class="px-5 py-3">Label</th>
                            <th class="px-5 py-3">Min (Ksh)</th>
                            <th class="px-5 py-3">Max (Ksh)</th>
                            <th class="px-5 py-3">Description</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sizes as $s)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $s->sort_order }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $s->label }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ number_format($s->min_principal, 2) }}</td>
                                <td class="px-5 py-3 tabular-nums">{{ $s->max_principal !== null ? number_format($s->max_principal, 2) : '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate" title="{{ $s->description }}">{{ $s->description ?: '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.analytics.loan_sizes.edit', $s) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.analytics.loan_sizes.destroy', $s) }}" class="inline" data-swal-confirm="Remove this band?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No bands yet. <a href="{{ route('loan.analytics.loan_sizes.create') }}" class="text-indigo-600 font-medium hover:underline">Add the first loan size</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sizes->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $sizes->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
