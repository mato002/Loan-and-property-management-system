<x-property.workspace
    title="Rent roll"
    subtitle="Active leases by unit — scheduled rent vs paid vs balance."
    back-route="property.revenue.index"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No rent roll lines yet"
    empty-hint="Add properties, units, active leases, invoices, and payments to populate this grid."
>
    <x-slot name="actions">
        <span class="inline-flex items-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">Live data</span>
    </x-slot>
    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.rent_roll', get_defined_vars())
    </x-slot>
    <x-slot name="footer">
        @isset($paginator)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">
                    Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} rent line(s)
                </p>
                {{ $paginator->links() }}
            </div>
        @endisset
    </x-slot>
</x-property.workspace>
