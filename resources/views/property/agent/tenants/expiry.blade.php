<x-property.workspace
    title="Lease expiry tracking"
    subtitle="Active leases ending within the next 90 days. Use the window filter to focus renewals."
    back-route="property.tenants.index"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    :table-row-filters="$expiryFilterTexts"
    empty-title="No upcoming expiries"
    empty-hint="When leases have end dates in the next 90 days, they appear here."
>
    <x-slot name="actions">
        <a
            href="{{ route('property.workspace.form.show', 'tenants-renewal-email') }}"
            class="inline-flex justify-center items-center rounded-xl border border-slate-200 dark:border-slate-600 px-3 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/50 w-full sm:w-auto"
        >Email renewals</a>
    </x-slot>
    <x-slot name="toolbar">
        <select data-table-filter="parent" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-sm px-3 py-2 min-w-0 w-full sm:w-auto">
            <option value="">Window: All (90d)</option>
            <option value="within30">≤ 30 days</option>
            <option value="within60">≤ 60 days</option>
            <option value="within90">≤ 90 days</option>
        </select>
    </x-slot>
</x-property.workspace>
