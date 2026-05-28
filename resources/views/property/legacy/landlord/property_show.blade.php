<x-property.workspace
    :title="$property->name"
    subtitle="Units under this property"
    back-route="property.landlord.properties"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No units yet"
    empty-hint="Units linked to this property will be listed here with their status."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.landlord.reports.income', ['property_id' => $property->id]) }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Income</a>
        <a
            href="{{ route('property.landlord.maintenance', ['property_id' => $property->id]) }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Maintenance</a>
        <a
            href="{{ route('property.landlord.reports.statement', ['property_id' => $property->id]) }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Statement</a>
    </x-slot>
</x-property.workspace>
