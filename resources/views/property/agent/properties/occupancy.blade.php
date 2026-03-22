<x-property.workspace
    title="Occupancy view"
    subtitle="Structural only — vacant vs occupied vs notice, with active tenant when leased."
    back-route="property.properties.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No units"
    empty-hint="Add properties and units to see occupancy across the portfolio."
></x-property.workspace>
