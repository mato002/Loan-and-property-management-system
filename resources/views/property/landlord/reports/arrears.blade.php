<x-property.workspace
    title="Arrears aging"
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No outstanding invoices"
    empty-hint=""
/>
