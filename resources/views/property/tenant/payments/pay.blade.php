<x-property-layout>
    <x-slot name="header">Pay rent</x-slot>

    <x-property.page
        title="Pay rent"
        subtitle="Real payment wiring: choose method, submit details, and auto-allocate to open invoices."
    >
        <div class="rounded-2xl border border-teal-200/70 dark:border-teal-900/50 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-5 w-full min-w-0">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 text-center">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Amount due now</p>
                <p class="text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-white tabular-nums mt-1 break-words">{{ $amountDue ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-2">Includes rent and posted charges for this period.</p>
            </div>
            <form method="post" action="{{ route('property.tenant.payments.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payment method</label>
                    <select name="payment_method" required class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3">
                        @foreach (($paymentMethods ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', 'mpesa_stk') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Phone (for M-Pesa STK)</label>
                    <input type="tel" name="payer_phone" value="{{ old('payer_phone') }}" autocomplete="tel" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="07XX XXX XXX" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">External reference (bank/card/cash)</label>
                    <input type="text" name="external_ref" value="{{ old('external_ref') }}" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="Transaction reference / receipt no." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Pay different amount</label>
                    <input type="text" name="custom_amount" value="{{ old('custom_amount') }}" inputmode="decimal" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="Optional partial pay" />
                </div>
                <button type="submit" class="w-full rounded-xl bg-teal-600 py-3.5 text-sm font-semibold text-white hover:bg-teal-700">Submit payment</button>
                <p class="text-xs text-center text-slate-500">STK submissions are saved as pending. Bank/card/cash submissions are marked completed and allocated to open invoices.</p>
            </form>
        </div>
        <a href="{{ route('property.tenant.payments.index') }}" class="inline-block text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to payments</a>
    </x-property.page>
</x-property-layout>
