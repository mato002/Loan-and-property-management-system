<x-loan-layout>
    <x-loan.page title="Salary advances" subtitle="Staff advance requests: approve before payroll, settle when recovered.">
        <x-slot name="actions">
            <a href="{{ route('loan.system.form_setup.salary_advance') }}" class="inline-flex items-center justify-center rounded-lg border-2 border-indigo-500 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50 transition-colors">Form setup</a>
            @include('loan.accounting.partials.export_buttons')
            <a href="{{ route('loan.accounting.advances.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">New request</a>
        </x-slot>
        @include('loan.accounting.partials.flash')
        @error('status')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
        @error('delete')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror

        <form method="get" class="mb-4">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Status</label>
                    <select name="status" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>pending</option>
                        <option value="approved" @selected(($status ?? '') === 'approved')>approved</option>
                        <option value="rejected" @selected(($status ?? '') === 'rejected')>rejected</option>
                        <option value="settled" @selected(($status ?? '') === 'settled')>settled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">Employee</label>
                    <select name="employee_id" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                        <option value="">All</option>
                        @foreach(($employees ?? []) as $emp)
                            <option value="{{ $emp->id }}" @selected((int) ($employeeId ?? 0) === (int) $emp->id)>
                                {{ trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')) }} ({{ $emp->employee_number ?? '—' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm focus:border-[#2f4f4f] focus:ring-2 focus:ring-[#2f4f4f]/20">
                </div>

                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.accounting.advances.index') }}" class="h-10 inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Requested</th>
                            <th class="px-5 py-3">Reason</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3 text-right">Approved</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-slate-900">{{ $r->employee->full_name }}</span>
                                    <span class="block text-xs text-slate-500 font-mono">{{ $r->employee->employee_number }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ $r->requested_on->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 text-slate-600 max-w-xs truncate">{{ $r->reason_for_request ?: '—' }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium">{{ $r->currency }} {{ number_format((float) $r->amount, 2) }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">
                                    @if ($r->status === \App\Models\AccountingSalaryAdvance::STATUS_APPROVED || $r->status === \App\Models\AccountingSalaryAdvance::STATUS_SETTLED)
                                        <div class="font-medium text-slate-800">{{ $r->currency }} {{ number_format((float) ($r->approved_amount ?? $r->amount), 2) }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $r->approvedByUser?->name ?? '—' }}{{ $r->approved_at ? ' · '.$r->approved_at->format('Y-m-d H:i') : '' }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 capitalize text-slate-600">{{ $r->status }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($r->status === \App\Models\AccountingSalaryAdvance::STATUS_PENDING)
                                        <form method="post" action="{{ route('loan.accounting.advances.approve', $r) }}" class="inline mr-1">
                                            @csrf
                                            <input type="hidden" name="approved_amount" value="{{ (float) $r->amount }}">
                                            <button type="submit" class="text-[#2f4f4f] font-medium text-xs">Approve</button>
                                        </form>
                                        <form method="post" action="{{ route('loan.accounting.advances.reject', $r) }}" class="inline mr-2">
                                            @csrf
                                            <button type="submit" class="text-amber-700 font-medium text-xs">Reject</button>
                                        </form>
                                    @endif
                                    @if ($r->status === \App\Models\AccountingSalaryAdvance::STATUS_APPROVED)
                                        <form method="post" action="{{ route('loan.accounting.advances.settle', $r) }}" class="inline mr-2">
                                            @csrf
                                            <button type="submit" class="text-indigo-600 font-medium text-xs">Settle</button>
                                        </form>
                                    @endif
                                    @if ($r->status !== \App\Models\AccountingSalaryAdvance::STATUS_SETTLED)
                                        <a href="{{ route('loan.accounting.advances.edit', $r) }}" class="text-indigo-600 font-medium text-sm mr-2">Edit</a>
                                    @endif
                                    <form method="post" action="{{ route('loan.accounting.advances.destroy', $r) }}" class="inline" data-swal-confirm="Remove this advance record?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">No salary advances. Add employees first if the list is empty.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rows->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">{{ $rows->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
