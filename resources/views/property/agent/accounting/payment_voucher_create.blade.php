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
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payment from A/C</label>
                <select name="paid_from" required class="mt-1 w-full min-h-[44px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2">
                    <option value="">Select the account paid from…</option>
                    @foreach ($paidFromAccounts as $account)
                        <option value="{{ $account }}" @selected(old('paid_from') === $account)>{{ $account }}</option>
                    @endforeach
                </select>
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

        <div id="voucher-lines">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Lines</h3>
            <p class="mt-1 text-xs text-slate-500">Leave a row blank if you are not using it. Tax and line total fill in from the amount and tax rate.</p>
            @error('lines')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            <div class="mt-3 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="min-w-[920px] w-full text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-left text-slate-500">
                        <tr>
                            <th class="px-2 py-2 font-medium">Ledger / property</th>
                            <th class="px-2 py-2 font-medium">Expense group</th>
                            <th class="px-2 py-2 font-medium">Svc / utility account</th>
                            <th class="px-2 py-2 font-medium">Description</th>
                            <th class="px-2 py-2 font-medium">Amount</th>
                            <th class="px-2 py-2 font-medium">Tax rate</th>
                            <th class="px-2 py-2 font-medium">Tax</th>
                            <th class="px-2 py-2 font-medium">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for ($i = 0; $i < 6; $i++)
                            <tr data-voucher-line class="border-t border-slate-100 dark:border-slate-800">
                                <td class="px-1 py-1">
                                    <select name="lines[{{ $i }}][property_id]" class="w-40 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-1">
                                        <option value="">—</option>
                                        @foreach ($properties as $property)
                                            <option value="{{ $property->id }}" @selected((string) old('lines.'.$i.'.property_id') === (string) $property->id)>{{ $property->code ? $property->code.' — ' : '' }}{{ $property->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-1 py-1">
                                    <select name="lines[{{ $i }}][expense_group]" class="w-32 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-1">
                                        <option value="">Default</option>
                                        @foreach ($expenseGroups as $value => $group)
                                            <option value="{{ $value }}" @selected(old('lines.'.$i.'.expense_group') === $value)>{{ $group['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-1 py-1">
                                    <select name="lines[{{ $i }}][utility_account]" class="w-40 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-1">
                                        <option value="">—</option>
                                        @foreach ($serviceAccounts as $account)
                                            <option value="{{ $account }}" @selected(old('lines.'.$i.'.utility_account') === $account)>{{ $account }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-1 py-1">
                                    <input type="text" name="lines[{{ $i }}][description]" value="{{ old('lines.'.$i.'.description') }}" class="w-36 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2" />
                                </td>
                                <td class="px-1 py-1">
                                    <input type="number" data-amount name="lines[{{ $i }}][amount]" min="0" step="0.01" value="{{ old('lines.'.$i.'.amount') }}" class="w-24 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2" />
                                </td>
                                <td class="px-1 py-1">
                                    <input type="number" data-rate name="lines[{{ $i }}][tax_rate]" min="0" max="100" step="0.01" value="{{ old('lines.'.$i.'.tax_rate') }}" class="w-16 min-h-[34px] rounded border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 px-2" />
                                </td>
                                <td class="px-1 py-1">
                                    <input type="text" data-tax readonly class="w-20 min-h-[34px] rounded border border-slate-200 bg-slate-50 px-2" />
                                </td>
                                <td class="px-1 py-1">
                                    <input type="text" data-line-total readonly class="w-24 min-h-[34px] rounded border border-slate-200 bg-slate-50 px-2 font-semibold" />
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-slate-200 bg-slate-50 dark:bg-slate-900/40">
                            <td colspan="6"></td>
                            <td class="px-2 py-2 text-right font-medium text-slate-500">Line totals</td>
                            <td class="px-2 py-2 font-semibold" id="voucher-line-totals">0.00</td>
                        </tr>
                        <tr>
                            <td colspan="6"></td>
                            <td class="px-2 py-2 text-right font-medium text-slate-500">Control balance</td>
                            <td class="px-2 py-2 font-semibold" id="voucher-control-balance">0.00</td>
                        </tr>
                    </tfoot>
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
    <script>
        (function () {
            const root = document.getElementById('voucher-lines');
            const control = document.querySelector('[name="control_amount"]');
            if (!root || !control) return;
            const money = (value) => (Math.round(value * 100) / 100).toFixed(2);
            const recalc = () => {
                let total = 0;
                root.querySelectorAll('[data-voucher-line]').forEach((line) => {
                    const amount = parseFloat(line.querySelector('[data-amount]')?.value || '0') || 0;
                    const rate = parseFloat(line.querySelector('[data-rate]')?.value || '0') || 0;
                    const tax = amount > 0 ? amount * rate / 100 : 0;
                    const lineTotal = amount > 0 ? amount + tax : 0;
                    const taxInput = line.querySelector('[data-tax]');
                    const totalInput = line.querySelector('[data-line-total]');
                    if (taxInput) taxInput.value = amount > 0 ? money(tax) : '';
                    if (totalInput) totalInput.value = amount > 0 ? money(lineTotal) : '';
                    total += lineTotal;
                });
                const controlAmount = parseFloat(control.value || '0') || 0;
                const totals = document.getElementById('voucher-line-totals');
                const balance = document.getElementById('voucher-control-balance');
                if (totals) totals.textContent = money(total);
                if (balance) balance.textContent = money(controlAmount - total);
            };
            root.addEventListener('input', recalc);
            control.addEventListener('input', recalc);
            recalc();
        })();
    </script>
</x-property.crud-shell>
