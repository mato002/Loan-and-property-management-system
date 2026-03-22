<x-loan-layout>
    <x-loan.page
        title="Staff portfolios"
        subtitle="Assign portfolio codes and metrics to employees."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.portfolios.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add assignment
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Assignments</h2>
                <p class="text-xs text-slate-500">{{ $portfolios->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Staff</th>
                            <th class="px-5 py-3">Portfolio code</th>
                            <th class="px-5 py-3">Active loans</th>
                            <th class="px-5 py-3">Outstanding (Ksh)</th>
                            <th class="px-5 py-3">PAR %</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($portfolios as $p)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $p->employee->full_name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p->portfolio_code }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->active_loans }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->outstanding_amount !== null ? number_format($p->outstanding_amount, 2) : '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->par_rate !== null ? number_format($p->par_rate, 2).'%' : '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.employees.portfolios.edit', $p) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.employees.portfolios.destroy', $p) }}" class="inline" data-swal-confirm="Remove this portfolio row?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                    No portfolios. <a href="{{ route('loan.employees.portfolios.create') }}" class="text-indigo-600 font-medium hover:underline">Add an assignment</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($portfolios->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $portfolios->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
