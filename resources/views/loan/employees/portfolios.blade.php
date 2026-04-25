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

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Code, employee name, number..." oninput="window.clearTimeout(this._autoSearchTimer); this._autoSearchTimer = window.setTimeout(() => this.form.requestSubmit(), 1100);" class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Employee</label>
                    <select name="employee_id" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (($employees ?? []) as $employee)
                            <option value="{{ $employee->id }}" @selected((int) ($employeeId ?? 0) === (int) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.requestSubmit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 20, 30, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.employees.portfolios') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.employees.portfolios', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.employees.portfolios', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.employees.portfolios', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Assignments</h2>
                <p class="text-xs text-slate-500">{{ $portfolios->total() }} record(s)</p>
            </div>
            <form method="post" action="{{ route('loan.employees.portfolios.bulk_delete') }}" class="border-b border-slate-100 px-5 py-3" data-swal-confirm="Delete selected portfolio assignments?">
                @csrf
                <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-red-700">Delete selected</button>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.portfolio-row').forEach(cb => cb.checked = this.checked)"></th>
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
                                <td class="px-5 py-3"><input type="checkbox" class="portfolio-row" name="ids[]" value="{{ $p->id }}"></td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $p->employee->full_name }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $p->portfolio_code }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->active_loans }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->outstanding_amount !== null ? number_format($p->outstanding_amount, 2) : '—' }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $p->par_rate !== null ? number_format($p->par_rate, 2).'%' : '—' }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.employees.portfolios.edit', $p) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <button
                                        type="submit"
                                        formaction="{{ route('loan.employees.portfolios.destroy', $p) }}"
                                        formmethod="post"
                                        name="_method"
                                        value="delete"
                                        class="text-red-600 hover:text-red-500 font-medium text-sm"
                                        data-swal-confirm="Remove this portfolio row?"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    No portfolios. <a href="{{ route('loan.employees.portfolios.create') }}" class="text-indigo-600 font-medium hover:underline">Add an assignment</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            </form>
            @if ($portfolios->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $portfolios->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
