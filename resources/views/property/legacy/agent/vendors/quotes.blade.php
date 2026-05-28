<x-property.workspace
    title="Quote comparison"
    subtitle="Normalize line items, warranty, and schedule — side-by-side decision support."
    back-route="property.vendors.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No quotes"
    empty-hint="Add a quote amount to maintenance jobs to populate this page."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'vendors-quote-matrix') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Open matrix view</a>
    </x-slot>
</x-property.workspace>
