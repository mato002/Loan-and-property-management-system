<x-property.workspace
    :title="$title"
    :subtitle="$subtitle"
    :back-route="$backRoute"
    :legacy-toolbar="false"
    :show-search="false"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :empty-title="$emptyTitle ?? 'No records found'"
    :empty-hint="$emptyHint ?? 'This report will populate once there is transactional data.'"
>
    <x-slot name="actions">
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
        @include('property.agent.partials.table_export_dropdown', ['current' => true, 'formats' => \App\Support\TableExportLinks::STANDARD_FORMATS])
        <a href="{{ url()->current().'?'.http_build_query(array_filter(array_merge(request()->query(), ['export' => 'xls']))) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export XLS</a>
        <a href="{{ url()->current().'?'.http_build_query(array_filter(array_merge(request()->query(), ['export' => 'pdf']))) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Export PDF</a>
    </x-slot>

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.reports')
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
