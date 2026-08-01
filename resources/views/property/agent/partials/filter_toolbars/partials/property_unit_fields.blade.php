@php
    $propertyId = (int) ($filters['property_id'] ?? 0);
    $unitLabel = fn ($unit) => $propertyId > 0
        ? (string) ($unit->label ?? $unit['label'] ?? '')
        : trim(((string) ($unit->property?->name ?? $unit['property_name'] ?? 'Unknown')).'/'.((string) ($unit->label ?? $unit['label'] ?? '')), '/');
@endphp

<x-property.filter-field type="select"
    name="property_id"
    label="Property"
    :options="collect([['value' => '0', 'label' => 'Property: All']])
        ->merge(collect($properties ?? [])->map(fn ($property) => ['value' => (string) $property->id, 'label' => (string) $property->name]))
        ->all()"
    :value="(string) ($filters['property_id'] ?? '0')"
/>
<x-property.filter-field type="select"
    name="unit_id"
    label="Unit"
    :options="collect([['value' => '0', 'label' => 'Unit: All']])
        ->merge(collect($units ?? [])->map(fn ($unit) => ['value' => (string) $unit->id, 'label' => $unitLabel($unit)]))
        ->all()"
    :value="(string) ($filters['unit_id'] ?? '0')"
/>
