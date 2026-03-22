<x-property.workspace
    title="Vendor performance"
    subtitle="On-time completion, rework rate, quote accuracy, and dispute count — drives future RFQ shortlists."
    back-route="property.vendors.index"
    :stats="[
        ['label' => 'Top vendor', 'value' => '—', 'hint' => 'Composite score'],
        ['label' => 'Rework rate', 'value' => '—', 'hint' => 'All vendors'],
        ['label' => 'Avg quote delta', 'value' => '—', 'hint' => 'vs final cost'],
    ]"
    :columns="['Vendor', 'Jobs (12m)', 'On-time %', 'Rework %', 'Avg cost var', 'Disputes', 'Grade']"
    empty-title="No performance history"
    empty-hint="Minimum sample size before ranking — avoid penalizing new vendors on one job."
>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-52 flex items-center justify-center text-sm text-slate-500">
        Scatter placeholder — cost variance vs on-time rate
    </div>
</x-property.workspace>
