<x-property.workspace
    title="Cash book"
    subtitle="Running balance for cash/bank tagged accounting entries."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cash book rows"
    empty-hint="Use account names containing 'cash' or 'bank' to populate this report."
/>

