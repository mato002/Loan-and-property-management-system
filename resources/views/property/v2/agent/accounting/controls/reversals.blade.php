<x-property.workspace
    title="Reversals"
    subtitle="Original and reversal linkage with reason trail."
    back-route="property.accounting.index"
    :stats="[['label' => 'Reversal rows', 'value' => (string) ($rows->total() ?? 0), 'hint' => 'Audit control']]"
    :columns="['Original Entry', 'Reversal Entry', 'Date', 'Reason']"
    :table-rows="$rows->getCollection()->map(fn($r) => [
        '#'.(string) $r->reversal_of_id,
        '#'.(string) $r->id,
        optional($r->entry_date)->format('Y-m-d') ?? '—',
        (string) ($r->description ?? 'Manual reversal'),
    ])->all()"
>
    <x-slot name="footer">
        @include('property.agent.partials.pagination_controls', ['paginator' => $rows])
    </x-slot>
</x-property.workspace>

