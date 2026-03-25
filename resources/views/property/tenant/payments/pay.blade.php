<x-property-layout>
    <x-slot name="header">Pay rent</x-slot>

    <x-property.page
        title="Pay rent"
        subtitle="Pay with M-Pesa STK (prompt to phone). No reference number needed."
    >
        <div class="rounded-2xl border border-teal-200/70 dark:border-teal-900/50 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-5 w-full min-w-0">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 text-center">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Amount due now</p>
                <p class="text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-white tabular-nums mt-1 break-words">{{ $amountDue ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-2">Includes rent and posted charges for this period.</p>
            </div>

            <form
                method="post"
                action="{{ route('property.tenant.payments.store') }}"
                class="space-y-4"
                x-data="{ method: '{{ old('payment_method', 'mpesa_stk') }}' }"
            >
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Payment method</label>
                    <select
                        name="payment_method"
                        required
                        class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                        x-model="method"
                    >
                        @foreach (($paymentMethods ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', 'mpesa_stk') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div x-show="method === 'mpesa_stk'" x-cloak class="rounded-xl border border-teal-200/60 dark:border-teal-900/40 bg-teal-50/60 dark:bg-teal-950/20 p-4">
                    <p class="text-sm font-semibold text-teal-900 dark:text-teal-100">M-Pesa STK Push</p>
                    <p class="text-xs text-teal-800/80 dark:text-teal-200/80 mt-1">
                        Enter your phone number. We’ll send a prompt to your phone—no reference number required.
                    </p>

                    <div class="mt-3">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Phone number</label>
                        <input
                            type="tel"
                            name="payer_phone"
                            value="{{ old('payer_phone') }}"
                            autocomplete="tel"
                            class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                            placeholder="0712 345 678 or 0111 296 234"
                        />
                        @error('payer_phone')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div x-show="method !== 'mpesa_stk'" x-cloak class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40 p-4">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Other payment methods</p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                        For non‑STK payments, provide the transaction/receipt reference so your payment can be reconciled.
                    </p>

                    <div class="mt-3">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reference number</label>
                        <input
                            type="text"
                            name="external_ref"
                            value="{{ old('external_ref') }}"
                            class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3"
                            placeholder="Transaction reference / receipt no."
                        />
                        @error('external_ref')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Amount (optional)</label>
                    <input type="text" name="custom_amount" value="{{ old('custom_amount') }}" inputmode="decimal" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="Optional partial pay" />
                    <p class="text-xs text-slate-500 mt-1">Leave blank to pay the full amount due.</p>
                    @error('custom_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-xl bg-teal-600 py-3.5 text-sm font-semibold text-white hover:bg-teal-700">
                    <span x-show="method === 'mpesa_stk'">Send STK prompt</span>
                    <span x-show="method !== 'mpesa_stk'" x-cloak>Submit payment</span>
                </button>
                <p class="text-xs text-center text-slate-500">
                    STK payments are saved as pending until Safaricom confirms the payment.
                </p>
            </form>

            @if (! empty($paymentMethodDetails))
                <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Available payment channels</h3>
                    <div class="space-y-2">
                        @foreach ($paymentMethodDetails as $method)
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $method['label'] ?? 'Payment method' }}</p>
                                @if (! empty($method['provider']) || ! empty($method['account']))
                                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                        @if (! empty($method['provider']))<span>{{ $method['provider'] }}</span>@endif
                                        @if (! empty($method['provider']) && ! empty($method['account']))<span class="text-slate-400"> · </span>@endif
                                        @if (! empty($method['account']))<span>{{ $method['account'] }}</span>@endif
                                    </p>
                                @endif
                                @if (! empty($method['instructions']))
                                    <p class="mt-1 text-xs text-slate-500">{{ $method['instructions'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <a href="{{ route('property.tenant.payments.index') }}" class="inline-block text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to payments</a>
    </x-property.page>
</x-property-layout>
