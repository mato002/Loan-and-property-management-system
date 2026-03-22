<x-property.workspace
    title="Vendor performance"
    subtitle="On-time completion proxy (done / all jobs) vs quoted volume — bubble size reflects job count."
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No performance history"
    empty-hint="Assign vendors to jobs and close work to build this view."
>
    <x-property.chart-scatter
        title="Vendor scatter"
        :points="$scatterPoints ?? []"
    />
</x-property.workspace>
