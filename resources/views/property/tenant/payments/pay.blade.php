<x-property-layout>
    <x-slot name="header">Pay rent</x-slot>

    <x-property.page
        title="Pay rent"
        subtitle="M-Pesa STK push — amount prefilled from your open invoice when billing is connected."
    >
        <div class="rounded-2xl border border-teal-200/70 dark:border-teal-900/50 bg-white dark:bg-gray-800/80 p-4 sm:p-6 shadow-sm space-y-5 w-full max-w-lg min-w-0">
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 text-center">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Amount due now</p>
                <p class="text-2xl sm:text-3xl font-semibold text-slate-900 dark:text-white tabular-nums mt-1 break-words">{{ $amountDue ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-2">Includes rent and posted charges for this period.</p>
            </div>
            <form method="post" action="{{ route('property.tenant.payments.stk.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">M-Pesa phone</label>
                    <input type="tel" name="mpesa_phone" required autocomplete="tel" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="07XX XXX XXX" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Pay different amount</label>
                    <input type="text" name="custom_amount" inputmode="decimal" class="mt-1 w-full min-w-0 rounded-xl border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-900/40 text-sm px-4 py-3" placeholder="Optional partial pay" />
                </div>
                <button type="submit" class="w-full rounded-xl bg-teal-600 py-3.5 text-sm font-semibold text-white hover:bg-teal-700">Send STK push</button>
                <p class="text-xs text-center text-slate-500">Creates a pending payment record for agents to see. Connect Daraja to deliver a real STK prompt.</p>
            </form>
        </div>
        <a href="{{ route('property.tenant.payments.index') }}" class="inline-block text-sm font-medium text-teal-700 dark:text-teal-400 hover:underline">← Back to payments</a>
    </x-property.page>
</x-property-layout>
