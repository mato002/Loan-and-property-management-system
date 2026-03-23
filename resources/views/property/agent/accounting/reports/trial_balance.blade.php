<x-property.workspace
    title="Trial balance"
    subtitle="Debit and credit totals by account from property accounting entries."
    back-route="property.accounting.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No balances yet"
    empty-hint="Add entries first to generate a trial balance."
/>

