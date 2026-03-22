<x-loan-layout>
    <x-loan.page
        title="Staff leaves"
        subtitle="Submit leave requests and update approval status."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.leaves.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                New leave request
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">All requests</h2>
                <p class="text-xs text-slate-500">{{ $leaves->total() }} record(s)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">From</th>
                            <th class="px-5 py-3">To</th>
                            <th class="px-5 py-3">Days</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($leaves as $leave)
                            <tr class="hover:bg-slate-50/80">
                                <td class="px-5 py-3 font-medium text-slate-900">{{ $leave->employee->full_name }}</td>
                                <td class="px-5 py-3 text-slate-600">{{ $leave->leave_type }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $leave->start_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $leave->end_date->format('Y-m-d') }}</td>
                                <td class="px-5 py-3 text-slate-600 tabular-nums">{{ $leave->days }}</td>
                                <td class="px-5 py-3">
                                    @if ($leave->status === 'approved')
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 border border-emerald-100">Approved</span>
                                    @elseif ($leave->status === 'rejected')
                                        <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-800 border border-red-100">Rejected</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 border border-amber-100">Pending</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($leave->status === 'pending')
                                        <div class="flex flex-wrap justify-end gap-2">
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
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
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
