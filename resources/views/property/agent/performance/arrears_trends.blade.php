<x-property.workspace
    title="Arrears trends"
    subtitle="Open invoice balances grouped by how long past due — operational aging view."
    back-route="property.performance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cohort data"
    empty-hint="Cohort ledger can be added later; buckets above reflect current overdue exposure."
>
    <x-property.chart-bar
        title="Aging buckets (balance due)"
        value-format="kes"
        :series="$agingBars ?? []"
    />
</x-property.workspace>
