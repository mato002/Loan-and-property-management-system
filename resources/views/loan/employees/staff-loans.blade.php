<x-loan-layout>
    <x-loan.page
        title="Staff loans"
        subtitle="Track principal, balance, and next due date for staff facilities."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.staff_loans.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New staff loan
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Facilities</h2>
                <p class="text-xs text-slate-500">{{ $loans->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Account</th>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Principal</th>
                            <th class="px-5 py-3">Balance</th>
                            <th class="px-5 py-3">Next due</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($loans as $loan)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $loan->account_ref ?? '—' }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $loan->employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ number_format($loan->principal, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ number_format($loan->balance, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums whitespace-nowrap">{{ $loan->next_due_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($loan->status === 'current')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Current</span>
                                    @elseif ($loan->status === 'closed')
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200">Closed</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100">Arrears</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.employees.staff_loans.edit', $loan) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.employees.staff_loans.destroy', $loan) }}" class="inline" data-swal-confirm="Delete this loan record?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    No staff loans. <a href="{{ route('loan.employees.staff_loans.create') }}" class="text-indigo-600 font-medium hover:underline">Add a record</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($loans->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
