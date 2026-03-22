<x-loan-layout>
    <x-loan.page
        title="Edit portfolio"
        subtitle="Update assignment {{ $portfolio->portfolio_code }}."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.employees.portfolios') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back to list
            </a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
            <form method="post" action="{{ route('loan.employees.portfolios.update', $portfolio) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="employee_id" value="Employee" />
                    <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}" @selected(old('employee_id', $portfolio->employee_id) == $emp->id)>{{ $emp->full_name }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                </div>
                <div>
                    <x-input-label for="portfolio_code" value="Portfolio code" />
                    <x-text-input id="portfolio_code" name="portfolio_code" type="text" class="mt-1 block w-full font-mono" :value="old('portfolio_code', $portfolio->portfolio_code)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('portfolio_code')" />
                </div>
                <div>
                    <x-input-label for="active_loans" value="Active loans (count)" />
                    <x-text-input id="active_loans" name="active_loans" type="number" min="0" class="mt-1 block w-full" :value="old('active_loans', $portfolio->active_loans)" />
                    <x-input-error class="mt-2" :messages="$errors->get('active_loans')" />
                </div>
                <div>
                    <x-input-label for="outstanding_amount" value="Outstanding amount (Ksh)" />
                    <x-text-input id="outstanding_amount" name="outstanding_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('outstanding_amount', $portfolio->outstanding_amount)" />
                    <x-input-error class="mt-2" :messages="$errors->get('outstanding_amount')" />
                </div>
                <div>
                    <x-input-label for="par_rate" value="PAR rate (%)" />
                    <x-text-input id="par_rate" name="par_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('par_rate', $portfolio->par_rate)" />
                    <x-input-error class="mt-2" :messages="$errors->get('par_rate')" />
                </div>
                <x-primary-button>{{ __('Update') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
