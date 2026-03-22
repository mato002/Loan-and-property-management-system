<x-loan-layout>
    <x-loan.page
        title="Add employee"
        subtitle="Capture core HR fields. You can extend this form when payroll and LDAP integrations are added."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-3xl">
            <form method="post" action="{{ route('loan.employees.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="employee_number" value="Employee number" />
                        <x-text-input id="employee_number" name="employee_number" type="text" class="mt-1 block w-full" :value="old('employee_number')" required autocomplete="off" />
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
                        <x-input-label for="department" value="Department" />
                        <x-text-input id="department" name="department" type="text" class="mt-1 block w-full" :value="old('department')" list="department-suggestions" />
                        <datalist id="department-suggestions">
                            <option value="Credit"></option>
                            <option value="Collections"></option>
                            <option value="Operations"></option>
                            <option value="Risk"></option>
                            <option value="IT"></option>
                        </datalist>
                        <x-input-error class="mt-2" :messages="$errors->get('department')" />
                    </div>
                    <div>
                        <x-input-label for="job_title" value="Job title" />
                        <x-text-input id="job_title" name="job_title" type="text" class="mt-1 block w-full" :value="old('job_title')" />
                        <x-input-error class="mt-2" :messages="$errors->get('job_title')" />
                    </div>
                    <div>
                        <x-input-label for="branch" value="Branch" />
                        <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch')" />
                        <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                    </div>
                    <div>
                        <x-input-label for="hire_date" value="Hire date" />
                        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-1 block w-full" :value="old('hire_date')" />
                        <x-input-error class="mt-2" :messages="$errors->get('hire_date')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save employee') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
