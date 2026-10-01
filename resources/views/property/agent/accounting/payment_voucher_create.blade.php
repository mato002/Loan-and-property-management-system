<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Record payment voucher"
    subtitle="Outgoing payment. The voucher number is assigned when you save. Line totals must equal the control amount."
    back-route="property.accounting.payables.payment_vouchers"
    :stats="[]"
    :columns="[]"
>
    <form method="post" action="{{ route('property.accounting.payables.payment_vouchers.store', absolute: false) }}" class="property-compact-panel max-w-5xl rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 sm:p-5 shadow-sm space-y-4">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Voucher date</label>
                <input type="date" name="txn_date" required value="{{ old('txn_date', now()->toDateString()) }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('txn_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payee</label>
                <input type="text" name="payee" required value="{{ old('payee') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('payee')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment from account</label>
                <input type="text" name="paid_from" required list="paid-from-accounts" value="{{ old('paid_from') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                <datalist id="paid-from-accounts">
                    @foreach ($paidFromAccounts as $account)
                        <option value="{{ $account }}"></option>
                    @endforeach
                </datalist>
                @error('paid_from')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment method</label>
                <select name="method" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select…</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method }}" @selected(old('method') === $method)>{{ $method }}</option>
                    @endforeach
                </select>
                @error('method')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Reference no.</label>
                <input type="text" name="ref_no" required value="{{ old('ref_no') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('ref_no')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Control amount</label>
                <input type="number" name="control_amount" required min="0.01" step="0.01" value="{{ old('control_amount') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                @error('control_amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cheque no.</label>
                <input type="text" name="cheque_no" value="{{ old('cheque_no') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Cheque date</label>
                <input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default expense group</label>
                <select name="expense_group" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    @foreach ($expenseGroups as $value => $group)
                        <option value="{{ $value }}" @selected(old('expense_group', 'operating') === $value)>{{ $group['label'] }}</option>
                    @endforeach
                </select>
                @error('expense_group')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Landlord (required for rent remittance)</label>
                <select name="landlord_id" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select landlord…</option>
                    @foreach ($landlords as $landlord)
                        <option value="{{ $landlord->id }}" @selected((string) old('landlord_id') === (string) $landlord->id)>{{ $landlord->name }}</option>
                    @endforeach
                </select>
                @error('landlord_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Narration</label>
                <input type="text" name="narration" value="{{ old('narration') }}" class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Lines</h3>
            <p class="mt-1 text-xs text-slate-500">Leave unused rows blank. Tax is calculated from the rate. Line total is amount plus tax.</p>
            @error('lines')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500">
                            <th class="py-2 pr-2">Property</th>
                            <th class="py-2 pr-2">Expense group</th>
                            <th class="py-2 pr-2">Utility account</th>
                            <th class="py-2 pr-2">Description</th>
                            <th class="py-2 pr-2">Amount</th>
                            <th class="py-2 pr-2">Tax %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < 6; $i++)
                            <tr>
                                <td class="py-1 pr-2">
                                    <select name="lines[{{ $i }}][property_id]" class="w-44 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2">
                                        <option value="">—</option>
                                        @foreach ($properties as $property)
                                            <option value="{{ $property->id }}" @selected((string) old('lines.'.$i.'.property_id') === (string) $property->id)>{{ $property->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1 pr-2">
                                    <select name="lines[{{ $i }}][expense_group]" class="w-40 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2">
                                        <option value="">Default</option>
                                        @foreach ($expenseGroups as $value => $group)
                                            <option value="{{ $value }}" @selected(old('lines.'.$i.'.expense_group') === $value)>{{ $group['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1 pr-2">
                                    <input type="text" name="lines[{{ $i }}][utility_account]" value="{{ old('lines.'.$i.'.utility_account') }}" class="w-32 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                                </td>
                                <td class="py-1 pr-2">
                                    <input type="text" name="lines[{{ $i }}][description]" value="{{ old('lines.'.$i.'.description') }}" class="w-48 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                                </td>
                                <td class="py-1 pr-2">
                                    <input type="number" name="lines[{{ $i }}][amount]" min="0" step="0.01" value="{{ old('lines.'.$i.'.amount') }}" class="w-28 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                                </td>
                                <td class="py-1 pr-2">
                                    <input type="number" name="lines[{{ $i }}][tax_rate]" min="0" max="100" step="0.01" value="{{ old('lines.'.$i.'.tax_rate') }}" class="w-20 min-h-[40px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-2" />
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Comments / notes</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">{{ old('notes') }}</textarea>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-800">Save voucher</button>
            <a href="{{ route('property.accounting.payables.payment_vouchers', absolute: false) }}" data-turbo-frame="property-main" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700">Cancel</a>
        </div>
    </form>
</x-property.crud-shell>
