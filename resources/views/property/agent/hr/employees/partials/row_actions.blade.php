@php
    $showUrl = $showUrl ?? route('property.hr.employees.show', $employee);
    $editUrl = $editUrl ?? route('property.hr.employees.edit', $employee);
    $canManage = $canManage ?? (auth()->check() && (
        (auth()->user()?->is_super_admin ?? false) === true
        || auth()->user()?->hasPmPermission('properties.manage')
    ));
    $loginState = $loginState ?? [
        'has_email' => trim((string) ($employee->email ?? '')) !== '',
        'has_login' => (bool) ($employee->user_id ?? false),
        'login_emailed' => false,
        'can_send_login' => trim((string) ($employee->email ?? '')) !== '' && $employee->employmentStatusKey() !== 'terminated',
        'login_action' => null,
        'login_action_label' => null,
    ];
    if (($loginState['login_action'] ?? null) === null && ($loginState['can_send_login'] ?? false)) {
        $loginState['login_action'] = ! empty($loginState['has_login']) || ! empty($loginState['login_emailed']) ? 'resend' : 'send';
        $loginState['login_action_label'] = $loginState['login_action'] === 'resend' ? 'Resend logins' : 'Send logins';
    }
    $status = $employee->employmentStatusKey();
    $hasLogin = (bool) ($loginState['has_login'] ?? false);
@endphp
<x-property.action-menu width="w-60">
    <a href="{{ $showUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View</a>

    @if ($canManage && ($loginState['can_send_login'] ?? false) && ($loginState['login_action_label'] ?? null))
        <form method="post" action="{{ route('property.hr.employees.send_login', $employee) }}" data-turbo-frame="_top" data-swal-title="{{ ($loginState['login_action'] ?? '') === 'resend' ? 'Resend login credentials?' : 'Send login credentials?' }}" data-swal-confirm="A temporary password will be emailed to {{ $employee->email }}." data-swal-confirm-text="{{ ($loginState['login_action'] ?? '') === 'resend' ? 'Yes, resend' : 'Yes, send logins' }}">
            @csrf
            <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">{{ $loginState['login_action_label'] }}</button>
        </form>
    @endif

    @if ($canManage)
        <a href="{{ $editUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Edit</a>
    @endif

    @if (! empty($isFieldOfficer) && ! empty($portfolioUrl))
        <a href="{{ $portfolioUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Portfolio</a>
    @endif

    @if ($canManage)
        @if ($status !== 'terminated')
            <a href="{{ route('property.hr.leaves.create', ['employee_id' => $employee->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Request leave</a>
        @endif
        @if ($hasLogin && $status !== 'terminated')
            <form method="post" action="{{ route('property.hr.employees.revoke_login', $employee) }}" data-turbo-frame="_top" data-swal-title="Revoke portal access?" data-swal-confirm="They will not be able to sign in to the property workspace until you restore access." data-swal-confirm-text="Revoke access">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-amber-800 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-slate-700/50">Revoke portal access</button>
            </form>
        @endif
        @if ($status === 'onboarding')
            <form method="post" action="{{ route('property.hr.employees.complete_onboarding', $employee) }}" data-turbo-frame="_top" data-swal-title="Complete onboarding?" data-swal-confirm="This marks {{ $employee->full_name }} as active staff." data-swal-confirm-text="Activate">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Complete onboarding</button>
            </form>
        @endif
        @if ($status === 'on_leave')
            <form method="post" action="{{ route('property.hr.employees.status', $employee) }}" data-turbo-frame="_top">
                @csrf
                <input type="hidden" name="employment_status" value="active" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Return from leave</button>
            </form>
        @endif
        @if (in_array($status, ['onboarding', 'active', 'on_leave'], true))
            <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id], false) }}#hr-offboard" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">Offboard…</a>
        @else
            <form method="post" action="{{ route('property.hr.employees.status', $employee) }}" data-turbo-frame="_top" data-swal-title="Re-activate employee?" data-swal-confirm="This restores them as active staff. Portal access is not restored automatically." data-swal-confirm-text="Re-activate">
                @csrf
                <input type="hidden" name="employment_status" value="active" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Re-activate</button>
            </form>
        @endif
    @endif
</x-property.action-menu>
