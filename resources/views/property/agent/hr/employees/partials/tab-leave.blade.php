<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm max-w-3xl">
    <div class="flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Leave</h3>
        <div class="flex items-center gap-3">
            @if (($canManage ?? false) && ! $employee->isOffboarded())
                <a href="{{ route('property.hr.leaves.create', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">Request leave</a>
            @endif
            <a href="{{ route('property.hr.leaves.index', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">View all</a>
        </div>
    </div>
    <div class="mt-3 space-y-2 text-sm">
        @forelse ($recentLeaves as $leave)
            <div class="rounded-lg border border-slate-200 dark:border-slate-700 px-3 py-2">
                <div class="font-medium text-slate-900 dark:text-white">{{ $leave->leave_type }}</div>
                <div class="text-xs text-slate-500">{{ $leave->start_date?->format('Y-m-d') }} → {{ $leave->end_date?->format('Y-m-d') }} · {{ ucfirst((string) $leave->status) }}</div>
            </div>
        @empty
            <p class="text-slate-500">No leave requests recorded.</p>
        @endforelse
    </div>
</div>
