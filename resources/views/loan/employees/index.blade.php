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

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">All employees</h2>
                <p class="text-xs text-slate-500">{{ $employees->total() }} record(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
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
                                <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $employee->employee_number }}</td>
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->department ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->job_title ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $employee->branch ?? '—' }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    <div class="flex flex-col">
                                        @if ($employee->email)
                                            <span>{{ $employee->email }}</span>
                                        @endif
                                        @if ($employee->phone)
                                            <span class="text-xs">{{ $employee->phone }}</span>
                                        @endif
                                        @if (! $employee->email && ! $employee->phone)
                                            <span>—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('loan.employees.edit', $employee) }}" class="text-indigo-600 hover:text-indigo-500 font-medium text-sm mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.employees.destroy', $employee) }}" class="inline" data-swal-confirm="Remove this employee?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 hover:text-red-500 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
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
