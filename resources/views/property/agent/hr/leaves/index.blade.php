<x-property.workspace
    title="Staff leave"
    subtitle="Leave requests for property employees."
    back-route="property.hr.employees.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    :column-config="$columnConfig"
    :responsive-cards="false"
    :legacy-toolbar="false"
    :show-search="false"
    table-min-width="880px"
    empty-title="No leave requests yet"
    empty-hint="Submit leave for an employee from HR → Leaves."
>
    <x-slot name="actions">
        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <a href="{{ route('property.hr.leaves.create', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">New leave request</a>
        @endif
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.hr.leaves.index', absolute: false) }}" class="flex flex-wrap items-end gap-2">
            <div class="min-w-[12rem] flex-1">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Search</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Employee name or number…" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employee</label>
                <select name="employee_id" class="mt-1 min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">All</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) ($filters['employee_id'] ?? 0) === (int) $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                <select name="status" class="mt-1 min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">All</option>
                    @foreach (['pending', 'approved', 'rejected'] as $rowStatus)
                        <option value="{{ $rowStatus }}" @selected(($filters['status'] ?? '') === $rowStatus)>{{ ucfirst($rowStatus) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ route('property.hr.leaves.index', absolute: false) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>
</x-property.workspace>
