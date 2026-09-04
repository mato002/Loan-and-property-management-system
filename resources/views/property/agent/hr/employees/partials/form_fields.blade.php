@php
    $employeeModel = $employee ?? null;
    $isEdit = $employeeModel !== null;
    $isFieldOfficer = old('is_field_officer', $isFieldOfficer ?? $defaultIsFieldOfficer ?? false);
@endphp

<div>
    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Employee details</h3>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Add staff once in HR. Field officers are linked automatically when you enable the field officer role.</p>
</div>

@if (($agents ?? []) !== [])
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Agent workspace</label>
        <select name="agent_user_id" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            @foreach ($agents as $agent)
                <option value="{{ $agent['id'] }}" @selected((int) old('agent_user_id', $employeeModel->agent_user_id ?? $defaultAgentUserId ?? 0) === (int) $agent['id'])>{{ $agent['name'] }}</option>
            @endforeach
        </select>
        @error('agent_user_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
@endif

<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employee number</label>
        <input type="text" name="employee_number" value="{{ old('employee_number', $employeeModel->employee_number ?? $suggestedEmployeeNumber ?? '') }}" readonly class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/60 text-sm px-3 py-2" />
        @error('employee_number')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">First name</label>
        <input type="text" name="first_name" value="{{ old('first_name', $employeeModel->first_name ?? '') }}" required autofocus class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('first_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Last name</label>
        <input type="text" name="last_name" value="{{ old('last_name', $employeeModel->last_name ?? '') }}" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('last_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Work email</label>
        <input type="email" name="email" value="{{ old('email', $employeeModel->email ?? '') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $employeeModel->phone ?? '') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Department</label>
        <select name="department" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="">Select department</option>
            @foreach ($departments as $department)
                <option value="{{ $department }}" @selected(old('department', $employeeModel->department ?? '') === $department)>{{ $department }}</option>
            @endforeach
        </select>
        @error('department')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Job title</label>
        <select name="job_title" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            <option value="">Select job title</option>
            @foreach ($jobTitles as $title)
                <option value="{{ $title }}" @selected(old('job_title', $employeeModel->job_title ?? $defaultJobTitle ?? '') === $title)>{{ $title }}</option>
            @endforeach
        </select>
        @error('job_title')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Employment status</label>
        <select name="employment_status" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
            @foreach (['active' => 'Active', 'on_leave' => 'On leave', 'terminated' => 'Terminated'] as $value => $label)
                <option value="{{ $value }}" @selected(old('employment_status', $employeeModel->employment_status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('employment_status')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Hire date</label>
        <input type="date" name="hire_date" value="{{ old('hire_date', optional($employeeModel?->hire_date)->format('Y-m-d')) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('hire_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">National ID</label>
        <input type="text" name="national_id" value="{{ old('national_id', $employeeModel->national_id ?? '') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        @error('national_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 space-y-3">
    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Field officer portfolio</h4>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="is_field_officer" value="1" @checked($isFieldOfficer) class="rounded border-slate-300" />
        Field officer (assign properties from the employee portfolio tab)
    </label>
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="portal_access" value="1" @checked(old('portal_access', $employeeModel?->fieldOfficerProfile?->portal_access ?? false)) class="rounded border-slate-300" />
        Portal access (future — officer can sign in when enabled)
    </label>
</div>

@if ($rolesReady ?? false)
    @php
        $oldRoleIds = old('role_ids');
        $selectedRoleId = 0;
        if (is_array($oldRoleIds) && $oldRoleIds !== []) {
            $selectedRoleId = (int) ($oldRoleIds[0] ?? 0);
        } elseif ($oldRoleIds !== null && $oldRoleIds !== '') {
            $selectedRoleId = (int) $oldRoleIds;
        } elseif (! empty($linkedRoleIds ?? [])) {
            $selectedRoleId = (int) ($linkedRoleIds[0] ?? 0);
        }
    @endphp
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4 space-y-3">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Property portal login</h4>
        @if ($employeeModel?->user_id)
            <p class="text-sm text-emerald-700 dark:text-emerald-300">Linked login: {{ $employeeModel->user?->email ?? $employeeModel->email }}</p>
            <p class="text-xs text-slate-500">Update the property role below. Password reset is handled separately under Settings → Users.</p>
        @else
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="provision_login" value="1" @checked(old('provision_login')) class="rounded border-slate-300" />
                Create property portal login (requires work email)
            </label>
            @error('provision_login')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        @endif
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Property role</label>
            <select name="role_ids[]" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="">Select property role…</option>
                @foreach ($propertyRoles as $role)
                    <option value="{{ $role->id }}" @selected($selectedRoleId === (int) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('role_ids')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
@endif
