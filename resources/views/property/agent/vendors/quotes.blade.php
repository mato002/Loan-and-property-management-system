<x-property.workspace
    title="Quote comparison"
    subtitle="Normalize line items, warranty, and schedule — side-by-side decision support."
    back-route="property.vendors.index"
    :stats="[
        ['label' => 'Open comparisons', 'value' => '0', 'hint' => 'Need decision'],
        ['label' => 'Median spread', 'value' => '—', 'hint' => 'High vs low quote'],
    ]"
    :columns="['RFQ', 'Vendor', 'Total', 'Line items', 'Lead time', 'Warranty', 'Score', 'Select']"
    empty-title="No quote sets"
    empty-hint="Scoring can weight price, SLA history, and landlord preference flags."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'vendors-quote-matrix') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Open matrix view</a>
    </x-slot>
</x-property.workspace>
