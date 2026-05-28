<x-property.workspace
    title="Account mapping"
    subtitle="Configure posting slots for invoices, payments, and maintenance."
    back-route="property.accounting.index"
    :stats="[['label' => 'Mappings', 'value' => '5', 'hint' => 'Core posting slots']]"
    :columns="[]"
    :table-rows="[]"
>
    <form method="post" action="{{ route('property.accounting.settings.account_map.save') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3 max-w-4xl">
        @csrf
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div><label class="block text-xs font-medium text-slate-600">Invoice → Accounts Receivable</label><input type="text" name="accounts_receivable" value="{{ old('accounts_receivable', $accountMap['accounts_receivable'] ?? 'Accounts Receivable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="block text-xs font-medium text-slate-600">Invoice → Rental Income</label><input type="text" name="rental_income" value="{{ old('rental_income', $accountMap['rental_income'] ?? 'Rental Income') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="block text-xs font-medium text-slate-600">Payment → Cash Account</label><input type="text" name="cash_bank" value="{{ old('cash_bank', $accountMap['cash_bank'] ?? 'Cash / Bank') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="block text-xs font-medium text-slate-600">Maintenance → Expense Account</label><input type="text" name="maintenance_expense" value="{{ old('maintenance_expense', $accountMap['maintenance_expense'] ?? 'Maintenance Expense') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
            <div><label class="block text-xs font-medium text-slate-600">Payables → Accounts Payable</label><input type="text" name="accounts_payable" value="{{ old('accounts_payable', $accountMap['accounts_payable'] ?? 'Accounts Payable') }}" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" /></div>
        </div>
        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save mapping</button>
    </form>
</x-property.workspace>

