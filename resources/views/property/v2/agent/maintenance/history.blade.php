<x-property.workspace
    title="Maintenance history"
    subtitle="Closed work — done and cancelled jobs for audit and cost review."
    back-route="property.maintenance.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No closed jobs yet"
    empty-hint="When jobs are marked done or cancelled, they appear here."
></x-property.workspace>
