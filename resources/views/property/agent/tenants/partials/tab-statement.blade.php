<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Statement</h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('property.tenants.statement', $tenant, false) }}" data-turbo-frame="property-main" class="rounded-lg border border-indigo-300 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Full statement</a>
            <a href="{{ route('property.reports.tenant.statements', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Reports</a>
        </div>
    </div>
    <iframe
        src="{{ route('property.tenants.statement', [$tenant, 'embed' => 1], false) }}"
        title="Tenant statement preview"
        class="w-full min-h-[520px] border-0 bg-slate-50"
        loading="lazy"
    ></iframe>
</div>
