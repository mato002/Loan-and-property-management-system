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
            ['label' => 'Status', 'value' => $employee->employmentStatusLabel(), 'hint' => 'Employment'],
        ];
@endphp

<x-property.workspace
    :title="'Employee: '.$employee->full_name"
    subtitle="HR profile{{ ($isFieldOfficer ?? false) ? ' and property portfolio' : '' }}."
    back-route="property.hr.employees.index"
    :stats="$headerStats"
    :columns="[]"
>
    @php
        $loginState = $loginState ?? [
            'has_email' => trim((string) ($employee->email ?? '')) !== '',
            'has_login' => (bool) ($employee->user_id ?? false),
            'login_emailed' => false,
            'can_send_login' => trim((string) ($employee->email ?? '')) !== '' && ! $employee->isOffboarded(),
            'login_action' => null,
            'login_action_label' => null,
        ];
        if (($loginState['login_action'] ?? null) === null && ($loginState['can_send_login'] ?? false)) {
            $loginState['login_action'] = ! empty($loginState['has_login']) || ! empty($loginState['login_emailed']) ? 'resend' : 'send';
            $loginState['login_action_label'] = $loginState['login_action'] === 'resend' ? 'Resend logins' : 'Send logins';
        }
        $loginAction = $loginState['login_action'] ?? null;
        $loginLabel = $loginState['login_action_label'] ?? null;
    @endphp
    <x-slot name="actions">
        @if ($canManage ?? false)
            <a href="{{ route('property.hr.employees.edit', $employee, false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Edit employee</a>
            @if (($loginState['can_send_login'] ?? false) && $loginLabel)
                <form method="post" action="{{ route('property.hr.employees.send_login', $employee) }}" data-turbo-frame="_top" data-swal-title="{{ $loginAction === 'resend' ? 'Resend login credentials?' : 'Send login credentials?' }}" data-swal-confirm="A temporary password will be emailed to {{ $employee->email }}." data-swal-confirm-text="{{ $loginAction === 'resend' ? 'Yes, resend' : 'Yes, send logins' }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">{{ $loginLabel }}</button>
                </form>
            @endif
            @if (! $employee->isOffboarded())
            <a href="{{ route('property.hr.leaves.create', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Request leave</a>
            @endif
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
            Copy this now if email is delayed. They should change the password after first sign-in.
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
        @elseif (($activeTab ?? 'overview') === 'lifecycle')
            @include('property.agent.hr.employees.partials.lifecycle')
        @elseif (($activeTab ?? 'overview') === 'offboard')
            @include('property.agent.hr.employees.partials.tab-offboard')
        @elseif (($activeTab ?? 'overview') === 'access')
            @include('property.agent.hr.employees.partials.tab-access')
        @elseif (($activeTab ?? 'overview') === 'permissions')
            @include('property.agent.hr.employees.partials.tab-permissions')
        @elseif (($activeTab ?? 'overview') === 'leave')
            @include('property.agent.hr.employees.partials.tab-leave')
        @else
            @include('property.agent.hr.employees.partials.tab-overview')
        @endif
    </div>
</x-property.workspace>
