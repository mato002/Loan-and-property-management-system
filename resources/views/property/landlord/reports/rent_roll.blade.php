<x-property.workspace
    title="Rent roll"
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No active leases"
    empty-hint=""
/>
