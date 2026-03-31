<x-property-layout>
    <x-slot name="header">Receipt #RCP-PAY-{{ $payment->id }}</x-slot>

    <div class="space-y-6 max-w-4xl mx-auto">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">PrimeEstate Payment Receipt</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">Receipt No: RCP-PAY-{{ $payment->id }}</p>
            </div>
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('property.payments.receipt.download', $payment) }}"
                    data-turbo="false"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >Download</a>
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex items-center rounded-xl border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                >Print / Save PDF</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Tenant</p>
                <p class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $payment->tenant?->name ?? '—' }}</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $payment->tenant?->email ?? '—' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Payment details</p>
                <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">Channel: <span class="font-semibold uppercase">{{ $payment->channel }}</span></p>
                <p class="text-sm text-slate-700 dark:text-slate-200">Reference: <span class="font-semibold">{{ $payment->external_ref ?: '—' }}</span></p>
                <p class="text-sm text-slate-700 dark:text-slate-200">Paid at: <span class="font-semibold">{{ $payment->paid_at?->format('Y-m-d H:i:s') ?? '—' }}</span></p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Allocated amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payment->allocations as $allocation)
                        <tr class="border-t border-slate-100 dark:border-slate-700/80">
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $allocation->invoice?->invoice_no ?? ('INV-'.$allocation->pm_invoice_id) }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">KES {{ number_format((float) $allocation->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-slate-500 dark:text-slate-400">No allocations recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 bg-slate-50/50 dark:bg-slate-900/30 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total paid</p>
            <p class="mt-1 text-2xl font-black text-slate-900 dark:text-white">KES {{ number_format((float) $payment->amount, 2) }}</p>
        </div>
    </div>
</x-property-layout>

