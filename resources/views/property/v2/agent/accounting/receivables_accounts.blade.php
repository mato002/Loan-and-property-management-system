<x-property.workspace
    title="Accounts receivable"
    subtitle="Tenant balances with overdue visibility and payment actions."
    back-route="property.accounting.index"
    :stats="[['label' => 'Open invoices', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Receivable items']]"
    :columns="['Tenant', 'Property', 'Unit', 'Balance', 'Overdue Days', 'Last Payment', 'Actions']"
    :table-rows="$rows->getCollection()->map(function($inv) {
        $balance = max(0, (float) $inv->amount - (float) $inv->amount_paid);
        $overdueDays = $inv->due_date ? max(0, \Carbon\Carbon::parse($inv->due_date)->diffInDays(now(), false) * -1) : 0;
        return [
            optional($inv->tenant)->name ?? '—',
            optional(optional($inv->unit)->property)->name ?? '—',
            optional($inv->unit)->label ?? '—',
            \App\Services\Property\PropertyMoney::kes($balance),
            (string) $overdueDays,
            \App\Services\Property\PropertyMoney::kes((float) $inv->amount_paid),
            new \Illuminate\Support\HtmlString(
                '<div class=\'flex gap-2\'>'.
                '<a class=\'text-indigo-600 hover:text-indigo-700\' href=\''.route('property.tenants.statement', ['tenant' => $inv->pm_tenant_id]).'\'>View tenant ledger</a>'.
                '<a class=\'text-amber-700 hover:text-amber-800\' href=\''.route('property.communications.messages').'\'>Send reminder</a>'.
                '<a class=\'text-emerald-700 hover:text-emerald-800\' href=\''.route('property.revenue.payments').'\'>Allocate payment</a>'.
                '</div>'
            ),
        ];
    })->all()"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.payments') }}" class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Allocate payment</a>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.accounting.receivables.accounts') }}" class="flex flex-wrap gap-2">
            <select name="property_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Property: All</option>
                @foreach (($properties ?? collect()) as $p)
                    <option value="{{ $p->id }}" @selected((int) ($filters['propertyId'] ?? 0) === (int) $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            <select name="tenant_id" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Tenant: All</option>
                @foreach (($tenants ?? collect()) as $t)
                    <option value="{{ $t->id }}" @selected((int) ($filters['tenantId'] ?? 0) === (int) $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            <select name="overdue" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Overdue: All</option>
                <option value="overdue" @selected(($filters['overdue'] ?? '') === 'overdue')>Overdue only</option>
                <option value="current" @selected(($filters['overdue'] ?? '') === 'current')>Current only</option>
            </select>
            <input type="number" step="0.01" name="min_balance" placeholder="Min balance" value="{{ $filters['minBalance'] ?? '' }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-36" />
            <input type="number" step="0.01" name="max_balance" placeholder="Max balance" value="{{ $filters['maxBalanceRaw'] ?? '' }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm w-36" />
            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">Apply</button>
        </form>
    </x-slot>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>

