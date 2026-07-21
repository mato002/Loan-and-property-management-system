<x-property-layout>
    <x-slot name="header">Request remittance</x-slot>

    <x-property.page title="Request remittance">
        <x-property.landlord.kpi-grid cols="2">
            <x-property.landlord.kpi-card label="Ledger balance" :value="$ledgerBalance" emphasis />
            <x-property.landlord.kpi-card label="Available for new instruction" :value="$available" />
        </x-property.landlord.kpi-grid>

        <p class="text-sm text-slate-600 dark:text-slate-400">Submit an instruction for your agency to remit funds manually. No payment is initiated from this system.</p>

        <form method="post" action="{{ route('property.landlord.earnings.withdraw.store') }}" class="w-full min-w-0 space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm" data-swal-confirm="Submit this remittance instruction?">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="1" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Destination</label>
                <select name="payout_destination" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="bank" @selected(old('payout_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'bank')>Bank transfer</option>
                    <option value="mpesa" @selected(old('payout_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'mpesa')>M-Pesa</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Destination detail</label>
                <input type="text" name="destination_detail" value="{{ old('destination_detail', $payoutPrefs['destination_detail'] ?? '') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" placeholder="Account or M-Pesa number" />
                @error('destination_detail')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reference note (optional)</label>
                <input type="text" name="reference_note" value="{{ old('reference_note') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <button type="submit" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Submit instruction</button>
        </form>

        <a href="{{ route('property.landlord.earnings.index') }}" class="inline-block mt-6 text-sm font-medium text-emerald-700 hover:underline">← Back</a>
    </x-property.page>
</x-property-layout>
