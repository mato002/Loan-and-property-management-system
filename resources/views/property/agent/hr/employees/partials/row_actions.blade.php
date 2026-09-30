@php
    $showUrl = $showUrl ?? route('property.hr.employees.show', $employee);
    $editUrl = $editUrl ?? route('property.hr.employees.edit', $employee);
    $canManage = auth()->check() && auth()->user()?->hasPmPermission('properties.manage');
    $hasLogin = (bool) ($employee->user_id ?? false);
    $hasEmail = trim((string) ($employee->email ?? '')) !== '';
    $status = $employee->employmentStatusKey();
@endphp
<x-property.action-menu width="w-56">
    <a href="{{ $showUrl }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">View</a>
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
        @if ($hasEmail && $status !== 'terminated')
            <form method="post" action="{{ route('property.hr.employees.send_login', $employee) }}" data-turbo-frame="_top" data-swal-title="{{ $hasLogin ? 'Reset and email login?' : 'Generate and email login?' }}" data-swal-confirm="A temporary password will be emailed to {{ $employee->email }}." data-swal-confirm-text="Yes, send login">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">{{ $hasLogin ? 'Reset & email login' : 'Generate & email login' }}</button>
            </form>
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
