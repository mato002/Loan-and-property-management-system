@props([
    'unit',
    'propertyId' => null,
    'arrears' => 0,
    'showViewProperty' => false,
    'showOpenUnitHub' => false,
])

@php
    use App\Models\PropertyUnit;
    use App\Support\Property\UnitListPresentation;

    $propertyReadOnly = $unit->property?->isManagementReadOnly() ?? false;
    $propertyOffboarding = $unit->property?->isOffboarding() ?? false;
    $activeLease = $unit->relationLoaded('leases') ? $unit->leases->first() : null;
    $hasActiveLease = $activeLease !== null;
    $tenant = $activeLease?->pmTenant;
    $propertyId = (int) ($propertyId ?? $unit->property_id);
    $unitArrears = (float) $arrears;
    $statusTransitions = UnitListPresentation::allowedStatusTransitions($unit, $hasActiveLease);
@endphp

<x-property.action-menu>
    @if ($showOpenUnitHub && \Illuminate\Support\Facades\Route::has('property.units.show'))
        <a href="{{ route('property.units.show', ['unit' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Open unit hub</a>
    @endif

    @if ($showViewProperty)
        <a href="{{ route('property.properties.show', $unit->property_id, absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">View property</a>
    @endif

    @if ($propertyReadOnly || $propertyOffboarding)
        <a href="{{ route('property.properties.offboarding', $unit->property_id, absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-slate-700/50">View offboarding</a>
    @else
        <a href="{{ route('property.units.edit', $unit, absolute: false) }}" data-turbo="false" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Edit unit</a>

        @if (UnitListPresentation::canOpenLease($hasActiveLease) && $activeLease)
            <a href="{{ route('property.leases.edit', ['lease' => $activeLease->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Open lease</a>
        @endif

        @if (UnitListPresentation::canAssignLease($unit, $hasActiveLease))
            <a href="{{ route('property.tenants.leases', array_filter(['property_id' => $propertyId, 'unit_id' => $unit->id, 'open_create' => 1]), absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">{{ $unit->status === PropertyUnit::STATUS_VACANT ? 'Assign tenant' : 'Add lease' }}</a>
        @endif

        @if (UnitListPresentation::canPublishListing($unit, $hasActiveLease))
            <a href="{{ route('property.listings.create', ['selected_unit' => $unit->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">{{ $unit->public_listing_published ? 'Edit listing' : 'Publish listing' }}</a>
        @endif

        @if ($tenant?->name)
            <a href="{{ route('property.tenants.profiles', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">View tenant</a>
        @elseif (UnitListPresentation::shouldShowMissingLeaseWarning($unit, $hasActiveLease))
            <span class="block px-3 py-2 text-xs text-amber-700 dark:text-amber-300">No active lease linked</span>
        @endif

        @if ($unitArrears > 0)
            <a href="{{ route('property.revenue.arrears', ['q' => $unit->label], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">View arrears ({{ \App\Services\Property\PropertyMoney::kes($unitArrears) }})</a>
        @endif

        @foreach ($statusTransitions as $targetStatus)
            <form method="post" action="{{ route('property.units.status', $unit, absolute: false) }}" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="status" value="{{ $targetStatus }}" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Mark {{ PropertyUnit::statusLabel($targetStatus) }}</button>
            </form>
        @endforeach

        @if (UnitListPresentation::canDeleteUnit($unit, $hasActiveLease))
            <form
                method="post"
                action="{{ route('property.units.destroy', $unit, absolute: false) }}"
                data-turbo-frame="property-main"
                data-swal-title="Delete unit?"
                data-swal-confirm="Delete {{ $unit->label }} from {{ $unit->property->name }}? This cannot be undone."
                data-swal-confirm-text="Yes, delete"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">Delete</button>
            </form>
        @endif
    @endif
</x-property.action-menu>
