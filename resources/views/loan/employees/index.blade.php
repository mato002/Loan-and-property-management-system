<x-loan-layout>
    <x-loan.page
        title="Employees"
        subtitle="Directory of staff linked to loan operations, leaves, and portfolios."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Add employee
            </a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Number, name, email, phone..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Department</label>
                    <select name="department" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (($departments ?? []) as $row)
                            <option value="{{ $row }}" @selected(($department ?? '') === $row)>{{ $row }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $row)
                            <option value="{{ $row }}" @selected(($branch ?? '') === $row)>{{ $row }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        @foreach ([10, 15, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.employees.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.employees.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.employees.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.employees.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">All employees</h2>
                <p class="text-xs text-slate-500">{{ $employees->total() }} record(s)</p>
            </div>

            <form id="employee-bulk-delete-form" method="post" action="{{ route('loan.employees.bulk_delete') }}" class="border-b border-slate-100 px-5 py-3" data-swal-confirm="Delete selected employee records?">
                @csrf
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm hover:bg-red-700">Delete selected</button>
                    <span class="text-xs text-slate-500">Bulk actions affect selected rows on this page.</span>
                </div>
            </form>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3"><input type="checkbox" onclick="document.querySelectorAll('.emp-row').forEach(cb => cb.checked = this.checked)"></th>
                            <th class="px-5 py-3">Number</th>
                            <th class="px-5 py-3">Name</th>
                            <th class="px-5 py-3">Department</th>
                            <th class="px-5 py-3">Job title</th>
                            <th class="px-5 py-3">Branch</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employees as $employee)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3"><input type="checkbox" class="emp-row" name="ids[]" value="{{ $employee->id }}" form="employee-bulk-delete-form"></td>
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $employee->employee_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->department ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->job_title ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    <div class="flex flex-col">
                                        @if ($employee->email)
                                            <span class="break-all">{{ $employee->email }}</span>
                                        @endif
                                        @if ($employee->phone)
                                            <span class="text-xs break-all">{{ $employee->phone }}</span>
                                        @endif
                                        @if (! $employee->email && ! $employee->phone)
                                            <span>—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.employees.show', $employee) }}" class="text-slate-700 hover:text-slate-900 font-medium text-sm mr-3">View</a>
                                    <a href="{{ route('loan.employees.edit', $employee) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    @if ($employee->email)
                                        <form method="post" action="{{ route('loan.employees.resend_login', $employee) }}" class="inline" data-swal-confirm="Resend login credentials to this employee email?">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-500 font-medium text-sm mr-3">Resend login</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('loan.employees.destroy', $employee) }}" class="inline" data-swal-confirm="Remove this employee?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-slate-500">
                                    No employees yet. Use <span class="font-medium text-slate-700">Add Employee</span> to create the first record.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($employees->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $employees->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
