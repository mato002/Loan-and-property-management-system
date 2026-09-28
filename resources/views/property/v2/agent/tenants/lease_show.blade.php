@php
    use App\Models\PmLease;
    use App\Services\Property\PropertyMoney;

    $tenant = $lease->pmTenant;
    $tenantId = (int) ($lease->pm_tenant_id ?? 0);
    $isTerminated = $lease->status === PmLease::STATUS_TERMINATED;
    $depositLines = $lease->relationLoaded('depositLines') ? $lease->depositLines : collect();
    $utilityExpenses = collect($lease->utility_expenses ?? [])->filter(fn ($row) => is_array($row) && !empty($row['type']) && (float) ($row['amount'] ?? 0) > 0);
    $openingArrears = collect($lease->opening_arrears ?? [])->filter(fn ($row) => is_array($row));
    $additionalDeposits = collect($lease->additional_deposits ?? [])->filter(fn ($row) => is_array($row));
    $invoiceFilter = array_filter([
        'tenant_id' => $tenantId ?: null,
        'unit_id' => optional($lease->units->first())->id,
    ]);
    $paymentFilter = array_filter([
        'tenant_id' => $tenantId ?: null,
        'unit_id' => optional($lease->units->first())->id,
    ]);
@endphp
<x-property.workspace
    :title="'Lease #'.$lease->id"
    subtitle="Lease, occupancy, collections, and linked unit workspace."
    back-route="property.tenants.leases"
    :stats="[
        ['label' => 'Status', 'value' => ucfirst($lease->status), 'hint' => $unitsLabel ?? 'Unit'],
        ['label' => 'Monthly rent', 'value' => PropertyMoney::kes((float) $lease->monthly_rent), 'hint' => $lease->rent_due_day ? 'Due day '.$lease->rent_due_day : 'Contract'],
        ['label' => 'Outstanding', 'value' => PropertyMoney::kes((float) ($outstanding ?? 0)), 'hint' => ((float) ($carryForwardTotal ?? 0) > 0) ? 'CF '.PropertyMoney::kes((float) $carryForwardTotal) : 'Tenant AR'],
        ['label' => 'Days to end', 'value' => is_null($daysLeft) ? 'Open-ended' : (string) $daysLeft, 'hint' => $isEndingSoon ? 'Renewal window' : ''],
    ]"
    :columns="[]"
>
    <x-slot name="actions">
        @include('property.agent.partials.hub_quick_actions', ['actions' => $quickActions ?? []])
        @if ($isTerminated)
            <form method="post" action="{{ route('property.leases.restore', $lease, false) }}" class="inline" data-swal-confirm="Restore this lease to active?">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Restore lease</button>
            </form>
        @else
            <form method="post" action="{{ route('property.leases.terminate', $lease, false) }}" class="inline" data-swal-confirm="Terminate this lease now? The unit will be vacated if no other active lease remains.">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">Terminate</button>
            </form>
        @endif
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Tenant</h3>
                @if ($tenantId > 0)
                    <a href="{{ route('property.tenants.show', $tenantId, false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Open tenant 360</a>
                @endif
            </div>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Name:</span> {{ $tenant->name ?? '—' }}</p>
                <p><span class="text-slate-500">Account:</span> {{ $tenant->account_number ?? '—' }}</p>
                <p><span class="text-slate-500">Phone:</span> <x-phone-link :value="$tenant->phone ?? null" /></p>
                <p><span class="text-slate-500">Email:</span> {{ $tenant->email ?? '—' }}</p>
                <p><span class="text-slate-500">ID / passport:</span> {{ $tenant->national_id ?? '—' }}</p>
                @if ($tenantId > 0)
                    <p class="pt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        <a href="{{ route('property.tenants.statement', ['tenant' => $tenantId], false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">Statement</a>
                        <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenantId], false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">Notices</a>
                    </p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Lease details</h3>
                <a href="{{ route('property.leases.edit', $lease, false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Edit</a>
            </div>
            <div class="mt-2 text-sm text-slate-700 space-y-1">
                <p><span class="text-slate-500">Start:</span> {{ $lease->start_date?->format('Y-m-d') ?? '—' }}</p>
                <p><span class="text-slate-500">End:</span> {{ $lease->end_date?->format('Y-m-d') ?? 'Open-ended' }}</p>
                <p><span class="text-slate-500">Rent due day:</span> {{ $lease->rent_due_day ?: '—' }}</p>
                <p><span class="text-slate-500">Deposit held:</span> {{ PropertyMoney::kes((float) $lease->deposit_amount) }}</p>
                <p><span class="text-slate-500">Variation:</span> {{ $lease->lease_variation_type ? ucfirst(str_replace('_', ' ', (string) $lease->lease_variation_type)) : '—' }}</p>
                <p><span class="text-slate-500">Linked unit(s):</span> {{ $unitsLabel }}</p>
                @if ($utilityExpenses->isNotEmpty())
                    <p class="pt-1 font-medium text-slate-800">Utility expenses</p>
                    @foreach ($utilityExpenses as $row)
                        <p><span class="text-slate-500">—</span> {{ ucfirst(str_replace('_', ' ', (string) ($row['type'] ?? 'other'))) }}: {{ PropertyMoney::kes((float) ($row['amount'] ?? 0)) }}</p>
                    @endforeach
                @else
                    <p><span class="text-slate-500">Utility expense:</span> {{ $lease->utility_expense_type ? ucfirst($lease->utility_expense_type) : '—' }}</p>
                    <p><span class="text-slate-500">Utility amount paid:</span> {{ $lease->utility_expense_amount ? PropertyMoney::kes((float) $lease->utility_expense_amount) : '—' }}</p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Terms summary</h3>
            <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap leading-6">
                {{ trim((string) ($lease->terms_summary ?? '')) !== '' ? $lease->terms_summary : 'No terms summary provided.' }}
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Carry-forward & extra charges</h3>
            <div class="mt-2 text-sm text-slate-700 space-y-2">
                <p><span class="text-slate-500">Carry-forward total:</span> {{ PropertyMoney::kes((float) ($carryForwardTotal ?? 0)) }}</p>
                @if ($openingArrears->isNotEmpty())
                    <p class="font-medium text-slate-800">Opening arrears</p>
                    @foreach ($openingArrears as $row)
                        <p>
                            <span class="text-slate-500">-</span>
                            {{ ucfirst(str_replace('_', ' ', (string) ($row['charge_type'] ?? 'other'))) }}
                            {{ trim((string) ($row['specific_charge'] ?? '')) !== '' ? ' / '.$row['specific_charge'] : '' }}
                            {{ !empty($row['period']) ? ' ('.$row['period'].')' : '' }}
                            :
                            {{ PropertyMoney::kes((float) ($row['amount'] ?? 0)) }}
                        </p>
                    @endforeach
                    @if (!is_null($lease->opening_arrears_manual_total))
                        <p><span class="text-slate-500">Manual total:</span> {{ PropertyMoney::kes((float) $lease->opening_arrears_manual_total) }}</p>
                    @endif
                    @if (!is_null($lease->opening_arrears_as_of_date))
                        <p><span class="text-slate-500">As of date:</span> {{ optional($lease->opening_arrears_as_of_date)->format('Y-m-d') }}</p>
                    @endif
                    @if (trim((string) ($lease->opening_arrears_note ?? '')) !== '')
                        <p><span class="text-slate-500">Note:</span> {{ $lease->opening_arrears_note }}</p>
                    @endif
                @else
                    <p><span class="text-slate-500">Opening arrears:</span> —</p>
                @endif

                @if ($additionalDeposits->isNotEmpty())
                    <p class="pt-1 font-medium text-slate-800">Additional deposits</p>
                    @foreach ($additionalDeposits as $row)
                        <p>
                            <span class="text-slate-500">-</span>
                            {{ $row['label'] ?? 'Deposit' }}:
                            {{ PropertyMoney::kes((float) ($row['amount'] ?? 0)) }}
                        </p>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Deposit lines</h3>
            <div class="mt-2 text-sm text-slate-700">
                @forelse ($depositLines as $line)
                    <p class="flex flex-wrap justify-between gap-2 border-b border-slate-100 py-1.5 last:border-0">
                        <span>{{ $line->label ?: ($line->deposit_key ?: 'Deposit') }}</span>
                        <span class="tabular-nums">
                            {{ PropertyMoney::kes((float) $line->paid_amount) }} /
                            {{ PropertyMoney::kes((float) $line->expected_amount) }}
                            <span class="text-slate-500">(bal {{ PropertyMoney::kes((float) $line->balance_amount) }})</span>
                        </span>
                    </p>
                @empty
                    <p class="text-slate-500">No structured deposit lines. Contract deposit {{ PropertyMoney::kes((float) $lease->deposit_amount) }}.</p>
                @endforelse
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
                        <td class="px-4 py-3 tabular-nums">{{ PropertyMoney::kes((float) $u->rent_amount) }}</td>
                        <td class="px-4 py-3">
                            @include('property.agent.partials.unit_actions_menu', [
                                'unit' => $u,
                                'propertyId' => $u->property_id,
                                'showViewProperty' => true,
                                'showOpenUnitHub' => true,
                            ])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No linked units.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5 grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Recent invoices</h3>
                <a href="{{ route('property.revenue.invoices', $invoiceFilter, false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">All invoices</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Due</th>
                        <th class="px-3 py-2">Balance</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentInvoices as $invoice)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-3 py-2">
                                <a href="{{ route('property.revenue.invoices.show', $invoice, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $invoice->invoice_no ?: '#'.$invoice->id }}</a>
                                <div class="text-[11px] text-slate-400">{{ optional($invoice->issue_date)->format('Y-m-d') }}</div>
                            </td>
                            <td class="px-3 py-2 capitalize">{{ str_replace('_', ' ', (string) ($invoice->invoice_type ?: '—')) }}</td>
                            <td class="px-3 py-2">{{ optional($invoice->due_date)->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ PropertyMoney::kes($invoice->balanceFloat()) }}</td>
                            <td class="px-3 py-2 capitalize">{{ $invoice->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No invoices for this lease yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Recent payments</h3>
                <a href="{{ route('property.revenue.payments', $paymentFilter, false) }}" data-turbo-frame="property-main" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">All payments</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Channel</th>
                        <th class="px-3 py-2">Ref</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentPayments as $payment)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-3 py-2">{{ optional($payment->paid_at)->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-3 py-2 capitalize">{{ $payment->channel ?: '—' }}</td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $payment->external_ref ?: '—' }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ PropertyMoney::kes((float) $payment->amount) }}</td>
                            <td class="px-3 py-2 capitalize">{{ $payment->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No payments recorded for this tenant yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-property.workspace>
