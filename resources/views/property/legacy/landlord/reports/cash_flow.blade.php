<x-property.workspace
    title="Cash flow"
    subtitle="Ledger credits and debits — monthly trend and running detail."
    back-route="property.landlord.reports.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No cash rows"
    empty-hint="Ledger entries will appear here as credits and debits are posted."
>
    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Month</label>
                <input
                    type="month"
                    name="month"
                    value="{{ request('month', $month ?? now()->format('Y-m')) }}"
                    class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto"
                />
            </div>
            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Apply</button>
        </form>
    </x-slot>
    <div class="grid gap-4 lg:grid-cols-2 w-full min-w-0">
        <x-property.chart-line-dual
            title="Cash in vs out by month"
            label-a="Credits (in)"
            label-b="Debits (out)"
            :series="$cashInOutDual ?? []"
        />
        <x-property.chart-bar
            title="Net ledger flow by month"
            value-format="kes"
            :series="$cashNetBars ?? []"
        />
    </div>
    @if (! empty($cashCumulative))
        <div class="mt-4">
            <x-property.chart-bar
                title="End-of-month ledger balance"
                value-format="kes"
                :series="array_map(static fn ($c) => ['label' => $c['label'], 'value' => $c['balance']], $cashCumulative)"
            />
        </div>
    @endif
</x-property.workspace>
