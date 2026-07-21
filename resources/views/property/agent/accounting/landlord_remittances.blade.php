<x-property-layout>
    <x-slot name="header">Landlord remittance instructions</x-slot>

    <x-property.page title="Landlord remittance instructions">
        <p class="text-sm text-slate-600 mb-4">Instructions from the landlord portal. Mark paid after you remit funds manually outside the system.</p>

        <form method="get" class="mb-4 flex gap-2">
            <select name="status" class="rounded-lg border px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['pending', 'acknowledged', 'paid', 'cancelled'] as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-lg border px-3 py-2 text-sm">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-2xl border">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">ID</th>
                        <th class="px-3 py-2">Landlord</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Destination</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr class="border-t align-top">
                            <td class="px-3 py-2">#{{ $r->id }}</td>
                            <td class="px-3 py-2">{{ $r->user?->name ?? '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</td>
                            <td class="px-3 py-2">{{ strtoupper($r->destination) }} · {{ $r->destination_detail }}</td>
                            <td class="px-3 py-2">{{ $r->statusLabel() }}</td>
                            <td class="px-3 py-2">
                                @if ($r->status === 'pending')
                                    <form method="post" action="{{ route('property.accounting.payables.landlord_remittances.acknowledge', $r) }}" class="inline">@csrf<button class="text-blue-600 text-xs">Acknowledge</button></form>
                                @endif
                                @if (in_array($r->status, ['pending', 'acknowledged'], true))
                                    <form method="post" action="{{ route('property.accounting.payables.landlord_remittances.paid', $r) }}" class="mt-1 space-y-1">
                                        @csrf
                                        <input name="paid_reference" placeholder="Payment ref" class="rounded border px-2 py-1 text-xs w-full" />
                                        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="post_ledger" value="1" checked /> Post ledger debit</label>
                                        <button class="text-emerald-700 text-xs font-medium">Mark paid</button>
                                    </form>
                                    <form method="post" action="{{ route('property.accounting.payables.landlord_remittances.cancel', $r) }}" class="mt-1">@csrf<button class="text-rose-600 text-xs">Cancel</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-property.page>
</x-property-layout>
