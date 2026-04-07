<x-property.workspace
    title="Transaction history"
    subtitle="Immutable ledger — collections, fees, maintenance charges, remittances, and adjustments with document links."
    back-route="property.landlord.earnings.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No ledger movements"
    empty-hint="Each row should drill down to source: invoice, payment, vendor bill, or journal memo."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.landlord.earnings.history.export') }}"
            data-turbo="false"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Export CSV</a>
        <a
            href="{{ route('property.landlord.earnings.history.export') }}"
            data-turbo="false"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Statement (CSV)</a>
    </x-slot>
    <x-slot name="toolbar">
        <input type="month" data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto" />
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Property: All</option>
            <option value="linked">Linked properties</option>
        </select>
    </x-slot>
</x-property.workspace>
