<x-property.workspace
    :legacy-toolbar="false"
    :show-search="false"
    title="Tenant statements"
    subtitle="Statement access — closing balance uses canonical billable AR; collections use allocation totals."
    back-route="property.accounting.index"
    :stats="[['label' => 'Tenants', 'value' => (string) ($tenants->total() ?? 0), 'hint' => 'Statement-ready']]"
    :columns="['Tenant', 'Opening Balance', 'Invoices', 'Payments', 'Closing Balance', 'Actions']"
    :table-rows="collect($statementRows ?? [])->map(fn($row) => [
        (string) ($row['name'] ?? '—'),
        \App\Services\Property\PropertyMoney::kes((float) ($row['opening_balance'] ?? 0)),
        (string) ($row['invoice_count'] ?? 0),
        \App\Services\Property\PropertyMoney::kes((float) ($row['collections'] ?? 0)),
        \App\Services\Property\PropertyMoney::kes((float) ($row['closing_balance'] ?? 0)),
        new \Illuminate\Support\HtmlString(
            '<div class=\'flex gap-2\'>'.
            '<a class=\'text-indigo-600 hover:text-indigo-700\' href=\''.route('property.tenants.statement', ['tenant' => $row['tenant_id'] ?? 0]).'\'>Open statement</a>'.
            '<a class=\'text-slate-700 hover:text-slate-900\' data-turbo=\'false\' href=\''.route('property.tenants.statement', ['tenant' => $row['tenant_id'] ?? 0, 'export' => 'pdf']).'\'>Export PDF</a>'.
            '<a class=\'text-amber-700 hover:text-amber-800\' href=\''.route('property.communications.messages').'\'>Send statement</a>'.
            '<a class=\'text-emerald-700 hover:text-emerald-800\' href=\''.route('property.revenue.payments').'\'>Apply credit</a>'.
            '</div>'
        ),
    ])->all()"
>
    <x-slot name="toolbar">
        <x-property.filter-toolbar
            :action="route('property.accounting.receivables.tenant_statements', false)"
            :reset-url="route('property.accounting.receivables.tenant_statements', false)"
            drawer-label="Statement filters"
        >
            <x-slot name="primary">
                <x-property.filter-field type="search" name="q" placeholder="Name, phone, or account…" :value="$filters['q'] ?? ''" wide />
                <x-property.filter-field type="select" name="property_id" label="Property" empty-option="Property: All" :options="collect($properties ?? [])->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name])->all()" :value="(string) ($filters['property_id'] ?? '')" />
            </x-slot>
            <x-slot name="export">
                @include('property.agent.partials.export_dropdown', [
                    'route' => 'property.accounting.receivables.tenant_statements',
                    'query' => request()->except(['export', 'page']),
                ])
            </x-slot>
        </x-property.filter-toolbar>
    </x-slot>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.arrears') }}" class="inline-flex justify-center items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Send statement reminders</a>
    </x-slot>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $tenants])
    </x-slot>
</x-property.workspace>
