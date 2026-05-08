<x-property.workspace
    title="Tenant statements"
    subtitle="Statement access for opening balance, invoices, payments, and closing balance."
    back-route="property.accounting.index"
    :stats="[['label' => 'Tenants', 'value' => (string) ($tenants->total() ?? 0), 'hint' => 'Statement-ready']]"
    :columns="['Tenant', 'Opening Balance', 'Invoices', 'Payments', 'Closing Balance', 'Actions']"
    :table-rows="$tenants->getCollection()->map(fn($t) => [
        (string) $t->name,
        \App\Services\Property\PropertyMoney::kes(0),
        (string) ($t->invoices_count ?? 0),
        \App\Services\Property\PropertyMoney::kes((float) \App\Models\PmPayment::query()->where('pm_tenant_id', $t->id)->where('status', \App\Models\PmPayment::STATUS_COMPLETED)->sum('amount')),
        \App\Services\Property\PropertyMoney::kes((float) \App\Models\PmInvoice::query()->where('pm_tenant_id', $t->id)->selectRaw('COALESCE(SUM(amount - amount_paid),0) as bal')->value('bal')),
        new \Illuminate\Support\HtmlString(
            '<div class=\'flex gap-2\'>'.
            '<a class=\'text-indigo-600 hover:text-indigo-700\' href=\''.route('property.tenants.statement', ['tenant' => $t->id]).'\'>Open statement</a>'.
            '<a class=\'text-slate-700 hover:text-slate-900\' href=\''.route('property.tenants.statement', ['tenant' => $t->id]).'\'>Export PDF</a>'.
            '<a class=\'text-amber-700 hover:text-amber-800\' href=\''.route('property.communications.messages').'\'>Send statement</a>'.
            '<a class=\'text-emerald-700 hover:text-emerald-800\' href=\''.route('property.revenue.payments').'\'>Apply credit</a>'.
            '</div>'
        ),
    ])->all()"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.arrears') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Send statement reminders</a>
    </x-slot>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $tenants])
    </x-slot>
</x-property.workspace>

