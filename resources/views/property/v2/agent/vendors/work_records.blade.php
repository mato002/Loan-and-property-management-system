<x-property.workspace
    title="Before / after work records"
    subtitle="Photo timelines, measurements, and sign-off — evidence for landlords and warranty claims."
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No work records"
    empty-hint="Mark maintenance jobs as done and assign vendors to populate completed work records."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'vendors-work-records-zip') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Request ZIP bundle</a>
    </x-slot>
</x-property.workspace>
