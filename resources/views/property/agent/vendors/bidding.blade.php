<x-property.workspace
    title="Job bidding"
    subtitle="Issue scoped RFQs to shortlisted vendors — attach photos, access window, and deadline."
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No bidding events"
    empty-hint="Create an RFQ draft to start collecting and tracking vendor quotes."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.vendors.bidding.create') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >
            Create RFQ
        </a>
    </x-slot>
</x-property.workspace>
