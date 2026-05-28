<x-property.workspace
    title="Vacancy trends"
    subtitle="Current vacant units by property and portfolio-level exposure from unit asking rents."
    back-route="property.performance.index"
    :stats="$stats"
    :columns="$columns ?? []"
    :table-rows="$tableRows ?? []"
>
    <x-property.chart-bar
        title="Vacant unit count by property (top 12)"
        value-format="number"
        :series="$vacancyByProperty ?? []"
    />
</x-property.workspace>
