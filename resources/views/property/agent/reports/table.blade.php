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
    </x-slot>

    @php
        // Compute simple numeric totals per column for an ERP-style quick summary.
        $columnTotals = [];
        if (is_array($columns ?? null) && is_array($tableRows ?? null) && count($tableRows) > 0) {
            foreach ($columns as $colIndex => $colLabel) {
                $sum = 0.0;
                $isNumericColumn = false;
                foreach ($tableRows as $row) {
                    $cell = $row[$colIndex] ?? null;
                    if ($cell instanceof \Illuminate\Support\HtmlString) {
                        $cell = (string) $cell;
                    }
                    if (is_string($cell) || is_numeric($cell)) {
                        $val = is_numeric($cell) ? (float) $cell : (float) preg_replace('/[^\d.\-]/', '', (string) $cell);
                        if (is_finite($val) && $val !== 0.0) {
                            $isNumericColumn = true;
                            $sum += $val;
                        }
                    }
                }
                if ($isNumericColumn) {
                    $columnTotals[$colIndex] = $sum;
                }
            }
        }
    @endphp

    <x-slot name="toolbar">
        @include('property.agent.partials.filter_toolbars.reports')
    </x-slot>

    @if (!empty($columnTotals))
        <x-slot name="footer">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($columnTotals as $idx => $total)
                    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $columns[$idx] }}</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">{{ number_format($total, 2) }}</div>
                    </div>
                @endforeach
            </div>
        </x-slot>
    @endif

    @if (isset($paginator) && method_exists($paginator, 'links'))
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Showing {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }}
            </p>
            {{ $paginator->links() }}
        </div>
    @endif
</x-property.workspace>
