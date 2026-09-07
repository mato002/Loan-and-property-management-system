<x-property.workspace
    :title="$title"
    :subtitle="$subtitle"
    :back-route="$backRoute"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :empty-title="$emptyTitle ?? 'No deposits found'"
    :empty-hint="$emptyHint ?? 'Try clearing filters, or deposits will appear once leases record a held amount.'"
>
    <x-slot name="actions">
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.reports', [
            'searchPlaceholder' => 'Search tenant, property, unit…',
            'fromLabel' => 'Lease from',
            'toLabel' => 'Lease to',
            'reportFilterExtrasView' => 'property.agent.partials.filter_toolbars.tenant_deposits_extras',
        ])
    </x-slot>

    @if (isset($paginator) && method_exists($paginator, 'links'))
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
            </p>
            {{ $paginator->links() }}
        </div>
    @endif
</x-property.workspace>
