<x-loan-layout>
    <x-loan.page
        title="Create a lead"
        subtitle="Capture a prospect before full KYC and client onboarding."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.leads') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to leads
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-3xl">
            <form method="post" action="{{ route('loan.clients.leads.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="client_number" value="Lead reference" />
                        <x-text-input id="client_number" name="client_number" type="text" class="mt-1 block w-full" :value="old('client_number')" required autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('client_number')" />
                    </div>
                    <div>
                        <x-input-label for="first_name" value="First name" />
                        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>
                    <div>
                        <x-input-label for="last_name" value="Last name" />
                        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="branch" value="Branch" />
                        <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch')" />
                        <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                    </div>
                    <div>
                        <x-input-label for="lead_status" value="Lead status" />
                        <select id="lead_status" name="lead_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['new', 'contacted', 'qualified', 'lost'] as $st)
                                <option value="{{ $st }}" @selected(old('lead_status', 'new') === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('lead_status')" />
                    </div>
                    <div>
                        <x-input-label for="assigned_employee_id" value="Assigned officer" />
                        <select id="assigned_employee_id" name="assigned_employee_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— None —</option>
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(old('assigned_employee_id') == $employee->id)>{{ $employee->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('assigned_employee_id')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save lead') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
