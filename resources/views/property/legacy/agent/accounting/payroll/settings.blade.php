<x-property.workspace
    title="Payroll settings"
    subtitle="Configure default payroll account mapping for postings."
    back-route="property.accounting.payroll"
    :stats="[]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="above">
        <form method="post" action="{{ route('property.accounting.payroll.settings.save') }}" class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm space-y-3 max-w-3xl">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Expense account</label>
                    <input type="text" name="expense_account" value="{{ old('expense_account', $settings['expense_account'] ?? 'Payroll Expense') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('expense_account')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Payable account</label>
                    <input type="text" name="payable_account" value="{{ old('payable_account', $settings['payable_account'] ?? 'Payroll Payable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('payable_account')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Deductions payable account</label>
                    <input type="text" name="deductions_payable_account" value="{{ old('deductions_payable_account', $settings['deductions_payable_account'] ?? 'Payroll Deductions Payable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('deductions_payable_account')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Default posting day</label>
                    <input type="number" min="1" max="28" name="default_posting_day" value="{{ old('default_posting_day', $settings['default_posting_day'] ?? 28) }}" class="mt-1 w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-900 text-sm px-3 py-2" />
                    @error('default_posting_day')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 mt-1">
                        <input type="checkbox" name="lock_processed_periods" value="1" class="rounded border-slate-300" @checked((bool) old('lock_processed_periods', $settings['lock_processed_periods'] ?? false)) />
                        Lock processed periods (disallow back-posting edits after processing)
                    </label>
                </div>
            </div>
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save settings</button>
        </form>
    </x-slot>
</x-property.workspace>
