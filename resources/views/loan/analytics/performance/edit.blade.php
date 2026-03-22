<x-loan-layout>
    <x-loan.page title="Edit performance snapshot" :subtitle="$record->record_date->format('Y-m-d')">
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.performance') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
            <form method="post" action="{{ route('loan.analytics.performance.update', $record) }}" class="space-y-5">
                @csrf
                @method('patch')
                <div>
                    <x-input-label for="record_date" value="As of date" />
                    <x-text-input id="record_date" name="record_date" type="date" class="mt-1 block w-full" :value="old('record_date', $record->record_date->format('Y-m-d'))" required />
                    <x-input-error class="mt-2" :messages="$errors->get('record_date')" />
                </div>
                <div>
                    <x-input-label for="branch" value="Branch (optional)" />
                    <x-text-input id="branch" name="branch" type="text" class="mt-1 block w-full" :value="old('branch', $record->branch)" />
                    <x-input-error class="mt-2" :messages="$errors->get('branch')" />
                </div>
                <div>
                    <x-input-label for="total_outstanding" value="Total outstanding (Ksh)" />
                    <x-text-input id="total_outstanding" name="total_outstanding" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('total_outstanding', $record->total_outstanding)" />
                    <x-input-error class="mt-2" :messages="$errors->get('total_outstanding')" />
                </div>
                <div>
                    <x-input-label for="disbursements_period" value="Disbursements in period (Ksh)" />
                    <x-text-input id="disbursements_period" name="disbursements_period" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('disbursements_period', $record->disbursements_period)" />
                    <x-input-error class="mt-2" :messages="$errors->get('disbursements_period')" />
                </div>
                <div>
                    <x-input-label for="collections_period" value="Collections in period (Ksh)" />
                    <x-text-input id="collections_period" name="collections_period" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('collections_period', $record->collections_period)" />
                    <x-input-error class="mt-2" :messages="$errors->get('collections_period')" />
                </div>
                <div>
                    <x-input-label for="npl_rate" value="NPL rate (%)" />
                    <x-text-input id="npl_rate" name="npl_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('npl_rate', $record->npl_rate)" />
                    <x-input-error class="mt-2" :messages="$errors->get('npl_rate')" />
                </div>
                <div>
                    <x-input-label for="active_borrowers_count" value="Active borrowers (count)" />
                    <x-text-input id="active_borrowers_count" name="active_borrowers_count" type="number" min="0" class="mt-1 block w-full" :value="old('active_borrowers_count', $record->active_borrowers_count)" />
                    <x-input-error class="mt-2" :messages="$errors->get('active_borrowers_count')" />
                </div>
                <div>
                    <x-input-label for="notes" value="Notes" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('notes', $record->notes) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('notes')" />
                </div>
                <x-primary-button>{{ __('Update snapshot') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
