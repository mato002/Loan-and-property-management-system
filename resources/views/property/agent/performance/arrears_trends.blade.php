<x-property.workspace
    title="Arrears trends"
    subtitle="Rolling aging, cure rates, and new arrears flow — connect to collections playbook."
    back-route="property.performance.index"
    :stats="[
        ['label' => 'Opening balance', 'value' => 'KES 0', 'hint' => 'Month start'],
        ['label' => 'New', 'value' => 'KES 0', 'hint' => 'Added'],
        ['label' => 'Cured', 'value' => 'KES 0', 'hint' => 'Collected'],
        ['label' => 'Closing', 'value' => 'KES 0', 'hint' => 'Month end'],
    ]"
    :columns="['Cohort', 'Opening', 'New', 'Cured', 'Write-off', 'Closing', 'Cure rate']"
    empty-title="No cohort data"
    empty-hint="Build cohorts by month-of-origination to see long-tail behavior."
>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-52 flex items-center justify-center text-sm text-slate-500">
        Stacked area — aging buckets over time
    </div>
</x-property.workspace>
