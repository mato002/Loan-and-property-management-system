<x-property-layout>
    <x-slot name="header">Waiting for payment</x-slot>

    <x-property.page
        title="Complete payment on your phone"
        subtitle="We’ve sent an M-Pesa prompt. Approve it on your phone to finish checkout."
    >
        <div class="max-w-2xl">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 sm:p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="h-12 w-12 rounded-2xl bg-teal-50 dark:bg-teal-950/30 border border-teal-100 dark:border-teal-900/40 flex items-center justify-center">
                        <span class="text-xl" aria-hidden="true">📲</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">STK prompt sent</p>
                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">
                            Check your phone and enter your M-Pesa PIN to approve the payment.
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Amount</p>
                        <p class="mt-1 font-semibold tabular-nums text-slate-900 dark:text-white">
                            KES {{ number_format((float) $payment->amount, 2) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Status</p>
                        <p class="mt-1 font-semibold text-slate-900 dark:text-white">
                            {{ ucfirst($payment->status) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500">Phone</p>
                        <p class="mt-1 font-semibold text-slate-900 dark:text-white">
                            {{ data_get($payment->meta, 'phone') ?: '—' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-amber-200/70 bg-amber-50 p-4 text-sm text-amber-900">
                    <p class="font-semibold">If you don’t see the prompt</p>
                    <ul class="mt-2 list-disc pl-5 space-y-1 text-amber-900/90">
                        <li>Make sure your phone has network and the line is Safaricom.</li>
                        <li>Wait 10–30 seconds, then tap refresh.</li>
                        <li>Confirm your callback URL is public HTTPS and reachable by Safaricom.</li>
                    </ul>
                </div>

                <div class="mt-5 flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('property.tenant.payments.pending', $payment) }}" data-turbo="false" class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-700 w-full sm:w-auto">
                        Refresh status
                    </a>
                    <a href="{{ route('property.tenant.payments.history') }}" data-turbo="false" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 w-full sm:w-auto dark:bg-slate-900/40 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900/60">
                        View payment history
                    </a>
                </div>
            </div>
        </div>
    </x-property.page>
</x-property-layout>

