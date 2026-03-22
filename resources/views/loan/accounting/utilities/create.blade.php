<x-loan-layout>
    <x-loan.page title="Record utility payment" subtitle="">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.utilities.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
        </x-slot>
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm max-w-xl overflow-hidden">
            <form method="post" action="{{ route('loan.accounting.utilities.store') }}" class="px-5 py-6 space-y-4">
                @csrf
                <div>
                    <label for="utility_type" class="block text-xs font-semibold text-slate-600 mb-1">Utility type</label>
                    <input id="utility_type" name="utility_type" value="{{ old('utility_type') }}" required placeholder="e.g. electricity" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('utility_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="provider" class="block text-xs font-semibold text-slate-600 mb-1">Provider</label>
                    <input id="provider" name="provider" value="{{ old('provider') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('provider')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="bill_account_ref" class="block text-xs font-semibold text-slate-600 mb-1">Account / meter ref</label>
                    <input id="bill_account_ref" name="bill_account_ref" value="{{ old('bill_account_ref') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                    @error('bill_account_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="amount" class="block text-xs font-semibold text-slate-600 mb-1">Amount</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                    @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="currency" class="block text-xs font-semibold text-slate-600 mb-1">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', 'KES') }}" maxlength="8" class="w-full rounded-lg border-slate-200 text-sm uppercase" />
                    @error('currency')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="paid_on" class="block text-xs font-semibold text-slate-600 mb-1">Paid on</label>
                    <input id="paid_on" name="paid_on" type="date" value="{{ old('paid_on', now()->toDateString()) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    @error('paid_on')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="payment_method" class="block text-xs font-semibold text-slate-600 mb-1">Payment method</label>
                    <select id="payment_method" name="payment_method" required class="w-full rounded-lg border-slate-200 text-sm">
                        @foreach (['bank' => 'Bank / EFT', 'mpesa' => 'M-Pesa', 'cash' => 'Cash', 'cheque' => 'Cheque'] as $v => $lab)
                            <option value="{{ $v }}" @selected(old('payment_method', 'bank') === $v)>{{ $lab }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="reference" class="block text-xs font-semibold text-slate-600 mb-1">External reference</label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" class="w-full rounded-lg border-slate-200 text-sm font-mono" />
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Save</button>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
