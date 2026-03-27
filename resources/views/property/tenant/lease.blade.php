<x-property-layout>
    <x-slot name="header">Lease</x-slot>

    <x-property.page
        title="Lease"
        subtitle="Key lease details at a glance."
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Unit</p>
                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white break-words">{{ $unitLabel ?? '—' }}</p>
                <p class="mt-1 text-xs text-slate-500">Your current occupied unit(s).</p>
            </div>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Monthly rent</p>
                <p class="mt-2 text-lg font-semibold text-slate-900 dark:text-white tabular-nums">{{ $rent ?? '—' }}</p>
                <p class="mt-1 text-xs text-slate-500">Excludes one-off charges unless stated.</p>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Lease term</p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4">
                    <p class="text-xs text-slate-500">Start date</p>
                    <p class="mt-1 font-semibold text-slate-900 dark:text-white tabular-nums">{{ $start ?? '—' }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4">
                    <p class="text-xs text-slate-500">End date</p>
                    <p class="mt-1 font-semibold text-slate-900 dark:text-white tabular-nums">{{ $end ?? '—' }}</p>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Lease PDF download can be enabled once your property manager uploads it.</p>
        </div>
    </x-property.page>
</x-property-layout>
