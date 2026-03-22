<x-loan-layout>
    <x-loan.page
        title="New staff loan"
        subtitle="An account reference (STF-xxxxx) is assigned after save."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.staff_loans') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        @if ($employees->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <a href="{{ route('loan.employees.create') }}" class="font-semibold underline">Add an employee</a> first.
            </div>
        @else
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
                <form method="post" action="{{ route('loan.employees.staff_loans.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="employee_id" value="Employee" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->full_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="principal" value="Principal (Ksh)" />
                        <x-text-input id="principal" name="principal" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('principal')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('principal')" />
                    </div>
                    <div>
                        <x-input-label for="balance" value="Current balance (Ksh)" />
                        <x-text-input id="balance" name="balance" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('balance')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('balance')" />
                    </div>
                    <div>
                        <x-input-label for="next_due_date" value="Next due date" />
                        <x-text-input id="next_due_date" name="next_due_date" type="date" class="mt-1 block w-full" :value="old('next_due_date')" />
                        <x-input-error class="mt-2" :messages="$errors->get('next_due_date')" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            @foreach (['current', 'arrears', 'closed'] as $st)
                                <option value="{{ $st }}" @selected(old('status', 'current') === $st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('status')" />
                    </div>
                    <x-primary-button>{{ __('Save loan') }}</x-primary-button>
                </form>
            </div>
        @endif
    </x-loan.page>
</x-loan-layout>
