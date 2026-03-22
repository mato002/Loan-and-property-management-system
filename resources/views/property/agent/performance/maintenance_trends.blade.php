<x-property.workspace
    title="Maintenance cost trends"
    subtitle="Cost per door, category mix, and vendor concentration — tie to NOI warnings."
    back-route="property.performance.index"
    :stats="[
        ['label' => 'Spend (12m)', 'value' => 'KES 0', 'hint' => 'All in'],
        ['label' => 'Cost / door', 'value' => 'KES 0', 'hint' => 'Avg / mo'],
        ['label' => 'Vendor concentration', 'value' => '—', 'hint' => 'Top 3 share'],
    ]"
    :columns="['Month', 'Reactive', 'Preventive', 'Capex', 'Total', 'vs prior']"
    empty-title="No trend rows"
    empty-hint="Normalize by unit count to compare properties of different sizes fairly."
>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-52 flex items-center justify-center text-sm text-slate-500">
        Line chart — total maintenance vs rent collected
    </div>
</x-property.workspace>
