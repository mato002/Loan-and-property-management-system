<x-property.workspace
    title="{{ ($isFieldOfficerList ?? false) ? 'Field officers' : 'Employees' }}"
    subtitle="{{ ($isFieldOfficerList ?? false) ? 'Employees with a field officer role and their assigned property portfolios.' : 'HR directory for property staff — field officers, leasing, maintenance, finance, and admin roles.' }}"
    back-route="property.hr.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$tableRowFilters"
    :column-config="$columnConfig"
    :responsive-cards="false"
    :legacy-toolbar="false"
    :show-search="false"
    table-min-width="960px"
    empty-title="{{ ($isFieldOfficerList ?? false) ? 'No field officers yet' : 'No employees yet' }}"
    empty-hint="{{ ($isFieldOfficerList ?? false) ? 'Add an employee and enable the field officer role, then assign properties from their portfolio tab.' : 'Add staff here first. Mark field officers to link them to the property portfolio workspace.' }}"
>
    <x-slot name="actions">
        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <a href="{{ route('property.hr.employees.create', ($isFieldOfficerList ?? false) ? ['field_officer' => 1, 'job_title' => 'Field Officer'] : [], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">{{ ($isFieldOfficerList ?? false) ? 'Add field officer' : 'Add employee' }}</a>
        @endif
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (is_array(session('hr_user_created')))
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
            Portal login for <strong>{{ session('hr_user_created.name') }}</strong>:
            email <code>{{ session('hr_user_created.email') }}</code>,
            temporary password <code>{{ session('hr_user_created.temporary_password') }}</code>.
            Share securely and ask them to change it after first sign-in.
        </div>
    @endif

    <x-slot name="tabs">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('property.hr.employees.index', absolute: false) }}" @class([
                'inline-flex min-h-[40px] items-center rounded-lg border px-3 py-2 text-xs font-medium',
                (($filters['role_type'] ?? 'all') === 'all') ? 'border-indigo-300 bg-indigo-50 text-indigo-800' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
            ])>All employees</a>
            <a href="{{ route('property.hr.employees.index', array_merge((array) ($filters ?? []), ['role_type' => 'field_officer']), absolute: false) }}" @class([
                'inline-flex min-h-[40px] items-center rounded-lg border px-3 py-2 text-xs font-medium',
                ($filters['role_type'] ?? 'all') === 'field_officer' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
            ])>Field officers</a>
            @if (($filters['role_type'] ?? 'all') === 'field_officer')
                <a href="{{ route('property.hr.employees.index', array_merge((array) ($filters ?? []), ['portfolio' => 'all']), absolute: false) }}" @class([
                    'inline-flex min-h-[40px] items-center rounded-lg border px-3 py-2 text-xs font-medium',
                    (($filters['portfolio'] ?? 'all') === 'all') ? 'border-slate-400 bg-slate-100 text-slate-800' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
                ])>All portfolios</a>
                <a href="{{ route('property.hr.employees.index', array_merge((array) ($filters ?? []), ['portfolio' => 'assigned']), absolute: false) }}" @class([
                    'inline-flex min-h-[40px] items-center rounded-lg border px-3 py-2 text-xs font-medium',
                    ($filters['portfolio'] ?? 'all') === 'assigned' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
                ])>With properties</a>
                <a href="{{ route('property.hr.employees.index', array_merge((array) ($filters ?? []), ['portfolio' => 'unassigned']), absolute: false) }}" @class([
                    'inline-flex min-h-[40px] items-center rounded-lg border px-3 py-2 text-xs font-medium',
                    ($filters['portfolio'] ?? 'all') === 'unassigned' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50',
                ])>No properties yet</a>
            @endif
        </div>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.hr_employees')
    </x-slot>
</x-property.workspace>
