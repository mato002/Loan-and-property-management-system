@php
    $adjustments = $waterRateAdjustments ?? [];
    $adjustmentMonth = $billingReadiness['month'] ?? now()->format('Y-m');
    $adjustmentCount = count($adjustments);
@endphp

<div class="rounded-xl border border-violet-200 bg-violet-50/40 p-3 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-slate-900">Water rate corrections due</h4>
            <p class="mt-1 text-xs text-slate-600">
                Compares invoiced water for <span class="font-medium">{{ $adjustmentMonth }}</span> to
                <span class="font-medium">current property water rates</span> × recorded usage.
                Before invoicing, fix rates on the reading via <span class="font-medium">Edit</span> in Recorded readings.
            </p>
        </div>
        @if ($adjustmentCount > 0)
            <form method="post" action="{{ route('property.revenue.utilities.water_supplements.generate', absolute: false) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="billing_month" value="{{ $adjustmentMonth }}" />
                <input type="hidden" name="generate_all" value="1" />
                <div>
                    <label class="block text-xs text-slate-500">Due date (optional)</label>
                    <input type="date" name="due_date" class="mt-1 rounded-lg border border-slate-200 bg-white text-sm px-3 py-2" />
                </div>
                <button type="submit" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                    Bill all corrections ({{ $adjustmentCount }})
                </button>
            </form>
        @endif
    </div>

    @if ($adjustmentCount === 0)
        <p class="text-sm text-emerald-800">No under-billed water for this month at current rates.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Tenant</th>
                        <th class="px-3 py-2">Usage</th>
                        <th class="px-3 py-2">Reading amt</th>
                        <th class="px-3 py-2">At current rate</th>
                        <th class="px-3 py-2">Invoiced</th>
                        <th class="px-3 py-2">To bill</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($adjustments as $row)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ $row['property_name'] ?? '—' }} / {{ $row['unit_label'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['tenant_name'] ?? '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ number_format((float) ($row['units_used'] ?? 0), 3) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['reading_amount'] ?? 0)) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['expected_amount'] ?? 0)) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['invoiced_amount'] ?? 0)) }}</td>
                            <td class="px-3 py-2 tabular-nums font-semibold text-violet-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['bill_amount'] ?? 0)) }}</td>
                            <td class="px-3 py-2">
                                <form method="post" action="{{ route('property.revenue.utilities.water_supplements.generate', absolute: false) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="billing_month" value="{{ $adjustmentMonth }}" />
                                    <input type="hidden" name="reading_ids[]" value="{{ (int) ($row['reading_id'] ?? 0) }}" />
                                    <button type="submit" class="rounded-lg bg-violet-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-violet-700">Bill</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
