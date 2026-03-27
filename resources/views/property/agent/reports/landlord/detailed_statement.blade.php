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
                class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50"
            >
                Export CSV
            </a>
        </form>
    </x-slot>
</x-property.workspace>
