<x-property-layout>
    <x-slot name="header">Portfolio overview</x-slot>

    <x-property.page
        title="Portfolio overview"
        subtitle="Financial-first snapshot — income, occupancy, arrears impact, and net earnings. Figures are placeholders until ledger data is connected."
    >
        <x-property.module-status label="Landlord portal" class="mb-4" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-emerald-200/70 dark:border-emerald-900/50 bg-gradient-to-br from-white to-emerald-50/50 dark:from-gray-900 dark:to-emerald-950/20 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-emerald-800/80 dark:text-emerald-300/80">Income this month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">KES 0</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">After agreed fees (when configured).</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Occupancy rate</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">—</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Weighted by rent roll.</p>
            </div>
            <div class="rounded-2xl border border-amber-200/70 dark:border-amber-900/40 bg-amber-50/40 dark:bg-amber-950/15 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-amber-900/80 dark:text-amber-200/90">Arrears (income impact)</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">KES 0</p>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">What didn’t land in your wallet this period.</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Net earnings</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white tabular-nums">KES 0</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Ledger-driven, not a single balance field.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/60 p-6">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Trust &amp; transparency</h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-2 leading-relaxed">
                Every number here should trace to postings (settlements, fees, maintenance allocations). Next step: expose a read-only ledger timeline per property.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('property.landlord.earnings.index') }}" class="inline-flex rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-medium hover:bg-emerald-700 transition-colors">Earnings &amp; wallet</a>
                <a href="{{ route('property.landlord.reports.index') }}" class="inline-flex rounded-xl border border-slate-200 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">Reports</a>
            </div>
        </div>
    </x-property.page>
</x-property-layout>
