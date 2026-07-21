@php
    $formId = $formId ?? 'lease-form-wrapper';
    $additionalDeposits = $additionalDeposits ?? [];
    $openingArrearsRows = $openingArrearsRows ?? [];
@endphp

<x-property.modal
    show="showOptionalFieldsModal"
    close="showOptionalFieldsModal = false"
    name="lease-create-optional-fields"
    title="Utilities, deposits &amp; terms"
    max-width="3xl"
    :lease-submodal="true"
>
    <fieldset form="{{ $formId }}" data-lease-optional-panel class="min-w-0 border-0 p-0 m-0 space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Utility defaults</label>
                <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <table class="min-w-[640px] w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-2">Utility type</th>
                                <th class="px-3 py-2 whitespace-nowrap">Rate / unit</th>
                                <th class="px-3 py-2 whitespace-nowrap">Fixed (flat)</th>
                            </tr>
                        </thead>
                        <tbody id="utility-defaults-tbody"></tbody>
                    </table>
                    <p id="utility-defaults-empty" class="px-3 py-4 text-xs text-slate-500 hidden">Select a property and unit to load configured utility types.</p>
                </div>
                @error('utility_expenses')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('utility_expense_type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('utility_expense_rate')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Configured deposit lines</label>
                <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                    <div class="min-w-[760px] p-2">
                        <div class="grid gap-2 grid-cols-[2fr_1fr_2fr_auto] px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div>Deposit type</div>
                            <div>Amount</div>
                            <div>Rule details</div>
                            <div>Action</div>
                        </div>
                        <div id="additional-deposits-rows" class="mt-2 space-y-2">
                            @foreach ($additionalDeposits as $idx => $row)
                                <div class="grid gap-2 grid-cols-[2fr_1fr_2fr_auto] additional-deposit-row">
                                    <select form="{{ $formId }}" name="additional_deposits[{{ $idx }}][label]" class="additional-deposit-label rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 w-full">
                                        <option value="{{ $row['label'] ?? '' }}" selected>{{ $row['label'] ?? 'Select deposit type' }}</option>
                                    </select>
                                    <input form="{{ $formId }}" type="number" name="additional_deposits[{{ $idx }}][amount]" value="{{ $row['amount'] ?? '' }}" step="0.01" min="0" placeholder="Amount" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2 w-full" />
                                    <div class="deposit-line-meta rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">—</div>
                                    <button type="button" class="remove-deposit-row min-h-[44px] rounded-lg border border-red-200 px-2.5 py-2 text-xs font-medium text-red-700 hover:bg-red-50">Remove</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @error('additional_deposits')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('additional_deposits.*.label')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('additional_deposits.*.amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Terms summary</label>
                <textarea form="{{ $formId }}" name="terms_summary" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('terms_summary', $leaseTemplate ?? '') }}</textarea>
                @error('terms_summary')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>
    </fieldset>
    <x-slot name="footer">
        <button type="button" class="w-full min-h-[44px] rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="showOptionalFieldsModal = false">Done</button>
    </x-slot>
</x-property.modal>

<x-property.modal
    show="showOpeningArrearsModal"
    close="showOpeningArrearsModal = false"
    name="lease-create-opening-arrears"
    title="Carry-forward details"
    max-width="3xl"
    :lease-submodal="true"
>
    <fieldset form="{{ $formId }}" data-lease-arrears-panel class="min-w-0 border-0 p-0 m-0">
        <div id="opening-arrears-create-wrap" class="space-y-3">
            <button type="button" id="open-arrears-line-modal-create" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-amber-300 bg-amber-100/70 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-800/20 dark:text-amber-300">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Add charge line
            </button>
            <div class="overflow-x-auto rounded-xl border border-amber-200/80 bg-white/70">
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col class="w-[18%]" />
                        <col class="w-[28%]" />
                        <col class="w-[18%]" />
                        <col class="w-[18%]" />
                        <col class="w-[18%]" />
                    </colgroup>
                    <thead class="bg-amber-50 text-left text-xs font-semibold text-amber-900">
                        <tr>
                            <th class="px-3 py-2">Charge type</th>
                            <th class="px-3 py-2">Specific charge</th>
                            <th class="px-3 py-2">Period</th>
                            <th class="px-3 py-2">Amount (KES)</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody id="opening-arrears-create-rows">
                        @foreach ($openingArrearsRows as $idx => $row)
                            <tr class="opening-arrears-row border-t border-amber-100">
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
                                    <button type="button" class="remove-opening-arrears-row min-h-[44px] rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">Remove</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent (KES)</label>
                    <input type="number" step="0.01" min="0" name="opening_rent_arrears" value="{{ old('opening_rent_arrears') }}" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('opening_rent_arrears')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent period</label>
                    <input type="month" name="opening_rent_arrears_period" value="{{ old('opening_rent_arrears_period') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('opening_rent_arrears_period')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward rent details</label>
                    <input type="text" name="opening_rent_arrears_details" value="{{ old('opening_rent_arrears_details') }}" placeholder="e.g. Jan-Mar unpaid rent balance" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('opening_rent_arrears_details')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="rounded-xl border border-amber-200/80 bg-white/80 p-3 space-y-2">
                <div class="overflow-x-auto rounded-xl border border-amber-200/80 bg-white/70">
                    <table class="w-full text-sm">
                        <thead class="bg-amber-50 text-left text-xs font-semibold text-amber-900">
                            <tr>
                                <th class="px-3 py-2">Deposit type</th>
                                <th class="px-3 py-2">Amount (KES)</th>
                            </tr>
                        </thead>
                        <tbody id="opening-deposit-arrears-create-rows"></tbody>
                    </table>
                </div>
                <p id="opening-deposit-arrears-create-empty" class="hidden text-xs text-slate-500">No configured deposit rules for this property/unit.</p>
                @error('opening_deposit_arrears')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                @error('opening_deposit_arrears.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Manual total override (optional)</label>
                    <input type="number" step="0.01" min="0" name="opening_arrears_manual_total" value="{{ old('opening_arrears_manual_total') }}" placeholder="Auto-sums charge lines if left blank" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">As of date</label>
                    <input type="date" name="opening_arrears_as_of_date" value="{{ old('opening_arrears_as_of_date') }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Carry-forward note (optional)</label>
                <input type="text" name="opening_arrears_note" value="{{ old('opening_arrears_note') }}" placeholder="Source / reason for brought-forward debt" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
        </div>
    </fieldset>
    <x-slot name="footer">
        <button type="button" class="w-full min-h-[44px] rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="markLeaseCarryForwardTouched('{{ $formId }}'); showOpeningArrearsModal = false">Done</button>
    </x-slot>
</x-property.modal>

<x-property.modal
    show="showArrearsLineModal"
    close="showArrearsLineModal = false"
    name="lease-create-arrears-line"
    title="Add charge line"
    max-width="lg"
    :z-index="7110"
    :lease-submodal="true"
>
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Charge type</label>
            <select id="arrears-line-create-charge-type" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                <option value="water">Water</option>
                <option value="electricity">Electricity</option>
                <option value="service">Service</option>
                <option value="garbage">Garbage</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Period (YYYY-MM)</label>
            <input id="arrears-line-create-period" type="month" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Specific charge</label>
            <input id="arrears-line-create-specific-charge" type="text" placeholder="e.g. Water meter bill" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Amount (KES)</label>
            <input id="arrears-line-create-amount" type="number" step="0.01" min="0" placeholder="0.00" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
        </div>
    </div>
    <x-slot name="footer">
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" id="cancel-arrears-line-modal-create" class="min-h-[44px] rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium" @click="showArrearsLineModal = false">Cancel</button>
            <button type="button" id="save-arrears-line-modal-create" class="min-h-[44px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add line</button>
        </div>
    </x-slot>
</x-property.modal>
