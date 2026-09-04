@php
    $headerStats = ($isFieldOfficer ?? false) && ($activeTab ?? 'overview') === 'portfolio'
        ? [
            ['label' => 'Properties', 'value' => (string) ($portfolioStats['properties'] ?? 0), 'hint' => 'Assigned'],
            ['label' => 'Landlords', 'value' => (string) ($portfolioStats['landlords'] ?? 0), 'hint' => 'Across portfolio'],
            ['label' => 'Units', 'value' => (string) ($portfolioStats['units'] ?? 0), 'hint' => 'Total units'],
            ['label' => 'Active tenants', 'value' => (string) ($portfolioStats['tenants'] ?? 0), 'hint' => 'On active leases'],
            ['label' => 'Rent portfolio', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($portfolioStats['rent_portfolio'] ?? 0)), 'hint' => 'Active lease rent'],
        ]
        : [
            ['label' => 'Employee #', 'value' => $employee->employee_number, 'hint' => 'HR record'],
            ['label' => 'Department', 'value' => (string) ($employee->department ?: '—'), 'hint' => 'Current'],
            ['label' => 'Job title', 'value' => (string) ($employee->job_title ?: '—'), 'hint' => 'Current'],
            ['label' => 'Status', 'value' => ucfirst((string) ($employee->employment_status ?: 'active')), 'hint' => 'Employment'],
        ];
@endphp

<x-property.workspace
    :title="'Employee: '.$employee->full_name"
    subtitle="HR profile{{ ($isFieldOfficer ?? false) ? ' and property portfolio' : '' }}."
    back-route="property.hr.employees.index"
    :stats="$headerStats"
    :columns="[]"
>
    <x-slot name="actions">
        @if (auth()->check() && auth()->user()?->hasPmPermission('properties.manage'))
            <a href="{{ route('property.hr.employees.edit', $employee, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Edit employee</a>
            <a href="{{ route('property.hr.leaves.create', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Request leave</a>
        @endif
        <a href="{{ route('property.accounting.payroll', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Payroll</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif
    @if (is_array(session('hr_user_created')))
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
            Portal login: email <code>{{ session('hr_user_created.email') }}</code>,
            temporary password <code>{{ session('hr_user_created.temporary_password') }}</code>.
        </div>
    @endif

    @if (($employeeTabs ?? []) !== [])
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/50 shadow-sm overflow-hidden mb-4 sm:mb-5">
            <nav class="flex gap-1 overflow-x-auto custom-scrollbar px-2 py-2 snap-x snap-mandatory" aria-label="Employee sections">
                @foreach ($employeeTabs as $tab)
                    <a
                        href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => $tab['key']], false) }}"
                        data-turbo-frame="property-main"
                        @if (($activeTab ?? 'overview') === $tab['key']) aria-current="page" @endif
                        class="snap-start shrink-0 inline-flex items-center rounded-lg px-3 py-2 text-xs sm:text-sm font-semibold min-h-[40px] border border-transparent text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 aria-[current=page]:bg-indigo-600 aria-[current=page]:text-white aria-[current=page]:shadow-sm"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="space-y-4 sm:space-y-5 w-full min-w-0">
        @if (($activeTab ?? 'overview') === 'portfolio')
            @include('property.agent.hr.employees.partials.tab-portfolio')
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profile</h3>
                    <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
                        <p><span class="text-slate-500">Name:</span> {{ $employee->full_name }}</p>
                        <p><span class="text-slate-500">Email:</span> {{ $employee->email ?: '—' }}</p>
                        <p><span class="text-slate-500">Phone:</span> {{ $employee->phone ?: '—' }}</p>
                        <p><span class="text-slate-500">National ID:</span> {{ $employee->national_id ?: '—' }}</p>
                        <p><span class="text-slate-500">Hire date:</span> {{ $employee->hire_date?->format('Y-m-d') ?? '—' }}</p>
                        @if ($employee->supervisor)
                            <p><span class="text-slate-500">Supervisor:</span> {{ $employee->supervisor->full_name }}</p>
                        @endif
                    </div>
                </div>

                <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Portal & property roles</h3>
                    <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
                        @if ($employee->user)
                            <p><span class="text-slate-500">Login email:</span> {{ $employee->user->email }}</p>
                            <p><span class="text-slate-500">Roles:</span> {{ $employee->user->pmRoles->pluck('name')->join(', ') ?: '—' }}</p>
                        @else
                            <p class="text-slate-500">No portal login yet. Edit employee and enable portal login with property roles.</p>
                        @endif
                    </div>
                </div>

                @if ($isFieldOfficer ?? false)
                    <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Field officer portfolio</h3>
                            <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">Manage portfolio</a>
                        </div>
                        <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
                            <p><span class="text-slate-500">Properties:</span> {{ (int) ($portfolioStats['properties'] ?? 0) }}</p>
                            <p><span class="text-slate-500">Units:</span> {{ (int) ($portfolioStats['units'] ?? 0) }}</p>
                            <p><span class="text-slate-500">Rent portfolio:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($portfolioStats['rent_portfolio'] ?? 0)) }}</p>
                        </div>
                    </div>
                @endif

                <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recent leave</h3>
                        <a href="{{ route('property.hr.leaves.index', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">View all</a>
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
            </div>
        @endif
    </div>
</x-property.workspace>
