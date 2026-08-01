@php
    $showNewListingModal = isset($errors) && $errors->has('property_unit_id');
@endphp

<x-property.crud-shell
    :in-property-form-modal="$inPropertyFormModal ?? false"
    title="Setup a public listing"
    back-route="property.listings.index"
    :stats="$stats"
    :columns="[]"
    :compact-list="true"
    :show-search="false"
    :legacy-toolbar="false"
>
    <x-slot name="pageModalsAttributes" x-data="{ showNewListing: @json($showNewListingModal) }"></x-slot>

    @if ($vacantUnits->isNotEmpty())
        <x-slot name="actions">
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                data-property-modal-open="showNewListing"
                @click="showNewListing = true"
            >
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>New listing</span>
            </button>
            <a
                href="{{ route('property.properties.units', absolute: false) }}"
                data-turbo-frame="property-main"
                data-property-nav="property.properties.units"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50"
            >
                Properties → Units
            </a>
        </x-slot>

        <x-slot name="modals">
            <x-property.modal
                show="showNewListing"
                close="showNewListing = false"
                name="listing-new"
                title="Start a listing"
                max-width="2xl"
            >
                @include('property.agent.listings.partials.new_listing_modal')
            </x-property.modal>
        </x-slot>
    @endif

    @include('property.agent.listings.partials.create_workspace')
</x-property.crud-shell>
