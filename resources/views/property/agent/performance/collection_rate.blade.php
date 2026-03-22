<x-property.workspace
    title="Rent collection rate"
    subtitle="Budget vs actual collections — by portfolio, property, and month."
    back-route="property.performance.index"
    :stats="[
        ['label' => 'Target', 'value' => '—', 'hint' => 'This month'],
        ['label' => 'Actual', 'value' => '—', 'hint' => '% collected'],
        ['label' => 'Gap', 'value' => 'KES 0', 'hint' => 'Value at risk'],
    ]"
    :columns="[]"
>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-6 shadow-sm">
        <p class="text-sm font-medium text-slate-800 dark:text-slate-200">Trend</p>
        <div class="mt-4 h-64 rounded-xl bg-slate-50 dark:bg-slate-900/50 flex items-center justify-center text-sm text-slate-500">
            Line chart placeholder — target vs actual collection rate
        </div>
    </div>
    <x-slot name="footer">
        <p>Drill-down: click a month (when live) to open arrears cohort for that period.</p>
    </x-slot>
</x-property.workspace>
