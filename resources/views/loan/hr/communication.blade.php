<x-loan.hr.shell
    :section-meta="$sectionMeta"
    :section-key="$sectionKey"
    :hr-sections="$hrSections"
    :workspace-tabs="$workspaceTabs"
    :search-commands="$searchCommands"
    :focus-modes="$focusModes"
>
    <div class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Recent leave decisions</h3>
            <div class="mt-3 space-y-2">
                @forelse ($recentLeaves as $leave)
                    <div class="rounded-lg border border-slate-100 px-3 py-2 text-sm">
                        <p class="font-medium text-slate-800">{{ $leave->employee?->full_name ?? 'Employee' }}</p>
                        <p class="text-xs text-slate-500">{{ ucfirst((string) $leave->status) }} · {{ ucfirst((string) $leave->leave_type) }}</p>
                    </div>
                @empty
                    <x-loan.empty-state
                        title="No recent leave activity"
                        description="Leave decisions and attendance updates will appear here for quick review."
                        :action-label="Route::has('loan.employees.leaves') ? 'Manage Leave' : null"
                        :action-href="Route::has('loan.employees.leaves') ? route('loan.employees.leaves') : null"
                    />
                @endforelse
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">System activity log</h3>
            <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                @forelse ($activityLogs as $log)
                    <div class="rounded-lg border border-slate-100 px-3 py-2 text-xs">
                        <p class="font-medium text-slate-800">{{ $log->user?->name ?? 'System' }}</p>
                        <p class="text-slate-600">{{ $log->activity ?? $log->action_type ?? 'Activity' }}</p>
                        <p class="text-slate-400">{{ optional($log->created_at)->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No activity logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-loan.hr.shell>
