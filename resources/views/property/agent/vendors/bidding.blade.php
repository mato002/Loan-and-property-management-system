<x-property.workspace
    title="Job bidding"
    subtitle="Issue scoped RFQs to shortlisted vendors — attach photos, access window, and deadline."
    back-route="property.vendors.index"
    :stats="[
        ['label' => 'Open RFQs', 'value' => '0', 'hint' => 'Collecting quotes'],
        ['label' => 'Awaiting award', 'value' => '0', 'hint' => 'Quotes in'],
        ['label' => 'Awarded (MTD)', 'value' => '0', 'hint' => 'Jobs created'],
    ]"
    :columns="['RFQ #', 'Property / unit', 'Scope', 'Deadline', 'Invited', 'Quotes', 'Status']"
    empty-title="No bidding events"
    empty-hint="Keep a fair audit: who was invited, when, and why a vendor won or lost."
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
