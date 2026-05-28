<x-property-layout>
    <x-slot name="header">Payout settings</x-slot>

    <x-property.page
        title="Payout settings"
        subtitle="Configure your preferred destination and optional automatic payout schedule."
    >
        <form method="post" action="{{ route('property.landlord.earnings.settings.store') }}" class="w-full space-y-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm" data-swal-confirm="Save payout settings changes?">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Default destination</label>
                    <select name="default_destination" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                        <option value="bank" @selected(old('default_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'bank')>Bank transfer</option>
                        <option value="mpesa" @selected(old('default_destination', $payoutPrefs['default_destination'] ?? 'bank') === 'mpesa')>M-Pesa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Destination details</label>
                    <input type="text" name="destination_detail" value="{{ old('destination_detail', $payoutPrefs['destination_detail'] ?? '') }}" placeholder="Account number or phone number" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Auto-withdraw day</label>
                    <input type="number" min="1" max="28" name="auto_withdraw_day" value="{{ old('auto_withdraw_day', $payoutPrefs['auto_withdraw_day'] ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Minimum payout amount (KES)</label>
                    <input type="number" min="0" step="0.01" name="minimum_payout_amount" value="{{ old('minimum_payout_amount', $payoutPrefs['minimum_payout_amount'] ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <label class="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="auto_withdraw_enabled" value="1" class="mt-1 rounded border-slate-300" @checked(old('auto_withdraw_enabled', $payoutPrefs['auto_withdraw_enabled'] ?? false)) />
                <span>Enable auto-withdraw schedule (records your instruction; treasury integration executes payouts when connected).</span>
            </label>
            <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save settings</button>
        </form>
        <a href="{{ route('property.landlord.earnings.index') }}" class="inline-block mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-400 hover:underline">← Back to earnings</a>
    </x-property.page>
</x-property-layout>
