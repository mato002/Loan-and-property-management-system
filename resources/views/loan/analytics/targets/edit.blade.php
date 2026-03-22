<x-loan-layout>
    <x-loan.page title="Edit period target" :subtitle="$target->branch.' · '.$target->period_label">
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.targets') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
            <form method="post" action="{{ route('loan.analytics.targets.update', $target) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="branch" value="Branch" />
                    <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch', $target->branch)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="period_year" value="Year" />
                        <x-text-input id="period_year" name="period_year" type="number" min="2000" max="2100" class="mt-1 block w-full" :value="old('period_year', $target->period_year)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('period_year')" />
                    </div>
                    <div>
                        <x-input-label for="period_month" value="Month (1–12)" />
                        <x-text-input id="period_month" name="period_month" type="number" min="1" max="12" class="mt-1 block w-full" :value="old('period_month', $target->period_month)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('period_month')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="disbursement_target" value="Disbursement target (Ksh)" />
                    <x-text-input id="disbursement_target" name="disbursement_target" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('disbursement_target', $target->disbursement_target)" />
                    <x-input-error class="mt-2" :messages="$errors->get('disbursement_target')" />
                </div>
                <div>
                    <x-input-label for="collection_target" value="Collection target (Ksh)" />
                    <x-text-input id="collection_target" name="collection_target" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('collection_target', $target->collection_target)" />
                    <x-input-error class="mt-2" :messages="$errors->get('collection_target')" />
                </div>
                <div>
                    <x-input-label for="accrual_target" value="Interest accrual target (Ksh)" />
                    <x-text-input id="accrual_target" name="accrual_target" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('accrual_target', $target->accrual_target)" />
                    <x-input-error class="mt-2" :messages="$errors->get('accrual_target')" />
                </div>
                <div>
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $target->notes) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>
                <x-primary-button>{{ __('Update target') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
