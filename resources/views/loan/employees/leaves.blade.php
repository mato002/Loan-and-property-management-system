<x-loan-layout>
    <x-loan.page
        title="Staff leaves"
        subtitle="Submit leave requests and update approval status."
    >
        @php
            $leaveMapped = $mapped ?? [];
            $leaveCustomFields = $custom ?? [];
            $notesLabel = (string) ($leaveMapped['notes']['label'] ?? 'Notes');
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.employees.leaves.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New leave request
            </a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Employee name / number..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Employee</label>
                    <select name="employee_id" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((int) ($employeeId ?? 0) === (int) $employee->id)>{{ $employee->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Leave type</label>
                    <select name="leave_type" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (($leaveTypes ?? []) as $type)
                            <option value="{{ $type }}" @selected(($leaveType ?? '') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Status</label>
                    <select name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach (['pending', 'approved', 'rejected'] as $rowStatus)
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
                <a href="{{ route('loan.employees.leaves') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.employees.leaves', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.employees.leaves', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.employees.leaves', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All requests</h2>
                <p class="text-xs text-slate-500">{{ $leaves->total() }} record(s)</p>
            </div>

            <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Leave period</th>
                            <th class="px-5 py-3">Duration</th>
                            <th class="px-5 py-3">Leave type</th>
                            <th class="px-5 py-3">{{ $notesLabel }}</th>
                            <th class="px-5 py-3">Approvals</th>
                            @foreach ($leaveCustomFields as $field)
                                <th class="px-5 py-3">{{ $field['label'] ?? $field['key'] }}</th>
                            @endforeach
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaves as $leave)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $leave->employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">
                                    {{ $leave->start_date->format('M d') }} - {{ $leave->end_date->format('M d') }}
                                </td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $leave->days }} {{ \Illuminate\Support\Str::plural('Day', (int) $leave->days) }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $leave->leave_type }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $leave->notes ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    @if ($leave->status === 'pending')
                                        <div class="flex flex-wrap gap-2">
                                            <form method="post" action="{{ route('loan.employees.leaves.status', $leave) }}" class="inline">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="status" value="approved" />
                                                <button type="submit" class="text-xs font-semibold text-emerald-700 hover:underline">Approve</button>
                                            </form>
                                            <form method="post" action="{{ route('loan.employees.leaves.status', $leave) }}" class="inline">
                                                @csrf
                                                @method('patch')
                                                <input type="hidden" name="status" value="rejected" />
                                                <button type="submit" class="text-xs font-semibold text-red-700 hover:underline">Reject</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-500">{{ ucfirst($leave->status) }}</span>
                                    @endif
                                </td>
                                @foreach ($leaveCustomFields as $field)
                                    @php
                                        $fieldKey = (string) ($field['key'] ?? '');
                                        $value = data_get($leave->form_meta ?? [], $fieldKey);
                                    @endphp
                                    <td class="px-5 py-3 text-slate-600">{{ filled((string) $value) ? $value : '—' }}</td>
                                @endforeach
                                <td class="px-5 py-3">
                                    @if ($leave->status === 'approved')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Approved</span>
                                    @elseif ($leave->status === 'rejected')
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100">Rejected</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 border border-amber-100">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 7 + count($leaveCustomFields) }}" class="px-5 py-12 text-center text-slate-500">
                                    No leave records. Add employees first, then create a <a href="{{ route('loan.employees.leaves.create') }}" class="text-indigo-600 font-medium hover:underline">leave request</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    </table>
                </div>
            @if ($leaves->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $leaves->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
