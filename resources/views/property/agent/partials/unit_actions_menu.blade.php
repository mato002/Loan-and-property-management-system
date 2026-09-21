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

<x-property.action-menu width="w-56">
    @if ($showOpenUnitHub && \Illuminate\Support\Facades\Route::has('property.units.show'))
        <a href="{{ route('property.units.show', ['unit' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Open unit hub</a>
    @endif

    @if ($showViewProperty)
        <a href="{{ route('property.properties.show', ['property' => $unit->property_id, 'tab' => 'units'], absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">View property</a>
    @endif

    @if ($propertyReadOnly || $propertyOffboarding)
        <a href="{{ route('property.properties.offboarding', $unit->property_id, absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-slate-700/50">View offboarding</a>
    @else
        <p class="px-3 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Unit</p>
        <a href="{{ route('property.units.edit', $unit, absolute: false) }}" data-turbo="false" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Edit unit</a>
        <a href="{{ route('property.properties.show', ['property' => $propertyId, 'tab' => 'units'], absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Units on property</a>
        <a href="{{ route('property.maintenance.requests', ['unit_id' => $unit->id], absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Maintenance</a>
        <a href="{{ route('property.revenue.utilities', ['unit_id' => $unit->id], absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Utilities / water</a>

        <p class="px-3 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Occupancy</p>
        @if (UnitListPresentation::canOpenLease($hasActiveLease) && $activeLease)
            <a href="{{ route('property.leases.show', ['lease' => $activeLease->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">View lease</a>
            <a href="{{ route('property.leases.edit', ['lease' => $activeLease->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Edit lease</a>
        @endif

        @if (UnitListPresentation::canAssignLease($unit, $hasActiveLease))
            <a href="{{ route('property.tenants.leases', array_filter(['property_id' => $propertyId, 'unit_id' => $unit->id, 'open_create' => 1]), absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">{{ $unit->status === PropertyUnit::STATUS_VACANT ? 'Assign tenant' : 'Add lease' }}</a>
        @endif

        @if (UnitListPresentation::canPublishListing($unit, $hasActiveLease))
            <a href="{{ route('property.listings.create', ['selected_unit' => $unit->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Add photos / listing</a>
        @endif

        @if ($tenant)
            <a href="{{ route('property.tenants.show', $tenant, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">View tenant</a>
            <a href="{{ route('property.tenants.edit', $tenant, false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Edit tenant</a>
            <a href="{{ route('property.tenants.statement', ['tenant' => $tenant->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Tenant statement</a>
            <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Notices / move-out</a>
        @elseif (UnitListPresentation::shouldShowMissingLeaseWarning($unit, $hasActiveLease))
            <span class="block px-3 py-2 text-xs text-amber-700 dark:text-amber-300">No active lease linked</span>
        @endif

        <p class="px-3 pt-2 pb-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Collections</p>
        <a href="{{ route('property.revenue.invoices', ['unit_id' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Invoices</a>
        <a href="{{ route('property.revenue.payments', ['unit_id' => $unit->id], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Payments</a>
        @if ($unitArrears > 0)
            <a href="{{ route('property.revenue.arrears', ['q' => $unit->label], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-slate-700/50">View arrears ({{ \App\Services\Property\PropertyMoney::kes($unitArrears) }})</a>
        @else
            <a href="{{ route('property.revenue.arrears', ['q' => $unit->label], false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Arrears</a>
        @endif

        @foreach ($statusTransitions as $targetStatus)
            <form method="post" action="{{ route('property.units.status', $unit, absolute: false) }}" data-turbo-frame="property-main">
                @csrf
                <input type="hidden" name="status" value="{{ $targetStatus }}" />
                <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Mark {{ PropertyUnit::statusLabel($targetStatus) }}</button>
            </form>
        @endforeach

        @if ($hasActiveLease && $activeLease)
            <form
                method="post"
                action="{{ route('property.leases.terminate', $activeLease, absolute: false) }}"
                data-turbo-frame="property-main"
                data-swal-title="End this lease?"
                data-swal-confirm="Terminate the lease on {{ $unit->label }}? The unit can then be marked vacant."
                data-swal-confirm-text="Yes, terminate"
            >
                @csrf
                <button type="submit" class="block w-full border-t border-slate-100 px-3 py-2 text-left text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-slate-700 dark:text-amber-300 dark:hover:bg-slate-700/50">Terminate lease</button>
            </form>
        @endif

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
