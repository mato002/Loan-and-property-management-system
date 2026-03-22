<x-property-layout>
    <x-slot name="header">Withdraw funds</x-slot>

    <x-property.page
        title="Withdraw funds"
        subtitle="Ledger-validated payouts — available balance only."
    >
        <div class="grid gap-4 sm:grid-cols-2 max-w-3xl w-full min-w-0">
            <div class="rounded-2xl border border-emerald-200 dark:border-emerald-800/50 bg-white dark:bg-gray-900/50 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-emerald-800/80 dark:text-emerald-300/90">Available</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white tabular-nums">{{ $available }}</p>
                <p class="text-xs text-slate-500 mt-1">Mirrors ledger entries for your linked properties.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/70 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase text-slate-500">How it works</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Credits post from rent recognition rules; debits when you withdraw. Amount cannot exceed available.</p>
            </div>
        </div>

        <form method="post" action="{{ route('property.landlord.earnings.withdraw.store') }}" class="max-w-xl w-full min-w-0 space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount (KES)</label>
                <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="1" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payout destination</label>
                <select name="payout_destination" class="mt-1 w-full min-w-0 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="bank" @selected(old('payout_destination', 'bank') === 'bank')>Bank transfer</option>
                    <option value="mpesa" @selected(old('payout_destination') === 'mpesa')>M-Pesa</option>
                </select>
                <p class="mt-1 text-xs text-slate-500">Recorded on the ledger line for audit; connect your payout provider when ready.</p>
            </div>
            <div class="flex items-start gap-2">
                <input type="checkbox" required class="mt-1 rounded border-slate-300" id="withdraw-confirm" />
                <label for="withdraw-confirm" class="text-sm text-slate-600 dark:text-slate-400">I confirm this withdrawal matches my instruction and I accept any fees disclosed by the platform.</label>
            </div>
            <button type="submit" class="w-full sm:w-auto rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Request withdrawal</button>
        </form>

        <a href="{{ route('property.landlord.earnings.index') }}" class="inline-block mt-6 text-sm font-medium text-emerald-700 dark:text-emerald-400 hover:underline">← Back to earnings</a>
    </x-property.page>
</x-property-layout>
