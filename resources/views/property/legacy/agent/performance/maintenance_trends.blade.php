<x-property.workspace
    title="Maintenance trends"
    subtitle="Completed maintenance with quote amounts — monthly spend from job completions."
    back-route="property.performance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No maintenance history"
    empty-hint="Close jobs with quote amounts to populate this chart."
>
    <x-property.chart-bar
        title="Completed maintenance (quoted) by month"
        value-format="kes"
        :series="$maintBars ?? []"
    />
</x-property.workspace>
