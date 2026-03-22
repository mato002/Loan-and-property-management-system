<x-loan-layout>
    <x-loan.page
        title="Add client"
        subtitle="Register a borrower or savings customer in the loan book."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-3xl">
            <form method="post" action="{{ route('loan.clients.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2">
                        <x-input-label for="client_number" value="Client number" />
                        <x-text-input id="client_number" name="client_number" type="text" class="mt-1 block w-full" :value="old('client_number')" required autocomplete="off" />
                        <x-input-error class="mt-2" :messages="$errors->get('client_number')" />
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
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" autocomplete="tel" />
                        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" autocomplete="email" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>
                    <div>
                        <x-input-label for="id_number" value="ID / registration" />
                        <x-text-input id="id_number" name="id_number" type="text" class="mt-1 block w-full" :value="old('id_number')" />
                        <x-input-error class="mt-2" :messages="$errors->get('id_number')" />
                    </div>
                    <div>
                        <x-input-label for="branch" value="Branch" />
                        <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch')" />
                        <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('address') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('address')" />
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
                    <div>
                        <x-input-label for="client_status" value="Client status" />
                        <select id="client_status" name="client_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['active', 'dormant', 'watchlist'] as $st)
                                <option value="{{ $st }}" @selected(old('client_status', 'active') === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('client_status')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <x-primary-button>{{ __('Save client') }}</x-primary-button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
