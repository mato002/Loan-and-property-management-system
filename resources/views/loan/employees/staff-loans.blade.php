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

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Account ref, employee..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (['current', 'arrears', 'closed'] as $rowStatus)
                            <option value="{{ $rowStatus }}" @selected(($status ?? '') === $rowStatus)>{{ ucfirst($rowStatus) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 20, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.employees.staff_loans') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.employees.staff_loans', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.employees.staff_loans', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.employees.staff_loans', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Facilities</h2>
                <p class="text-xs text-slate-500">{{ $loans->total() }} record(s)</p>
            </div>
            <form method="post" action="{{ route('loan.employees.staff_loans.bulk') }}" class="border-b border-slate-100 px-5 py-3" data-swal-confirm="Apply bulk action to selected staff loans?">
                @csrf
                <div class="flex flex-wrap items-center gap-2">
                    <select name="action" class="h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-700">
                        <option value="">Bulk action</option>
                        <option value="current">Set current</option>
                        <option value="arrears">Set arrears</option>
                        <option value="closed">Set closed</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="h-9 rounded-lg bg-slate-800 px-3 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-slate-700">Apply</button>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.loan-row').forEach(cb => cb.checked = this.checked)"></th>
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
                                <td class="px-5 py-3"><input type="checkbox" class="loan-row" name="ids[]" value="{{ $loan->id }}"></td>
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
                                    <button
                                        type="submit"
                                        formaction="{{ route('loan.employees.staff_loans.destroy', $loan) }}"
                                        formmethod="post"
                                        name="_method"
                                        value="delete"
                                        class="text-red-600 hover:text-red-500 font-medium text-sm"
                                        data-swal-confirm="Delete this loan record?"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                                    No staff loans. <a href="{{ route('loan.employees.staff_loans.create') }}" class="text-indigo-600 font-medium hover:underline">Add a record</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </form>
            @if ($loans->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $loans->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
