<x-loan-layout>
    <x-loan.page title="Add loan size band" subtitle="Principal range in Ksh. Leave max empty for no upper cap.">
        <x-slot name="actions">
            <a href="{{ route('loan.analytics.loan_sizes') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 sm:p-8 max-w-xl">
            <form method="post" action="{{ route('loan.analytics.loan_sizes.store') }}" class="space-y-5">
                @csrf
                <div>
                    <x-input-label for="label" value="Label" />
                    <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label')" required placeholder="e.g. Micro (≤ 50k)" />
                    <x-input-error class="mt-2" :messages="$errors->get('label')" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="min_principal" value="Min principal (Ksh)" />
                        <x-text-input id="min_principal" name="min_principal" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('min_principal', 0)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('min_principal')" />
                    </div>
                    <div>
                        <x-input-label for="max_principal" value="Max principal (Ksh)" />
                        <x-text-input id="max_principal" name="max_principal" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('max_principal')" />
                        <p class="mt-1 text-xs text-slate-500">Optional; must be greater than min.</p>
                        <x-input-error class="mt-2" :messages="$errors->get('max_principal')" />
                    </div>
                </div>
                <div>
                    <x-input-label for="sort_order" value="Sort order" />
                    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', 0)" />
                    <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
                </div>
                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>
                <x-primary-button>{{ __('Save band') }}</x-primary-button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
