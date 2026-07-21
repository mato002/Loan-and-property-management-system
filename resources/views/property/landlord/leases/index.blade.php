<x-property.workspace
    title="Leases"
    back-route="property.landlord.portfolio"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No leases"
    empty-hint=""
>
    <x-slot name="actions">
        <a href="{{ route('property.landlord.reports.rent_roll') }}" class="inline-flex rounded-xl border border-slate-200 px-3 py-2 text-sm">Rent roll</a>
    </x-slot>
</x-property.workspace>
