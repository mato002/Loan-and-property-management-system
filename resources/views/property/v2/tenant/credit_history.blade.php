<x-property-layout>
    <x-slot name="header">Advance credit</x-slot>

    <x-property.page title="Advance credit" subtitle="Your prepaid balance and how it has been applied.">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white p-6 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-emerald-800 font-semibold">Credit balance</p>
                <p class="mt-2 text-3xl font-black text-emerald-900">{{ $balanceFormatted }}</p>
                <p class="mt-1 text-sm text-slate-600">Advance rent available for future invoices. Applied automatically when new bills are issued.</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-slate-900">Credit history</h2>
                    <a href="{{ route('property.tenant.home') }}" class="text-sm text-indigo-600 hover:underline">Back to dashboard</a>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Activity</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $txn)
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-3">{{ $txn->created_at?->format('d M Y') }}</td>
                                <td class="px-4 py-3">{{ $txn->typeLabel() }}</td>
                                <td class="px-4 py-3 font-semibold tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $txn->amount) }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $txn->invoice?->invoice_no ?? ($txn->reference ?: '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No credit activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($transactions->hasPages())
                    <div class="px-4 py-3 border-t">{{ $transactions->links() }}</div>
                @endif
            </div>
        </div>
    </x-property.page>
</x-property-layout>
