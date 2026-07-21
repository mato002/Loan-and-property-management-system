<x-property-layout>
    <x-slot name="header">Remittance status</x-slot>

    <x-property.page title="Remittance instructions">
        <x-property.landlord.kpi-grid cols="3" class="mb-4">
            <x-property.landlord.kpi-card label="Ledger balance" :value="$ledgerBalance" />
            <x-property.landlord.kpi-card label="Pending instructions" :value="$pendingTotal" />
            <x-property.landlord.kpi-card label="Available" :value="$available" emphasis />
        </x-property.landlord.kpi-grid>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Destination</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Paid ref</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr class="border-t border-slate-100 dark:border-slate-700/70">
                            <td class="px-4 py-3">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</td>
                            <td class="px-4 py-3">{{ strtoupper($r->destination) }} · {{ $r->destination_detail }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match ($r->status) {
                                        'paid' => 'bg-emerald-100 text-emerald-800',
                                        'acknowledged' => 'bg-blue-100 text-blue-800',
                                        'cancelled' => 'bg-slate-100 text-slate-600',
                                        default => 'bg-amber-100 text-amber-800',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">{{ $r->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs">{{ $r->paid_reference ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No remittance instructions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex gap-2">
            <a href="{{ route('property.landlord.earnings.withdraw') }}" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">New instruction</a>
            <a href="{{ route('property.landlord.earnings.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">Back</a>
        </div>
    </x-property.page>
</x-property-layout>
