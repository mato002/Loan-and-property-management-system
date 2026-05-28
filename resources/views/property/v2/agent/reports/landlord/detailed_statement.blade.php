@if (request('embed') === '1')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Landlord Statement' }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; margin: 16px; }
        .head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .title { font-size: 18px; font-weight: 700; }
        .muted { color: #64748b; font-size: 12px; }
        .cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
        .card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; background: #fff; }
        .card .k { font-size: 11px; color: #64748b; text-transform: uppercase; }
        .card .v { font-size: 13px; font-weight: 600; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; font-size: 12px; }
        th { background: #f8fafc; text-transform: uppercase; font-size: 11px; color: #475569; }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <div class="title">{{ $title ?? 'Landlord Statement' }}</div>
            <div class="muted">{{ $subtitle ?? '' }}</div>
        </div>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    @if (!empty($stats))
        <div class="cards">
            @foreach ($stats as $s)
                <div class="card">
                    <div class="k">{{ $s['label'] ?? '' }}</div>
                    <div class="v">{{ $s['value'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif
    <table>
        <thead>
            <tr>
                @foreach (($columns ?? []) as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse (($tableRows ?? []) as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>
                            @if ($cell instanceof \Illuminate\Support\HtmlString)
                                {!! $cell !!}
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns ?? []) ?: 1 }}">{{ $emptyHint ?? 'No records found.' }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
@else
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
                <label class="block text-xs font-medium text-slate-600">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select landlord…</option>
                    @foreach (($landlords ?? []) as $l)
                        <option value="{{ $l->id }}" @selected((string) ($selectedLandlordId ?? '') === (string) $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
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
            <a
                href="{{ route('property.reports.export.csv', array_filter(['reportKey' => 'landlord_detailed_statement', 'landlord_id' => request('landlord_id'), 'from' => request('from'), 'to' => request('to')]), false) }}"
                data-turbo="false"
                class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
            >
                Export CSV
            </a>
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
@endif