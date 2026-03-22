<x-property.workspace
    title="Lead tracking"
    subtitle="Scaffold only — capture leads via workspace forms; not tied to a separate listing entity (listings = vacant units + publish)."
    back-route="property.listings.index"
    :stats="[
        ['label' => 'Open leads', 'value' => '0', 'hint' => 'Active'],
        ['label' => 'Conversion', 'value' => '—', 'hint' => 'Last 90d'],
    ]"
    :columns="['Lead', 'Unit', 'Source', 'Stage', 'Owner', 'Next action', 'Updated']"
    empty-title="No leads"
    empty-hint="Capture name, phone, and preferred viewing window; optional link to public listing URL."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'listings-import-leads') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Import CSV</a>
        <a
            href="{{ route('property.workspace.form.show', 'listings-add-lead') }}"
            class="inline-flex justify-center items-center rounded-xl bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 w-full sm:w-auto"
        >Add lead</a>
    </x-slot>
</x-property.workspace>
