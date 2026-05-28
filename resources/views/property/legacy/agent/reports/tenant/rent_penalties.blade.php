<x-property.workspace
    :title="$title"
    :subtitle="$subtitle"
    :back-route="$backRoute"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :empty-title="$emptyTitle ?? 'No records found'"
    :empty-hint="$emptyHint ?? 'This report will populate once there is transactional data.'"
>
    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? request('from') }}" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? request('to') }}" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>

    @php
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
</x-property.workspace>
