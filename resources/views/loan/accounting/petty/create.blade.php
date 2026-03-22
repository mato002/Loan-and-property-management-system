<x-loan-layout>
    <x-loan.page title="Petty cash line" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.petty.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.petty.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="entry_date" class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
                    <input id="entry_date" name="entry_date" type="date" value="{{ old('entry_date', now()->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('entry_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="kind" class="block text-xs font-semibold text-slate-600 mb-1">Kind</label>
                    <select id="kind" name="kind" required class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="receipt" @selected(old('kind') === 'receipt')>Receipt (top-up)</option>
                        <option value="disbursement" @selected(old('kind') === 'disbursement')>Disbursement</option>
                    </select>
                    @error('kind')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payee_or_source" class="block text-xs font-semibold text-slate-600 mb-1">Payee or source</label>
                    <input id="payee_or_source" name="payee_or_source" value="{{ old('payee_or_source') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('payee_or_source')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                    <input id="description" name="description" value="{{ old('description') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
