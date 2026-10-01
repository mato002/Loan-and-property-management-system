<div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm max-w-3xl">
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Portal and property roles</h3>
    <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
        @if ($employee->user)
            <p><span class="text-slate-500">Login email:</span> {{ $employee->user->email }}</p>
            <p><span class="text-slate-500">Roles:</span> {{ $employee->user->pmRoles->pluck('name')->join(', ') ?: '—' }}</p>
            <p class="text-xs text-slate-500">They sign in at the main staff login, then open the property workspace. Assigned roles control what they can do.</p>
            @if (($canManage ?? false) && ! $employee->isOffboarded())
                <div class="flex flex-wrap gap-2 pt-2">
                    @if (($loginState['can_send_login'] ?? false) && ($loginLabel ?? null))
                        <form method="post" action="{{ route('property.hr.employees.send_login', $employee) }}" data-turbo-frame="_top">
                            @csrf
                            <button type="submit" class="rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-50">{{ $loginLabel }}</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('property.hr.employees.revoke_login', $employee) }}" data-turbo-frame="_top" data-swal-title="Revoke portal access?" data-swal-confirm="They will not be able to sign in until you restore access." data-swal-confirm-text="Revoke">
                        @csrf
                        <button type="submit" class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-50">Revoke access</button>
                    </form>
                    <form method="post" action="{{ route('property.hr.employees.restore_login', $employee) }}" data-turbo-frame="_top">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Restore access</button>
                    </form>
                </div>
            @endif
        @else
            <p class="text-slate-500">No portal login yet. Add a work email, then use <strong>Send logins</strong>.</p>
            @if (($canManage ?? false) && ($loginState['can_send_login'] ?? false) && ($loginLabel ?? null))
                <form method="post" action="{{ route('property.hr.employees.send_login', $employee) }}" data-turbo-frame="_top" class="pt-2">
                    @csrf
                    <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">{{ $loginLabel }}</button>
                </form>
            @endif
        @endif
    </div>
</div>
