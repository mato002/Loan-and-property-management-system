<x-property.workspace
    :title="$vendor->name"
    subtitle="Vendor profile, completed earnings, and allocation tracking."
    back-route="property.vendors.directory"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No jobs yet"
    empty-hint="This vendor has not been assigned maintenance work."
>
    <x-slot name="actions">
        <a href="{{ route('property.vendors.edit', $vendor, false) }}" class="inline-flex items-center rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit vendor</a>
        <form method="post" action="{{ route('property.vendors.pay_outstanding', $vendor, false) }}" class="inline-flex items-center gap-1" data-swal-title="Settle all outstanding payments?" data-swal-confirm="This will mark all eligible allocated jobs for this vendor as paid." data-swal-confirm-text="Yes, settle all">
            @csrf
            <input type="date" name="paid_date" value="{{ now()->toDateString() }}" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <input type="text" name="payment_note" placeholder="Bulk note/ref (optional)" class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <input type="text" name="confirm_phrase" placeholder='Type PAY' class="rounded border border-slate-300 px-2 py-1 text-xs text-slate-700" />
            <button type="submit" class="rounded-xl border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">Pay outstanding</button>
        </form>
    </x-slot>

    <x-slot name="above">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Vendor details</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 text-sm text-slate-700">
                <p><span class="text-slate-500">Category:</span> {{ $vendor->category ?: '—' }}</p>
                <p><span class="text-slate-500">Status:</span> {{ ucfirst((string) $vendor->status) }}</p>
                <p><span class="text-slate-500">Phone:</span> {{ $vendor->phone ?: '—' }}</p>
                <p><span class="text-slate-500">Email:</span> {{ $vendor->email ?: '—' }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-900">Payment history</p>
            @if (($paymentEntries ?? collect())->isEmpty())
                <p class="mt-2 text-sm text-slate-600">No vendor payments recorded yet.</p>
            @else
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Reference</th>
                                <th class="px-3 py-2">Amount</th>
                                <th class="px-3 py-2">Notes</th>
                                <th class="px-3 py-2">Recorded by</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($paymentEntries as $entry)
                                <tr>
                                    <td class="px-3 py-2">{{ $entry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $entry->reference ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ \App\Services\Property\PropertyMoney::kes((float) $entry->amount) }}</td>
                                    <td class="px-3 py-2">{{ $entry->description ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $recorderNames[$entry->recorded_by_user_id] ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-slot>
</x-property.workspace>

