<x-property.workspace
    :title="'Tenant: '.$tenant->name"
    subtitle="Tenant profile, leases, and billing snapshot."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Risk', 'value' => ucfirst($tenant->risk_level), 'hint' => 'Current'],
        ['label' => 'Leases', 'value' => (string) ($tenant->leases_count ?? 0), 'hint' => 'Linked'],
        ['label' => 'Invoices', 'value' => (string) ($tenant->invoices_count ?? 0), 'hint' => 'Recent snapshot'],
        ['label' => 'Outstanding', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($invoiceTotals['due'] ?? 0)), 'hint' => 'Recent invoices'],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.tenants.edit', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50">Edit tenant</a>
        <a href="{{ route('property.tenants.directory', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Directory</a>
        <a href="{{ route('property.tenants.leases', ['pm_tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Leases</a>
        <a href="{{ route('property.revenue.invoices', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-cyan-300 bg-white px-3 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-50">Invoices</a>
        <a href="{{ route('property.revenue.payments', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-teal-300 bg-white px-3 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50">Payments</a>
        <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-700 hover:bg-amber-50">Notices</a>
        <a href="{{ route('property.reports.tenant.statements', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl border border-fuchsia-300 bg-white px-3 py-2 text-sm font-medium text-fuchsia-700 hover:bg-fuchsia-50">Reports</a>
        <a href="{{ route('property.tenants.statement', $tenant, false) }}" data-turbo-frame="property-main" class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">Statement</a>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Profile</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Name:</span> {{ $tenant->name }}</p>
                <p><span class="text-slate-500">Phone:</span> {{ $tenant->phone ?: '—' }}</p>
                <p><span class="text-slate-500">Email:</span> {{ $tenant->email ?: '—' }}</p>
                <p><span class="text-slate-500">National ID / ref:</span> {{ $tenant->national_id ?: '—' }}</p>
                <p><span class="text-slate-500">Opening arrears total:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_amount ?? 0)) }}</p>
                <p class="text-xs text-slate-500">
                    Rent {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_rent ?? 0)) }},
                    Utilities {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_utilities ?? 0)) }},
                    Penalties {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_penalties ?? 0)) }},
                    Other {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_other ?? 0)) }}
                </p>
                @if (is_array($tenant->opening_arrears_items) && count($tenant->opening_arrears_items) > 0)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-2">
                        <p class="text-xs font-semibold text-amber-900">Opening arrears lines</p>
                        <ul class="mt-1 space-y-1 text-xs text-amber-800">
                            @foreach ($tenant->opening_arrears_items as $line)
                                @php
                                    $lineType = (string) ($line['type'] ?? 'other');
                                    $lineLabel = trim((string) ($line['label'] ?? '')) !== ''
                                        ? trim((string) ($line['label'] ?? ''))
                                        : ucfirst(str_replace('_', ' ', $lineType));
                                    $linePeriod = (string) ($line['period'] ?? '');
                                    $lineAmount = (float) ($line['amount'] ?? 0);
                                    $lineRef = trim((string) ($line['reference'] ?? ''));
                                @endphp
                                <li>
                                    {{ $lineLabel }}{{ $linePeriod !== '' ? ' ('.$linePeriod.')' : '' }}:
                                    {{ \App\Services\Property\PropertyMoney::kes($lineAmount) }}
                                    @if ($lineRef !== '')
                                        <span class="text-amber-700">- {{ $lineRef }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p><span class="text-slate-500">Portal login linked:</span> {{ $tenant->user_id ? 'Yes' : 'No' }}</p>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Notes</h3>
            <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">
                {{ trim((string) ($tenant->notes ?? '')) !== '' ? $tenant->notes : 'No notes added.' }}
            </div>
        </div>
    </div>

    <div class="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-900">Lease history</h3>
        </div>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3">Lease #</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Start</th>
                    <th class="px-4 py-3">End</th>
                    <th class="px-4 py-3">Rent</th>
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
                        <td class="px-4 py-3">{{ $r['units'] }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('property.leases.show', ['lease' => $r['id']], false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View lease</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No leases yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-property.workspace>
