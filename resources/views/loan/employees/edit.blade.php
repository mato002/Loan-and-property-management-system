<x-loan-layout>
    <x-loan.page
        title="Edit employee"
        subtitle="Update staff details."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-3xl">
            <form method="post" action="{{ route('loan.employees.update', $employee) }}" class="space-y-5">
                @csrf
                @method('patch')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="employee_number" value="Employee number" />
                        <x-text-input id="employee_number" name="employee_number" type="text" class="mt-1 block w-full" :value="old('employee_number', $employee->employee_number)" required autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('employee_number')" />
                    </div>
                    <div>
                        <x-input-label for="first_name" value="First name" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $employee->first_name)" required autocomplete="given-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Last name" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $employee->last_name)" required autocomplete="family-name" />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Work email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $employee->email)" autocomplete="email" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $employee->phone)" autocomplete="tel" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div>
                        <x-input-label for="personal_email" value="Personal email" />
                        <x-text-input id="personal_email" name="personal_email" type="email" class="mt-1 block w-full" :value="old('personal_email', $employee->personal_email)" autocomplete="email" />
                        <x-input-error class="mt-2" :messages="$errors->get('personal_email')" />
                    </div>
                    <div>
                        <x-input-label for="department" value="Department" />
                        <select id="department" name="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select department</option>
                            @foreach (($departmentNames ?? collect()) as $departmentName)
                                <option value="{{ $departmentName }}" @selected(old('department', $employee->department) === $departmentName)>{{ $departmentName }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('department')" />
                    </div>
                    <div>
                        <x-input-label for="job_title" value="Job title" />
                        <select id="job_title" name="job_title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select job title</option>
                            @foreach (($jobTitleOptions ?? collect()) as $title)
                                <option value="{{ $title }}" @selected(old('job_title', $employee->job_title) === $title)>{{ $title }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('job_title')" />
                    </div>
                    <div>
                        <x-input-label for="branch" value="Branch" />
                        <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch', $employee->branch)" />
                        <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                    </div>
                    <div>
                        <x-input-label for="hire_date" value="Hire date" />
                        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-1 block w-full" :value="old('hire_date', optional($employee->hire_date)->format('Y-m-d'))" />
                        <x-input-error class="mt-2" :messages="$errors->get('hire_date')" />
                    </div>
                    <div>
                        <x-input-label for="employment_status" value="Employment status" />
                        <select id="employment_status" name="employment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select status</option>
                            @foreach (['Active', 'On Leave', 'Suspended', 'Exited'] as $status)
                                <option value="{{ $status }}" @selected(old('employment_status', $employee->employment_status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employment_status')" />
                    </div>
                    <div>
                        <x-input-label for="work_type" value="Work type" />
                        <select id="work_type" name="work_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select work type</option>
                            @foreach (['Full-time', 'Part-time', 'Consultant'] as $type)
                                <option value="{{ $type }}" @selected(old('work_type', $employee->work_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('work_type')" />
                    </div>
                    <div>
                        <x-input-label for="gender" value="Gender" />
                        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select gender</option>
                            @foreach (['Male', 'Female', 'Other'] as $gender)
                                <option value="{{ $gender }}" @selected(old('gender', $employee->gender) === $gender)>{{ $gender }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('gender')" />
                    </div>
                    <div>
                        <x-input-label for="national_id" value="National ID" />
                        <x-text-input id="national_id" name="national_id" type="text" class="mt-1 block w-full" :value="old('national_id', $employee->national_id)" />
                        <x-input-error class="mt-2" :messages="$errors->get('national_id')" />
                    </div>
                    <div>
                        <x-input-label for="next_of_kin_name" value="Next of kin name" />
                        <x-text-input id="next_of_kin_name" name="next_of_kin_name" type="text" class="mt-1 block w-full" :value="old('next_of_kin_name', $employee->next_of_kin_name)" />
                        <x-input-error class="mt-2" :messages="$errors->get('next_of_kin_name')" />
                    </div>
                    <div>
                        <x-input-label for="next_of_kin_phone" value="Next of kin phone" />
                        <x-text-input id="next_of_kin_phone" name="next_of_kin_phone" type="text" class="mt-1 block w-full" :value="old('next_of_kin_phone', $employee->next_of_kin_phone)" />
                        <x-input-error class="mt-2" :messages="$errors->get('next_of_kin_phone')" />
                    </div>
                    <div>
                        <x-input-label for="supervisor_employee_id" value="Immediate supervisor" />
                        <select id="supervisor_employee_id" name="supervisor_employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select supervisor</option>
                            @foreach (($supervisors ?? collect()) as $supervisor)
                                <option value="{{ $supervisor->id }}" @selected((string) old('supervisor_employee_id', $employee->supervisor_employee_id) === (string) $supervisor->id)>{{ $supervisor->full_name }} ({{ $supervisor->employee_number }})</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('supervisor_employee_id')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="assigned_tools" value="Assigned tools (comma separated)" />
                        <x-text-input id="assigned_tools" name="assigned_tools" type="text" class="mt-1 block w-full" :value="old('assigned_tools', $employee->assigned_tools)" />
                        <x-input-error class="mt-2" :messages="$errors->get('assigned_tools')" />
                    </div>
                    <div>
                        <x-input-label for="kra_pin" value="KRA PIN" />
                        <x-text-input id="kra_pin" name="kra_pin" type="text" class="mt-1 block w-full" :value="old('kra_pin', $employee->kra_pin)" />
                        <x-input-error class="mt-2" :messages="$errors->get('kra_pin')" />
                    </div>
                    <div>
                        <x-input-label for="bank_name" value="Bank name" />
                        <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name', $employee->bank_name)" />
                        <x-input-error class="mt-2" :messages="$errors->get('bank_name')" />
                    </div>
                    <div>
                        <x-input-label for="bank_account_number" value="Bank account number" />
                        <x-text-input id="bank_account_number" name="bank_account_number" type="text" class="mt-1 block w-full" :value="old('bank_account_number', $employee->bank_account_number)" />
                        <x-input-error class="mt-2" :messages="$errors->get('bank_account_number')" />
                    </div>
                    <div>
                        <x-input-label for="nhif_number" value="NHIF number" />
                        <x-text-input id="nhif_number" name="nhif_number" type="text" class="mt-1 block w-full" :value="old('nhif_number', $employee->nhif_number)" />
                        <x-input-error class="mt-2" :messages="$errors->get('nhif_number')" />
                    </div>
                    <div>
                        <x-input-label for="nssf_number" value="NSSF number" />
                        <x-text-input id="nssf_number" name="nssf_number" type="text" class="mt-1 block w-full" :value="old('nssf_number', $employee->nssf_number)" />
                        <x-input-error class="mt-2" :messages="$errors->get('nssf_number')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="employment_contract_scan" value="Employment contract scan (path / reference)" />
                        <x-text-input id="employment_contract_scan" name="employment_contract_scan" type="text" class="mt-1 block w-full" :value="old('employment_contract_scan', $employee->employment_contract_scan)" />
                        <x-input-error class="mt-2" :messages="$errors->get('employment_contract_scan')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
