<x-property.workspace
    title="Tenant Statements"
    subtitle="Tenant Reports"
    back-route="property.reports.tenant"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No tenant statement records"
    empty-hint="Tenant statement data will appear once invoices and payments are recorded."
>
    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">From</label>
                <input
                    type="date"
                    name="from"
                    value="{{ $filters['from'] ?? request('from') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">To</label>
                <input
                    type="date"
                    name="to"
                    value="{{ $filters['to'] ?? request('to') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>
</x-property.workspace>
