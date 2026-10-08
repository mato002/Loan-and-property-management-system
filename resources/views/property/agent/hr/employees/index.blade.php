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
    table-min-width="1100px"
    empty-title="{{ ($isFieldOfficerList ?? false) ? 'No field officers yet' : 'No employees yet' }}"
    empty-hint="{{ ($isFieldOfficerList ?? false) ? 'Add an employee and enable the field officer role, then assign properties from their portfolio tab.' : 'Add staff here first. Mark field officers to link them to the property portfolio workspace.' }}"
>
    <x-slot name="actions">
        @if (auth()->check() && (auth()->user()?->hasPmPermission('team.users.manage') || auth()->user()?->hasPmPermission('properties.manage')))
            <a href="{{ route('property.hr.employees.create', ($isFieldOfficerList ?? false) ? ['field_officer' => 1, 'job_title' => 'Field Officer'] : [], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">{{ ($isFieldOfficerList ?? false) ? 'Add field officer' : 'Add employee' }}</a>
        @endif
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif
    @if (is_array(session('hr_user_created')))
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
            Portal login for <strong>{{ session('hr_user_created.name') }}</strong>:
            email <code>{{ session('hr_user_created.email') }}</code>,
            temporary password <code>{{ session('hr_user_created.temporary_password') }}</code>.
            @if (! empty(session('hr_user_created.login_url')))
                Sign-in: <a href="{{ session('hr_user_created.login_url') }}" class="underline font-semibold" target="_blank" rel="noopener">{{ session('hr_user_created.login_url') }}</a>.
            @endif
            Copy this now if email delivery is delayed. They should change the password after first sign-in.
        </div>
    @endif

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.hr_employees')
    </x-slot>
</x-property.workspace>
