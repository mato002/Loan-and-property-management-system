<x-property.filter-field type="select"
    name="deposit"
    label="Deposit"
    empty-option="Deposit: All"
    :options="[
        ['value' => 'held', 'label' => 'Held (balance > 0)'],
        ['value' => 'zero', 'label' => 'Zero / none'],
    ]"
    :value="$filters['deposit'] ?? ''"
/>
<x-property.filter-field type="select"
    name="status"
    label="Lease status"
    empty-option="Status: All"
    :options="[
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'expired', 'label' => 'Expired'],
        ['value' => 'terminated', 'label' => 'Terminated'],
        ['value' => 'draft', 'label' => 'Draft'],
    ]"
    :value="$filters['status'] ?? ''"
/>
<x-property.filter-field type="select"
    name="sort"
    label="Sort"
    :options="[
        ['value' => 'tenant', 'label' => 'Tenant'],
        ['value' => 'property', 'label' => 'Property'],
        ['value' => 'unit', 'label' => 'Unit'],
        ['value' => 'deposit', 'label' => 'Deposit paid'],
        ['value' => 'balance', 'label' => 'Balance'],
    ]"
    :value="$filters['sort'] ?? 'tenant'"
/>
<x-property.filter-field type="select"
    name="dir"
    label="Order"
    :options="[
        ['value' => 'asc', 'label' => 'Ascending'],
        ['value' => 'desc', 'label' => 'Descending'],
    ]"
    :value="$filters['dir'] ?? 'asc'"
/>
