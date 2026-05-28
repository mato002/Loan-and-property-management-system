@php
    $dual = [];
    foreach ($trend ?? [] as $t) {
        $dual[] = [
            'label' => $t['label'],
            'a' => $t['collected'],
            'b' => $t['billed'],
        ];
    }
@endphp

<x-property.workspace
    title="Rent collection rate"
    subtitle="Billed vs collected by month; MTD rate vs a 95% target (adjust in reporting rules later)."
    back-route="property.performance.index"
    :stats="$stats"
    :columns="$columns ?? []"
    :table-rows="$tableRows ?? []"
>
    <x-property.chart-line-dual
        title="Six-month trend — collected vs billed"
        label-a="Collected"
        label-b="Billed"
        :series="$dual"
    />
    <x-slot name="footer">
        <p>Drill into arrears from the Revenue workspace; overdue cohorts are on the arrears trends screen.</p>
    </x-slot>
</x-property.workspace>
