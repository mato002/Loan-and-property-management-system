@props([
    'unit',
    'arrears' => 0,
    'propertyId' => null,
])

@php
    use App\Models\PropertyUnit;

    $activeLease = $unit->leases->first();
    $tenant = $activeLease?->pmTenant;
    $propertyId = (int) ($propertyId ?? $unit->property_id);
    $unitArrears = (float) $arrears;
    $status = (string) $unit->status;
@endphp

<x-property.action-menu>
    <a href="{{ route('property.units.show', ['unit' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Open unit hub</a>
    <a href="{{ route('property.units.edit', ['unit' => $unit->id], false) }}" data-turbo="false" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Edit unit</a>

    @if ($status === PropertyUnit::STATUS_VACANT)
        <a href="{{ route('property.tenants.leases', ['property_id' => $propertyId, 'unit_id' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Assign tenant</a>
        <a href="{{ route('property.listings.create', ['selected_unit' => $unit->id], false) }}#listing-publish" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Publish listing</a>
    @else
        @if ($activeLease)
            <a href="{{ route('property.leases.edit', ['lease' => $activeLease->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Open lease</a>
        @endif
        @if ($tenant?->name)
            <a href="{{ route('property.tenants.show', $tenant, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">View tenant hub</a>
        @elseif ($status === PropertyUnit::STATUS_OCCUPIED)
            <span class="block px-3 py-2 text-xs text-amber-700 dark:text-amber-300">No active lease linked</span>
        @endif
        <a href="{{ route('property.tenants.leases', ['property_id' => $propertyId, 'unit_id' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Add / change lease</a>
    @endif

    @if ($unitArrears > 0)
        <a href="{{ route('property.revenue.arrears', ['q' => $unit->label], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">View arrears ({{ \App\Services\Property\PropertyMoney::kes($unitArrears) }})</a>
    @endif

    @foreach ([PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_OCCUPIED, PropertyUnit::STATUS_NOTICE] as $targetStatus)
        @if ($targetStatus !== $status)
            <form method="post" action="{{ route('property.units.status', ['unit' => $unit->id], false) }}" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="status" value="{{ $targetStatus }}" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Mark {{ ucfirst($targetStatus) }}</button>
            </form>
        @endif
    @endforeach

    <form
        method="post"
        action="{{ route('property.units.destroy', ['unit' => $unit->id], false) }}"
        data-swal-title="Remove this unit?"
        data-swal-confirm="Use this for demolished or invalid units. Deletion is blocked if the unit has lease, invoice, utility, or maintenance history."
        data-swal-confirm-text="Yes, remove unit"
    >
        @csrf
        @method('DELETE')
        <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">Remove unit</button>
    </form>
</x-property.action-menu>
