@php
    use App\Models\PropertyUnit;

    $propertyReadOnly = $unit->property?->isManagementReadOnly() ?? false;
    $propertyOffboarding = $unit->property?->isOffboarding() ?? false;
@endphp

<x-property.action-menu>
    <a href="{{ route('property.properties.show', $unit->property_id, absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">View property</a>
    @if ($propertyReadOnly || $propertyOffboarding)
        <a href="{{ route('property.properties.offboarding', $unit->property_id, absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-slate-700/50">View offboarding</a>
    @else
        <a href="{{ route('property.units.edit', $unit, absolute: false) }}" data-turbo="false" class="block px-3 py-2 text-xs text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-700/50">Edit unit</a>
        <a href="{{ route('property.tenants.leases', array_filter(['property_id' => $unit->property_id, 'unit_id' => $unit->id, 'open_create' => 1]), absolute: false) }}" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-slate-700/50">Add lease</a>

        @if ($unit->status === PropertyUnit::STATUS_VACANT)
            <a href="{{ route('property.listings.create', ['selected_unit' => $unit->id], absolute: false) }}#listing-publish" data-turbo-frame="property-main" class="block px-3 py-2 text-xs text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-700/50">Edit listing</a>
        @endif

        @foreach (PropertyUnit::statuses() as $targetStatus)
            @if ($targetStatus !== $unit->status)
                <form method="post" action="{{ route('property.units.status', $unit, absolute: false) }}" data-turbo-frame="property-main">
                    @csrf
                    <input type="hidden" name="status" value="{{ $targetStatus }}" />
                    <button type="submit" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-700/50">Mark {{ PropertyUnit::statusLabel($targetStatus) }}</button>
                </form>
            @endif
        @endforeach

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
</x-property.action-menu>
