<x-property.workspace
    title="Vacancy trends"
    subtitle="Loss-to-lease, days on market, and vacancy cost estimate."
    back-route="property.performance.index"
    :stats="[
        ['label' => 'Vacancy rate', 'value' => '—', 'hint' => 'Units'],
        ['label' => 'Avg days vacant', 'value' => '—', 'hint' => 'Rolling'],
        ['label' => 'Est. rent lost', 'value' => 'KES 0', 'hint' => 'MTD'],
    ]"
    :columns="[]"
>
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-56 flex items-center justify-center text-sm text-slate-500">
            Area chart — vacant units over time
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-56 flex items-center justify-center text-sm text-slate-500">
            Bar chart — days vacant by property
        </div>
    </div>
</x-property.workspace>
