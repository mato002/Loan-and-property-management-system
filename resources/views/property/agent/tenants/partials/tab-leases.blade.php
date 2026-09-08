<div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-slate-900">Leases</h3>
        <a href="{{ route('property.tenants.leases', ['pm_tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-emerald-700 hover:underline">Manage leases</a>
    </div>
    <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Lease #</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Start</th>
                <th class="px-4 py-3">End</th>
                <th class="px-4 py-3">Rent</th>
                <th class="px-4 py-3">Due day</th>
                <th class="px-4 py-3">Extras / mo</th>
                <th class="px-4 py-3">Unit(s)</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leaseRows as $r)
                <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                    <td class="px-4 py-3 font-medium text-slate-900">#{{ $r['id'] }}</td>
                    <td class="px-4 py-3 capitalize">{{ $r['status'] }}</td>
                    <td class="px-4 py-3">{{ $r['start'] }}</td>
                    <td class="px-4 py-3">{{ $r['end'] }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $r['rent']) }}</td>
                    <td class="px-4 py-3">{{ $r['rent_due_day'] ?? '—' }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($r['standing_total'] ?? 0)) }}</td>
                    <td class="px-4 py-3">{{ $r['units'] }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('property.leases.show', ['lease' => $r['id']], false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View lease</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No leases yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
