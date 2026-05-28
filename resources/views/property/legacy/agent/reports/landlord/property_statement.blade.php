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
    <x-slot name="actions">
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
        <a href="{{ url()->current().'?'.http_build_query(array_filter(array_merge(request()->query(), ['export' => 'csv']))) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export CSV</a>
        <a href="{{ url()->current().'?'.http_build_query(array_filter(array_merge(request()->query(), ['export' => 'xls']))) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Export XLS</a>
        <a href="{{ url()->current().'?'.http_build_query(array_filter(array_merge(request()->query(), ['export' => 'pdf']))) }}" data-turbo="false" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Export PDF</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">Property (search)</label>
                <input type="text" name="property" value="{{ $filters['property'] ?? request('property') }}" placeholder="e.g. Greenview" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? request('q') }}" placeholder="Tenant, unit, property" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">From</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? request('from') }}" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">To</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? request('to') }}" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">Per page</label>
                <select name="per_page" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($perPage ?? request('per_page', 30)) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
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
