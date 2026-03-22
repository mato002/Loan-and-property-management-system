<x-property.workspace
    title="Cash flow"
    subtitle="What hit your wallet vs accrual — timing of remittances and charges."
    back-route="property.landlord.reports.index"
    :stats="[
        ['label' => 'Cash in', 'value' => 'KES 0', 'hint' => 'Period'],
        ['label' => 'Cash out', 'value' => 'KES 0', 'hint' => 'Period'],
        ['label' => 'Net cash', 'value' => 'KES 0', 'hint' => 'Period'],
    ]"
    :columns="['Date', 'Description', 'Property', 'In', 'Out', 'Running cash']"
    empty-title="No cash rows"
    empty-hint="Reconcile to bank and M-Pesa statements; surface unreconciled items."
>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 h-48 flex items-center justify-center text-sm text-slate-500">
        Chart placeholder — cumulative cash vs accrual income
    </div>
</x-property.workspace>
