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
                <label class="block text-xs font-medium text-slate-600">Landlord</label>
                <select name="landlord_id" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">All landlords</option>
                    @foreach (($landlords ?? []) as $l)
                        <option value="{{ $l->id }}" @selected((string) ($selectedLandlordId ?? request('landlord_id')) === (string) $l->id)>{{ $l->name }}</option>
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
            <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>

    <x-slot name="above">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Collection by channel</h3>
                <div class="mt-2 space-y-2 text-sm">
                    @forelse (($channelSummary ?? []) as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2">
                            <span class="text-slate-700">{{ $row['channel'] }} ({{ $row['count'] }})</span>
                            <span class="font-semibold text-slate-900">KES {{ number_format((float) $row['amount'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-slate-500">No channel summary in this period.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Collection by property</h3>
                <div class="mt-2 space-y-2 text-sm">
                    @forelse (($propertySummary ?? []) as $row)
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/70 px-3 py-2">
                            <span class="text-slate-700">{{ $row['property'] }}</span>
                            <span class="font-semibold text-slate-900">KES {{ number_format((float) $row['amount'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-slate-500">No property summary in this period.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </x-slot>
</x-property.workspace>

