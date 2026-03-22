<x-property.workspace
    title="Cash flow"
    subtitle="Ledger credits and debits — monthly trend and running detail."
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cash rows"
    empty-hint="Ledger entries will appear here as credits and debits are posted."
>
    <div class="grid gap-4 lg:grid-cols-2 w-full min-w-0">
        <x-property.chart-line-dual
            title="Cash in vs out by month"
            label-a="Credits (in)"
            label-b="Debits (out)"
            :series="$cashInOutDual ?? []"
        />
        <x-property.chart-bar
            title="Net ledger flow by month"
            value-format="kes"
            :series="$cashNetBars ?? []"
        />
    </div>
    @if (! empty($cashCumulative))
        <div class="mt-4">
            <x-property.chart-bar
                title="End-of-month ledger balance"
                value-format="kes"
                :series="array_map(static fn ($c) => ['label' => $c['label'], 'value' => $c['balance']], $cashCumulative)"
            />
        </div>
    @endif
</x-property.workspace>
