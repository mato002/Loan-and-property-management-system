<x-loan-layout>
    <x-loan.page
        title="New leave request"
        subtitle="Select the employee and leave period. Days are calculated automatically."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.leaves') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to leaves
            </a>
        </x-slot>

        @if ($employees->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Add at least one <a href="{{ route('loan.employees.create') }}" class="font-semibold underline">employee</a> before creating leave requests.
            </div>
        @else
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
                <form method="post" action="{{ route('loan.employees.leaves.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="employee_id" value="Employee" />
                        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            <option value="">Choose…</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->full_name }} ({{ $emp->employee_number }})</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                    </div>
                    <div>
                        <x-input-label for="leave_type" value="Leave type" />
                        <select id="leave_type" name="leave_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                            @foreach (['Annual', 'Sick', 'Unpaid', 'Maternity', 'Paternity', 'Other'] as $type)
                                <option value="{{ $type }}" @selected(old('leave_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('leave_type')" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_date" value="Start date" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
                        </div>
                        <div>
                            <x-input-label for="end_date" value="End date" />
                            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('end_date')" />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="notes" value="Notes (optional)" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes') }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                    </div>
                    <x-primary-button>{{ __('Submit request') }}</x-primary-button>
                </form>
            </div>
        @endif
    </x-loan.page>
</x-loan-layout>
