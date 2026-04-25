<x-property.workspace
    :title="'Lease #'.$lease->id"
    subtitle="Lease overview and linked tenant/unit details."
    back-route="property.tenants.leases"
    :stats="[
        ['label' => 'Status', 'value' => ucfirst($lease->status), 'hint' => 'Current'],
        ['label' => 'Monthly rent', 'value' => \App\Services\Property\PropertyMoney::kes((float) $lease->monthly_rent), 'hint' => 'Contract'],
        ['label' => 'Deposit', 'value' => \App\Services\Property\PropertyMoney::kes((float) $lease->deposit_amount), 'hint' => 'Held'],
        ['label' => 'Days to end', 'value' => is_null($daysLeft) ? 'Open-ended' : (string) $daysLeft, 'hint' => $isEndingSoon ? 'Renewal window' : ''],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.leases.edit', $lease, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit lease</a>
        <a href="{{ route('property.revenue.invoices', ['q' => $lease->pmTenant->name ?? ''], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Tenant invoices</a>
        <a href="{{ route('property.revenue.payments', ['q' => $lease->pmTenant->name ?? ''], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Tenant payments</a>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Lease details</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Tenant:</span> {{ $lease->pmTenant->name ?? '—' }}</p>
                <p><span class="text-slate-500">Phone:</span> <x-phone-link :value="$lease->pmTenant->phone ?? null" /></p>
                <p><span class="text-slate-500">Email:</span> {{ $lease->pmTenant->email ?? '—' }}</p>
                <p><span class="text-slate-500">Start:</span> {{ $lease->start_date?->format('Y-m-d') ?? '—' }}</p>
                <p><span class="text-slate-500">End:</span> {{ $lease->end_date?->format('Y-m-d') ?? 'Open-ended' }}</p>
                <p><span class="text-slate-500">Utility expense:</span> {{ $lease->utility_expense_type ? ucfirst($lease->utility_expense_type) : '—' }}</p>
                <p><span class="text-slate-500">Utility amount paid:</span> {{ $lease->utility_expense_amount ? \App\Services\Property\PropertyMoney::kes((float) $lease->utility_expense_amount) : '—' }}</p>
                <p><span class="text-slate-500">Linked unit(s):</span> {{ $unitsLabel }}</p>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Terms summary</h3>
            <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap leading-6">
                {{ trim((string) ($lease->terms_summary ?? '')) !== '' ? $lease->terms_summary : 'No terms summary provided.' }}
            </div>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Linked units</h3>
        </div>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Property</th>
                    <th class="px-4 py-3">Unit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Listed rent</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lease->units as $u)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                        <td class="px-4 py-3">{{ $u->property->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $u->label }}</td>
                        <td class="px-4 py-3 capitalize">{{ $u->status }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $u->rent_amount) }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('property.properties.show', ['property' => $u->property_id], false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View property</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No linked units.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
