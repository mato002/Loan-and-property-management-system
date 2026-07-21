<x-property.landlord.embedded-report-table
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No invoices"
>
    <x-slot name="actions">
        <a href="{{ route('property.landlord.reports.income.export', request()->query()) }}" data-turbo="false" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Invoices (CSV)</a>
        <a href="{{ route('property.landlord.earnings.history.export') }}" data-turbo="false" class="rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200">Ledger (CSV)</a>
    </x-slot>
    <x-slot name="toolbar">
        <form method="get" action="{{ route('property.landlord.reports.index') }}" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="panel" value="income" />
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Month</label>
                <input type="month" name="month" value="{{ request('month', $month ?? now()->format('Y-m')) }}" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 w-full sm:w-auto" />
            </div>
            @if (request()->filled('property_id'))
                <input type="hidden" name="property_id" value="{{ request('property_id') }}" />
            @endif
            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply</button>
        </form>
    </x-slot>
</x-property.landlord.embedded-report-table>
