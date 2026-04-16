<x-loan-layout>
    <x-loan.page
        title="Add employee"
        subtitle="Capture core HR fields and provision a system login when needed."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        @php
            $roleDeptMap = $roleDepartmentMap ?? [];
            $roleTitleMap = $roleJobTitleMap ?? [];
            $departmentOptions = $departmentNames ?? collect();
            $jobTitleOptions = $jobTitleOptions ?? collect();
            $branchOptions = $branches ?? collect();
            $regionOptions = $regions ?? collect();
            $selectedBranchName = old('branch');
        @endphp

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-4xl"
             x-data="{ addDeptOpen: false, addBranchOpen: false, roleDeptMap: @js($roleDeptMap), roleTitleMap: @js($roleTitleMap) }">
            <form method="post" action="{{ route('loan.employees.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="employee_number" value="Employee number" />
                        <x-text-input
                            id="employee_number"
                            name="employee_number"
                            type="text"
                            class="mt-1 block w-full bg-slate-50"
                            :value="old('employee_number', $suggestedEmployeeNumber ?? '')"
                            readonly
                            autocomplete="off"
                        />
                        <p class="mt-1 text-xs text-slate-500">Auto-assigned by the system.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_number')" />
                    </div>
                    <div>
                        <x-input-label for="first_name" value="First name" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required autocomplete="given-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Last name" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required autocomplete="family-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Work email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" autocomplete="email" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" autocomplete="tel" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <x-input-label for="department" value="Department" />
                            <button type="button"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                                    @click="addDeptOpen = true">
                                + Add department
                            </button>
                        </div>
                        <select id="department" name="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select department</option>
                            @foreach ($departmentOptions as $departmentName)
                                <option value="{{ $departmentName }}" @selected(old('department') === $departmentName)>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('department')" />
                    </div>
                    <div>
                        <x-input-label for="job_title" value="Job title" />
                        <select id="job_title" name="job_title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select job title</option>
                            @foreach ($jobTitleOptions as $title)
                                <option value="{{ $title }}" @selected(old('job_title') === $title)>{{ $title }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Use standard organization job titles.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('job_title')" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <x-input-label for="branch" value="Branch" />
                            <button type="button"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                                    @click="addBranchOpen = true">
                                + Add branch
                            </button>
                        </div>
                        <select id="branch" name="branch" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select branch</option>
                            @foreach ($branchOptions as $branch)
                                <option value="{{ $branch->name }}" @selected($selectedBranchName === $branch->name)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                    </div>
                    <div>
                        <x-input-label for="hire_date" value="Hire date" />
                        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-1 block w-full" :value="old('hire_date')" />
                        <x-input-error class="mt-2" :messages="$errors->get('hire_date')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="loan_role" value="Loan system role (creates login)" />
                        <select id="loan_role"
                                name="loan_role"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="
                                    const dept = roleDeptMap[$event.target.value] || '';
                                    const title = roleTitleMap[$event.target.value] || '';
                                    if (dept !== '') {
                                        document.getElementById('department').value = dept;
                                    }
                                    if (title !== '') {
                                        document.getElementById('job_title').value = title;
                                    }
                                ">
                            <option value="">No system login (HR record only)</option>
                            @foreach (($loanRoleOptions ?? []) as $role)
                                <option value="{{ $role['slug'] }}" @selected(old('loan_role') === $role['slug'])>{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Selecting a role automatically creates a user account and emails credentials to the work email above.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('loan_role')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save employee') }}</x-primary-button>
                </div>
            </form>

            <div x-show="addDeptOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-slate-200 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Add department</h3>
                            <p class="text-sm text-slate-500 mt-1">Department will appear in the employee dropdown immediately after save.</p>
                        </div>
                        <button type="button" class="text-slate-500 hover:text-slate-700" @click="addDeptOpen = false">Close</button>
                    </div>
                    <form method="post" action="{{ route('loan.employees.departments.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="department_name_modal" value="Department name" />
                            <x-text-input id="department_name_modal" name="name" type="text" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="department_code_modal" value="Code (optional)" />
                            <x-text-input id="department_code_modal" name="code" type="text" class="mt-1 block w-full" />
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <x-primary-button>Add department</x-primary-button>
                            <button type="button" class="text-sm text-slate-600 hover:text-slate-800" @click="addDeptOpen = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="addBranchOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-xl border border-slate-200 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Add branch</h3>
                            <p class="text-sm text-slate-500 mt-1">Branch will appear in the employee dropdown immediately after save.</p>
                        </div>
                        <button type="button" class="text-slate-500 hover:text-slate-700" @click="addBranchOpen = false">Close</button>
                    </div>
                    <form method="post" action="{{ route('loan.employees.branches.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="branch_region_modal" value="Region" />
                            <select id="branch_region_modal" name="loan_region_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                <option value="">Select region</option>
                                @foreach ($regionOptions as $region)
                                    <option value="{{ $region->id }}">{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="branch_name_modal" value="Branch name" />
                            <x-text-input id="branch_name_modal" name="name" type="text" class="mt-1 block w-full" required />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="branch_code_modal" value="Code (optional)" />
                                <x-text-input id="branch_code_modal" name="code" type="text" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="branch_phone_modal" value="Phone (optional)" />
                                <x-text-input id="branch_phone_modal" name="phone" type="text" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="branch_address_modal" value="Address (optional)" />
                            <x-text-input id="branch_address_modal" name="address" type="text" class="mt-1 block w-full" />
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <x-primary-button>Add branch</x-primary-button>
                            <button type="button" class="text-sm text-slate-600 hover:text-slate-800" @click="addBranchOpen = false">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
