<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Occupancy rate</p>
    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($reporting['occupancy_rate'] ?? 0), 1) }}%</p>
    <p class="mt-1 text-xs text-slate-500">Occupied units over total doors</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Collection rate ({{ $periodLabel }})</p>
    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((float) ($reporting['collection_rate'] ?? 0), 1) }}%</p>
    <p class="mt-1 text-xs text-slate-500">Collected amount over invoiced amount</p>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average arrears per unit</p>
    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($reporting['avg_arrears_per_unit'] ?? 0)) }}</p>
    <p class="mt-1 text-xs text-slate-500">Across all units in this property</p>
</div>
