<x-property.workspace
    :title="'Tenant: '.$tenant->name"
    subtitle="Operational tenant hub — leases, billing, notices, and utilities in one place."
    back-route="property.tenants.directory"
    :stats="[
        ['label' => 'Risk', 'value' => ucfirst($tenant->risk_level), 'hint' => 'Current'],
        ['label' => 'Leases', 'value' => (string) ($tenant->leases_count ?? 0), 'hint' => 'Linked'],
        ['label' => 'Invoice AR', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totalDue['invoice_ar'] ?? 0)), 'hint' => 'Billable open balances'],
        ['label' => 'Total due', 'value' => \App\Services\Property\PropertyMoney::kes((float) ($totalDue['total_due'] ?? 0)), 'hint' => 'AR + uninvoiced CF − credit'],
    ]"
    :columns="[]"
>
    @php
        $activeTab = $activeTab ?? 'overview';
        $leaseCarryForward = $leaseCarryForward ?? ['total' => 0.0, 'lines' => [], 'invoiced' => false];
        $totalDue = $totalDue ?? ['invoice_ar' => 0.0, 'uninvoiced_cf' => 0.0, 'tenant_credit' => 0.0, 'total_due' => 0.0];
    @endphp

    <x-property.entity-hub
        entity="tenant"
        route-name="property.tenants.show"
        :route-params="['tenant' => $tenant->id]"
        :active-tab="$activeTab"
        :quick-actions="$quickActions ?? []"
        :alerts="$alerts ?? []"
    />

    @if ($activeTab === 'overview')
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm mb-4">
            <h3 class="text-sm font-semibold text-slate-900">Total due (canonical)</h3>
            <p class="mt-1 text-xs text-slate-500">Invoice AR + uninvoiced carry-forward − tenant credit</p>
            <div class="mt-3 grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                <div class="rounded-xl bg-rose-50 border border-rose-100 p-3">
                    <p class="text-xs text-rose-700 uppercase font-semibold">Invoice AR</p>
                    <p class="mt-1 text-lg font-bold text-rose-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['invoice_ar'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 border border-amber-100 p-3">
                    <p class="text-xs text-amber-700 uppercase font-semibold">Uninvoiced CF</p>
                    <p class="mt-1 text-lg font-bold text-amber-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['uninvoiced_cf'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3">
                    <p class="text-xs text-emerald-700 uppercase font-semibold">Tenant credit</p>
                    <p class="mt-1 text-lg font-bold text-emerald-900">− {{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['tenant_credit'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-3 md:col-span-2">
                    <p class="text-xs text-indigo-700 uppercase font-semibold">Total due</p>
                    <p class="mt-1 text-2xl font-bold text-indigo-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['total_due'] ?? 0)) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm mb-4">
            <h3 class="text-sm font-semibold text-slate-900">Financial summary</h3>
            <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div class="rounded-xl bg-rose-50 border border-rose-100 p-3">
                    <p class="text-xs text-rose-700 uppercase font-semibold">Invoice AR</p>
                    <p class="mt-1 text-lg font-bold text-rose-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['invoice_ar'] ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-3">
                    <p class="text-xs text-emerald-700 uppercase font-semibold">Credit balance</p>
                    <p class="mt-1 text-lg font-bold text-emerald-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($creditBalance ?? 0)) }}</p>
                </div>
                <div class="rounded-xl bg-blue-50 border border-blue-100 p-3">
                    <p class="text-xs text-blue-700 uppercase font-semibold">Last payment</p>
                    <p class="mt-1 font-semibold text-blue-900">
                        @if ($lastPayment ?? null)
                            {{ \App\Services\Property\PropertyMoney::kes((float) ($lastPaymentAmount ?? $lastPayment->allocations->sum('amount'))) }}
                            <span class="block text-xs font-normal text-blue-700">{{ $lastPayment->paid_at?->format('Y-m-d') ?? '—' }}</span>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-3 flex flex-col justify-center">
                    <a href="{{ route('property.tenants.credit.ledger', $tenant, false) }}" data-turbo-frame="property-main" class="text-sm font-semibold text-indigo-700 hover:underline">View credit ledger →</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">Profile</h3>
                    <div class="mt-2 text-sm text-slate-700 space-y-1">
                        <p><span class="text-slate-500">Name:</span> {{ $tenant->name }}</p>
                        <p><span class="text-slate-500">Phone:</span> {{ $tenant->phone ?: '—' }}</p>
                        <p><span class="text-slate-500">Email:</span> {{ $tenant->email ?: '—' }}</p>
                        <p><span class="text-slate-500">National ID / ref:</span> {{ $tenant->national_id ?: '—' }}</p>
                        <p><span class="text-slate-500">Portal login linked:</span> {{ $tenant->user_id ? 'Yes' : 'No' }}</p>
                        <p><span class="text-slate-500">Tenant carry-forward:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_amount ?? 0)) }}</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">Notes</h3>
                    <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">
                        {{ trim((string) ($tenant->notes ?? '')) !== '' ? $tenant->notes : 'No notes added.' }}
                    </div>
                </div>
            </div>
            <x-property.entity-activity-feed :items="$activityFeed ?? []" />
        </div>
    @endif

    @if ($activeTab === 'leases')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Lease center</h3>
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
    @endif

    @if ($activeTab === 'invoices')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Invoice center</h3>
                <a href="{{ route('property.revenue.invoices', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-cyan-700 hover:underline">Open invoice workspace</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($recentInvoices ?? []) as $invoice)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <a href="{{ route('property.revenue.invoices.show', $invoice, false) }}" data-turbo-frame="property-main" class="font-medium text-indigo-600 hover:text-indigo-700">{{ $invoice->invoice_no ?: '#'.$invoice->id }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $invoice->issue_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($invoice->invoice_type ?? 'charge')) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $invoice->amount) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes(max(0, (float) $invoice->amount - (float) $invoice->amount_paid)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'payments')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Payment center</h3>
                <a href="{{ route('property.revenue.payments', ['q' => $tenant->name], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-teal-700 hover:underline">Record payment</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Channel</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($recentPayments ?? []) as $payment)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</td>
                            <td class="px-4 py-3 uppercase">{{ $payment->channel ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $payment->external_ref ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('property.payments.receipt.show', $payment, false) }}" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'notices')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Notice center</h3>
                <a href="{{ route('property.tenants.notices', ['tenant_id' => $tenant->id], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-amber-700 hover:underline">Create notice</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Due</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($recentNotices ?? []) as $notice)
                        <tr class="border-t border-slate-100 hover:bg-slate-50/70">
                            <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', (string) ($notice->notice_type ?? 'notice')) }}</td>
                            <td class="px-4 py-3 capitalize">{{ $notice->status ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $notice->due_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $notice->created_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No notices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'utilities')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Utility center</h3>
                <a href="{{ route('property.revenue.utilities', [], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-blue-700 hover:underline">Utility billing workspace</a>
            </div>
            <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Billing month</th>
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
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No utility readings linked to this tenant's units.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($activeTab === 'statement')
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Statement center</h3>
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
    @endif
</x-property.workspace>
