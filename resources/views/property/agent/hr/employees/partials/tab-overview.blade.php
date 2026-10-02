<div class="grid gap-4 lg:grid-cols-2">
    <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Profile</h3>
        <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
            <p><span class="text-slate-500">Company:</span> {{ $employee->agentUser?->name ?: '—' }}</p>
            <p><span class="text-slate-500">Name:</span> {{ $employee->full_name }}</p>
            <p><span class="text-slate-500">Email:</span> {{ $employee->email ?: '—' }}</p>
            <p><span class="text-slate-500">Phone:</span> <x-phone-link :value="$employee->phone" /></p>
            <p><span class="text-slate-500">National ID:</span> {{ $employee->national_id ?: '—' }}</p>
            <p><span class="text-slate-500">Hire date:</span> {{ $employee->hire_date?->format('Y-m-d') ?? '—' }}</p>
            <p><span class="text-slate-500">Work type:</span> {{ $employee->work_type ? str_replace('_', ' ', $employee->work_type) : '—' }}</p>
            <p><span class="text-slate-500">Next of kin:</span> {{ $employee->next_of_kin_name ?: '—' }} @if($employee->next_of_kin_phone) ({{ $employee->next_of_kin_phone }}) @endif</p>
            <p><span class="text-slate-500">Bank:</span> {{ $employee->bank_name ?: '—' }} {{ $employee->bank_account_number ? '· '.$employee->bank_account_number : '' }}</p>
            <p><span class="text-slate-500">KRA / NHIF / NSSF:</span> {{ $employee->kra_pin ?: '—' }} · {{ $employee->nhif_number ?: '—' }} · {{ $employee->nssf_number ?: '—' }}</p>
            @if ($employee->supervisor)
                <p><span class="text-slate-500">Supervisor:</span> {{ $employee->supervisor->full_name }}</p>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Where to go next</h3>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
                <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'lifecycle'], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    <span class="block font-medium text-slate-900 dark:text-white">Lifecycle</span>
                    <span class="text-xs text-slate-500">{{ $employee->employmentStatusLabel() }}</span>
                </a>
                <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'access'], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    <span class="block font-medium text-slate-900 dark:text-white">Access</span>
                    <span class="text-xs text-slate-500">{{ $employee->user ? 'Portal login on file' : 'No portal login yet' }}</span>
                </a>
                <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'permissions'], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    <span class="block font-medium text-slate-900 dark:text-white">Role &amp; permissions</span>
                    <span class="text-xs text-slate-500">{{ $employee->user?->pmRoles?->pluck('name')->join(', ') ?: 'Allow or deny' }}</span>
                </a>
                <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'leave'], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    <span class="block font-medium text-slate-900 dark:text-white">Leave</span>
                    <span class="text-xs text-slate-500">{{ ($recentLeaves ?? collect())->count() }} recent request{{ ($recentLeaves ?? collect())->count() === 1 ? '' : 's' }}</span>
                </a>
                <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'offboard'], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                    <span class="block font-medium text-slate-900 dark:text-white">Offboard</span>
                    <span class="text-xs text-slate-500">{{ $employee->isOffboarded() ? 'Exit recorded' : 'Still employed' }}</span>
                </a>
            </div>
        </div>

        @if ($isFieldOfficer ?? false)
            <div class="property-compact-panel rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Field officer portfolio</h3>
                    <a href="{{ route('property.hr.employees.show', ['employee' => $employee->id, 'tab' => 'portfolio'], false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-blue-600 hover:underline">Open portfolio</a>
                </div>
                <div class="mt-3 text-sm text-slate-700 dark:text-slate-200 space-y-2">
                    <p><span class="text-slate-500">Properties:</span> {{ (int) ($portfolioStats['properties'] ?? 0) }}</p>
                    <p><span class="text-slate-500">Units:</span> {{ (int) ($portfolioStats['units'] ?? 0) }}</p>
                    <p><span class="text-slate-500">Rent portfolio:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($portfolioStats['rent_portfolio'] ?? 0)) }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
