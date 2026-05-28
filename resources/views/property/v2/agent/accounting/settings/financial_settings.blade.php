<x-property.workspace
    title="Financial settings"
    subtitle="Default accounts, commission, tax behavior, and posting rules."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'Default commission', 'value' => (string) $defaultCommission.'%', 'hint' => 'Current setting'],
        ['label' => 'Payroll expense account', 'value' => (string) ($payroll['expense_account'] ?? 'Payroll Expense'), 'hint' => 'Posting default'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Commission and tax</h3>
            <p class="mt-2 text-sm text-slate-600">Default commission: <strong>{{ $defaultCommission }}%</strong></p>
            <a href="{{ route('property.settings.commission') }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Open commission settings</a>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Payroll posting rules</h3>
            <p class="mt-2 text-sm text-slate-600">Expense account: <strong>{{ $payroll['expense_account'] ?? 'Payroll Expense' }}</strong></p>
            <p class="mt-1 text-sm text-slate-600">Payable account: <strong>{{ $payroll['payable_account'] ?? 'Payroll Payable' }}</strong></p>
            <a href="{{ route('property.accounting.payroll.settings') }}" class="mt-3 inline-flex rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Open payroll settings</a>
        </div>
    </div>
</x-property.workspace>

