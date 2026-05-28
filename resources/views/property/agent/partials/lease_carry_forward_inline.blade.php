@php
    $openingArrearsRows = array_values((array) ($openingArrearsRows ?? []));
    $existingRentArrears = $existingRentArrears ?? null;
    $existingRentArrearsPeriod = $existingRentArrearsPeriod ?? null;
    $existingRentArrearsDetails = $existingRentArrearsDetails ?? null;
    $lease = $lease ?? null;
    $rowsId = $rowsId ?? 'opening-arrears-inline-rows';
    $addLineButtonId = $addLineButtonId ?? 'open-arrears-line-inline-edit';
@endphp

<div class="space-y-3" data-lease-carry-forward-inline>
    <input type="hidden" name="carry_forward_touched" value="0" />
    <p class="text-xs text-amber-800 dark:text-amber-300">These fields submit with <strong>Save changes</strong> (no separate Done step required).</p>
    <button type="button" id="{{ $addLineButtonId }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-800/20 dark:text-amber-300">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        Add charge line
    </button>
    <div class="overflow-x-auto rounded-xl border border-amber-200/80 bg-white/70 dark:bg-gray-900/40">
        <table class="w-full table-fixed text-sm min-w-[640px]">
            <thead class="bg-amber-50 text-left text-xs font-semibold text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
                <tr>
                    <th class="px-3 py-2">Charge type</th>
                    <th class="px-3 py-2">Specific charge</th>
                    <th class="px-3 py-2">Period</th>
                    <th class="px-3 py-2">Amount (KES)</th>
                    <th class="px-3 py-2">Action</th>
                </tr>
            </thead>
            <tbody id="{{ $rowsId }}">
                @foreach ($openingArrearsRows as $idx => $row)
                    @if (! is_array($row))
                        @continue
                    @endif
                    <tr class="opening-arrears-row border-t border-amber-100 dark:border-amber-800/40">
                        <td class="px-3 py-2">
                            <select name="opening_arrears[{{ $idx }}][charge_type]" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                                <option value="water" @selected(($row['charge_type'] ?? '') === 'water')>Water</option>
                                <option value="electricity" @selected(($row['charge_type'] ?? '') === 'electricity')>Electricity</option>
                                <option value="service" @selected(($row['charge_type'] ?? '') === 'service')>Service</option>
                                <option value="garbage" @selected(($row['charge_type'] ?? '') === 'garbage')>Garbage</option>
                                <option value="other" @selected(($row['charge_type'] ?? '') === 'other')>Other</option>
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" name="opening_arrears[{{ $idx }}][specific_charge]" value="{{ $row['specific_charge'] ?? '' }}" placeholder="e.g. Water meter bill" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        </td>
                        <td class="px-3 py-2">
                            <input type="month" name="opening_arrears[{{ $idx }}][period]" value="{{ $row['period'] ?? '' }}" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        </td>
                        <td class="px-3 py-2">
                            <input type="number" name="opening_arrears[{{ $idx }}][amount]" value="{{ $row['amount'] ?? '' }}" step="0.01" min="0" placeholder="0.00" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                        </td>
                        <td class="px-3 py-2">
                            <button type="button" class="remove-opening-arrears-row min-h-[44px] rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300">Remove</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent (KES)</label>
            <input type="number" step="0.01" min="0" name="opening_rent_arrears" value="{{ old('opening_rent_arrears', $existingRentArrears) }}" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('opening_rent_arrears')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent period</label>
            <input type="month" name="opening_rent_arrears_period" value="{{ old('opening_rent_arrears_period', $existingRentArrearsPeriod) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('opening_rent_arrears_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent details</label>
            <input type="text" name="opening_rent_arrears_details" value="{{ old('opening_rent_arrears_details', $existingRentArrearsDetails) }}" placeholder="e.g. Jan-Mar unpaid rent balance" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            @error('opening_rent_arrears_details')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Manual total override (optional)</label>
            <input type="number" step="0.01" min="0" name="opening_arrears_manual_total" value="{{ old('opening_arrears_manual_total', $lease?->opening_arrears_manual_total) }}" placeholder="Auto-sums lines if left blank" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
            <input type="date" name="opening_arrears_as_of_date" value="{{ old('opening_arrears_as_of_date', optional($lease?->opening_arrears_as_of_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward note (optional)</label>
        <input type="text" name="opening_arrears_note" value="{{ old('opening_arrears_note', $lease?->opening_arrears_note) }}" placeholder="Source / reason for brought-forward debt" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
    </div>
</div>
