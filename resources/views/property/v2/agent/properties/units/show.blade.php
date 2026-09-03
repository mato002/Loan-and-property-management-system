<x-property.workspace :compact-list="false"
    :title="'Unit: '.($unit->label ?? '—')"
    :subtitle="'Operational unit hub · '.($property->name ?? 'Property')"
    back-route="property.properties.show"
    :back-route-params="['property' => $property->id, 'tab' => 'units']"
    :stats="[
        ['label' => 'Status', 'value' => ucfirst((string) $unit->status), 'hint' => 'Current'],
        ['label' => 'Rent', 'value' => \App\Services\Property\PropertyMoney::kes($unit->listedRentAmount()), 'hint' => 'Listed'],
        ['label' => 'Arrears', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($arrears ?? 0)), 'hint' => 'Outstanding'],
        ['label' => 'Type', 'value' => ucfirst(str_replace('_', ' ', (string) ($unit->unit_type ?? 'unit'))), 'hint' => (string) ($unit->bedrooms ?? 0).' bed'],
    ]"
    :columns="[]"
>
    @php
        $activeTab = $activeTab ?? 'overview';
    @endphp

    <x-property.entity-hub
        entity="unit"
        route-name="property.units.show"
        :route-params="['unit' => $unit->id]"
        :active-tab="$activeTab"
        :quick-actions="$quickActions ?? []"
        :alerts="($arrears ?? 0) > 0 ? [['tone' => 'rose', 'label' => 'Unit arrears '.\App\Services\Property\PropertyMoney::kes((float) $arrears), 'href' => route('property.revenue.arrears', ['q' => $unit->label], false)]] : []"
    />

    @if ($activeTab === 'overview')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Unit profile</h3>
                <div class="mt-2 text-sm text-slate-700 space-y-1">
                    <p><span class="text-slate-500">Property:</span>
                        <a href="{{ route('property.properties.show', $property, false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:underline">{{ $property->name }}</a>
                    </p>
                    <p><span class="text-slate-500">Label:</span> {{ $unit->label }}</p>
                    <p><span class="text-slate-500">Type:</span> {{ ucfirst(str_replace('_', ' ', (string) ($unit->unit_type ?? '—'))) }}</p>
                    <p><span class="text-slate-500">Bedrooms:</span> {{ (int) ($unit->bedrooms ?? 0) }}</p>
                    <p><span class="text-slate-500">Status:</span> {{ ucfirst((string) $unit->status) }}</p>
                    <p><span class="text-slate-500">Listed rent:</span> {{ \App\Services\Property\PropertyMoney::kes($unit->listedRentAmount()) }}</p>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Current occupancy</h3>
                @if ($activeLease ?? null)
                    <div class="mt-2 text-sm text-slate-700 space-y-1">
                        <p><span class="text-slate-500">Tenant:</span>
                            <a href="{{ route('property.tenants.show', $activeLease->pm_tenant_id, false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:underline">{{ $activeLease->pmTenant?->name ?? '—' }}</a>
                        </p>
                        <p><span class="text-slate-500">Lease:</span> #{{ $activeLease->id }} · {{ ucfirst((string) $activeLease->status) }}</p>
                        <p><span class="text-slate-500">Rent:</span> {{ \App\Services\Property\PropertyMoney::kes((float) $activeLease->monthly_rent) }}</p>
                        <p><span class="text-slate-500">Period:</span> {{ $activeLease->start_date?->format('Y-m-d') ?? '—' }} → {{ $activeLease->end_date?->format('Y-m-d') ?? 'open' }}</p>
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-500">Vacant — no active lease on this unit.</p>
                @endif
            </div>
        </div>
    @endif

    @if ($activeTab === 'tenant')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Tenant & lease</h3>
            </div>
            @if ($activeLease ?? null)
                <div class="p-4 text-sm text-slate-700 space-y-2">
                    <p><span class="text-slate-500">Tenant:</span>
                        <a href="{{ route('property.tenants.show', $activeLease->pm_tenant_id, false) }}" data-turbo-frame="property-main" class="font-semibold text-indigo-600 hover:underline">{{ $activeLease->pmTenant?->name ?? '—' }}</a>
                    </p>
                    <p><span class="text-slate-500">Lease #{{ $activeLease->id }}</span> · {{ ucfirst((string) $activeLease->status) }}</p>
                    <a href="{{ route('property.leases.show', ['lease' => $activeLease->id], false) }}" data-turbo-frame="property-main" class="inline-flex items-center rounded-lg border border-emerald-300 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Open lease workspace</a>
                </div>
            @else
                <p class="p-4 text-sm text-slate-500">No active tenant on this unit.</p>
            @endif
        </div>
    @endif

    @if ($activeTab === 'invoices')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Invoices</h3>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($invoices ?? []) as $invoice)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <a href="{{ route('property.revenue.invoices.show', $invoice, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $invoice->invoice_no ?: '#'.$invoice->id }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $invoice->pmTenant?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes(max(0, (float) $invoice->amount - (float) $invoice->amount_paid)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No invoices for this unit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'utilities')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Utility history</h3>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3">Previous</th>
                        <th class="px-4 py-3">Current</th>
                        <th class="px-4 py-3">Consumption</th>
                        <th class="px-4 py-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($utilityReadings ?? []) as $reading)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $reading->billing_month ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->previous_reading ?? 0), 2) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->current_reading ?? 0), 2) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ number_format((float) ($reading->units_used ?? 0), 2) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($reading->amount ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No utility readings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'maintenance')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Maintenance history</h3>
                <a href="{{ route('property.maintenance.requests', ['unit_id' => $unit->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-slate-700 hover:underline">Open maintenance workspace</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Urgency</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($maintenanceRequests ?? []) as $requestItem)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $requestItem->created_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($requestItem->category ?? 'general')) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $requestItem->urgency ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $requestItem->status ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs truncate">{{ $requestItem->description ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No maintenance requests for this unit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'history')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">Occupancy history</h3>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Tenant</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Start</th>
                        <th class="px-4 py-3">End</th>
                        <th class="px-4 py-3">Rent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($occupancyHistory ?? []) as $row)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $row['tenant'] ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $row['status'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['start'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['end'] ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['rent'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No lease history on this unit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-property.workspace>
