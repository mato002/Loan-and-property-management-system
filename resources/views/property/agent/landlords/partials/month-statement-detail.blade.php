@php
    $unitStats = $settlement['unit_stats'] ?? [];
@endphp

<div class="space-y-4">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $settlement['property_name'] ?? 'Property' }}</h4>
            <p class="text-xs text-slate-500">
                {{ $settlement['period_label'] ?? '' }}
                @if (! empty($settlement['period_range_label']))
                    ({{ $settlement['period_range_label'] }})
                @endif
                · Occupied {{ (int) ($unitStats['units_occupied'] ?? 0) }} · Vacant {{ (int) ($unitStats['units_vacant'] ?? 0) }}
            </p>
        </div>
        <p class="text-sm font-semibold text-teal-800 dark:text-teal-200 tabular-nums">
            Net due {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['net_amount_due'] ?? 0)) }}
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
        <table class="w-full min-w-[920px] border-collapse text-xs">
            <thead class="bg-slate-100 dark:bg-slate-900/60 text-[10px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-2 py-2 text-left border border-slate-200 dark:border-slate-700">Unit</th>
                    <th class="px-2 py-2 text-left border border-slate-200 dark:border-slate-700">Tenant</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Per month</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">B/F rent</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">B/F garbage</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">B/F water</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Inv. rent</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Inv. garbage</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Inv. water</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Rec. rent</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Rec. garbage</th>
                    <th class="px-2 py-2 text-right border border-slate-200 dark:border-slate-700">Rec. water</th>
                </tr>
            </thead>
            <tbody>
                @forelse (($settlement['unit_lines'] ?? []) as $line)
                    <tr class="border-t border-slate-100 dark:border-slate-700/80">
                        <td class="px-2 py-1.5 border border-slate-200 dark:border-slate-700">{{ $line['unit_label'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 border border-slate-200 dark:border-slate-700">{{ $line['tenant_name'] ?? '—' }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['rent_per_month'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['rent_bf'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_bf'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['water_bf'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['rent_billed'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_billed'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['water_billed'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['rent_received'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['garbage_received'] ?? 0)) }}</td>
                        <td class="px-2 py-1.5 text-right tabular-nums border border-slate-200 dark:border-slate-700">{{ \App\Services\Property\PropertyMoney::kes((float) ($line['water_received'] ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-3 py-4 text-center text-slate-500">No units on this property.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900/40 p-3">
            <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Additions</h5>
            <ul class="space-y-1 text-sm">
                @forelse (($settlement['additions'] ?? []) as $addition)
                    <li class="flex justify-between gap-3">
                        <span class="text-slate-600 dark:text-slate-300">{{ $addition['description'] ?? 'Addition' }}</span>
                        <span class="tabular-nums text-emerald-700 font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($addition['amount'] ?? 0)) }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No additions in this month.</li>
                @endforelse
                <li class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 font-semibold">
                    <span>Total additions</span>
                    <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['additions_total'] ?? 0)) }}</span>
                </li>
            </ul>
            <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2 mt-4">Deductions / disbursements</h5>
            <ul class="space-y-1 text-sm">
                @forelse (($settlement['deductions'] ?? []) as $deduction)
                    <li class="flex justify-between gap-3">
                        <span class="text-slate-600 dark:text-slate-300">{{ $deduction['description'] ?? 'Deduction' }}</span>
                        <span class="tabular-nums text-rose-700 font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($deduction['amount'] ?? 0)) }}</span>
                    </li>
                @empty
                    <li class="text-slate-500">No deductions in this month.</li>
                @endforelse
                <li class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-2 font-semibold">
                    <span>Total deductions</span>
                    <span class="tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['deductions_total'] ?? 0)) }}</span>
                </li>
            </ul>
        </div>
        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900/40 p-3">
            <h5 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Statement summary</h5>
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Rent received</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['rent_received'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Total utility</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['utility_received'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Less management fee ({{ rtrim(rtrim(number_format((float) ($settlement['commission_percent'] ?? 0), 2, '.', ''), '0'), '.') }}%)</dt><dd class="tabular-nums font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['management_fee'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Add additions</dt><dd class="tabular-nums font-medium text-emerald-700">+ {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['additions_total'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Less deductions</dt><dd class="tabular-nums font-medium text-rose-700">− {{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['deductions_total'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Balance B/F</dt><dd class="tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['balance_brought_forward'] ?? 0)) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-slate-100 dark:border-slate-700 pt-2 mt-1"><dt class="font-semibold">Net amount due</dt><dd class="tabular-nums font-semibold text-teal-800 dark:text-teal-200">{{ \App\Services\Property\PropertyMoney::kes((float) ($settlement['net_amount_due'] ?? 0)) }}</dd></div>
            </dl>
        </div>
    </div>
</div>
