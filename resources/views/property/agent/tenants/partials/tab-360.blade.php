@php
    $profileStatus = $profileStatus ?? ['label' => '—', 'hint' => ''];
    $standingExtras = $standingExtras ?? [];
    $standingTotal = (float) collect($standingExtras)->sum('amount');
    $depositSnapshot = $depositSnapshot ?? ['held' => 0.0, 'expected' => 0.0, 'lines' => []];
@endphp

<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">360° tenant view</h3>
            <p class="mt-1 text-xs text-slate-500">Identity, occupancy, standing extras, deposits, and canonical balances in one place.</p>
        </div>
        <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $profileStatus['label'] ?? '—' }} · {{ $profileStatus['hint'] ?? '' }}</span>
    </div>
    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
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
        <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-3">
            <p class="text-xs text-indigo-700 uppercase font-semibold">Total due</p>
            <p class="mt-1 text-lg font-bold text-indigo-900">{{ \App\Services\Property\PropertyMoney::kes((float) ($totalDue['total_due'] ?? 0)) }}</p>
            <p class="mt-1 text-[11px] text-indigo-700">AR + uninvoiced CF − credit</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Billable invoices</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">{{ (string) ($invoiceTotals['count'] ?? 0) }}</p>
        <p class="text-xs text-slate-500">{{ (string) ($invoiceTotals['open_count'] ?? 0) }} open</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Monthly rent</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($monthlyRentTotal ?? 0)) }}</p>
        <p class="text-xs text-slate-500">{{ (int) ($activeLeaseCount ?? 0) }} active {{ \Illuminate\Support\Str::plural('lease', (int) ($activeLeaseCount ?? 0)) }}</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Standing extras</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes($standingTotal) }}</p>
        <p class="text-xs text-slate-500">{{ count($standingExtras) }} {{ \Illuminate\Support\Str::plural('line', count($standingExtras)) }} / month</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <p class="text-[11px] uppercase tracking-wide text-slate-500">Deposits held</p>
        <p class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['held'] ?? 0)) }}</p>
        <p class="text-xs text-slate-500">Expected {{ \App\Services\Property\PropertyMoney::kes((float) ($depositSnapshot['expected'] ?? 0)) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Identity</h3>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Account</dt><dd class="font-mono text-slate-900">{{ $tenant->account_number ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-900">{{ $tenant->name }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Phone</dt><dd class="text-slate-900">{{ $tenant->phone ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Email</dt><dd class="text-slate-900 break-all">{{ $tenant->email ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">National ID / ref</dt><dd class="text-slate-900">{{ $tenant->national_id ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Emergency contact</dt><dd class="text-slate-900">{{ $tenant->emergency_contact ?: '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Portal login</dt><dd class="text-slate-900">{{ $tenant->user_id ? 'Linked' : 'Not linked' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Risk</dt><dd class="capitalize text-slate-900">{{ $tenant->risk_level ?: 'normal' }}</dd></div>
        </dl>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Occupancy</h3>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex flex-col gap-0.5"><dt class="text-slate-500">Property / unit</dt><dd class="font-medium text-slate-900">{{ $occupancyLabel ?? '—' }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Active leases</dt><dd class="text-slate-900">{{ (int) ($activeLeaseCount ?? 0) }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">All leases</dt><dd class="text-slate-900">{{ (int) ($tenant->leases_count ?? 0) }}</dd></div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Last payment</dt>
                <dd class="text-slate-900">
                    @if ($lastPayment ?? null)
                        {{ \App\Services\Property\PropertyMoney::kes((float) ($lastPaymentAmount ?? 0)) }}
                        <span class="block text-xs text-slate-500">{{ $lastPayment->paid_at?->format('Y-m-d') ?? '—' }}</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:justify-between gap-0.5"><dt class="text-slate-500">Credit ledger</dt>
                <dd><a href="{{ route('property.tenants.credit.ledger', $tenant, false) }}" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">Open →</a></dd>
            </div>
        </dl>
        <p class="mt-3 text-sm text-slate-600 whitespace-pre-wrap">{{ trim((string) ($tenant->notes ?? '')) !== '' ? $tenant->notes : 'No notes added.' }}</p>
    </div>

    @if (($activityFeed ?? []) !== [])
        <x-property.entity-activity-feed :items="$activityFeed" />
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Recent activity</h3>
            <p class="text-sm text-slate-500">No recent invoices, payments, or notices yet.</p>
        </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-x-auto">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900">Standing extras</h3>
            <a href="{{ route('property.tenants.show', ['tenant' => $tenant->id, 'tab' => 'utilities'], false) }}" data-turbo-frame="property-main" class="text-xs font-semibold text-indigo-700 hover:underline">Utilities tab</a>
        </div>
        <table class="min-w-full border-collapse text-sm [&_th]:border [&_th]:border-slate-200 [&_td]:border [&_td]:border-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2">Charge</th>
                    <th class="px-4 py-2">Unit</th>
                    <th class="px-4 py-2">Monthly</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($standingExtras as $row)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2">{{ $row['type_label'] ?? '—' }}</td>
                        <td class="px-4 py-2">{{ ($row['property_name'] ?? '—').' / '.($row['unit_label'] ?? '—') }}</td>
                        <td class="px-4 py-2 tabular-nums font-medium">{{ \App\Services\Property\PropertyMoney::kes((float) ($row['amount'] ?? 0)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No standing extras on active leases.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-900">Carry-forward / take-on</h3>
        <p class="mt-1 text-xs text-slate-500">Profile B/F as of {{ $tenant->opening_arrears_as_of?->format('Y-m-d') ?? '—' }}</p>
        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
            <p><span class="text-slate-500">Rent</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_rent ?? 0)) }}</p>
            <p><span class="text-slate-500">Utilities</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_utilities ?? 0)) }}</p>
            <p><span class="text-slate-500">Penalties</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_penalties ?? 0)) }}</p>
            <p><span class="text-slate-500">Other</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_other ?? 0)) }}</p>
        </div>
        <p class="mt-2 text-sm font-semibold">Tenant total {{ \App\Services\Property\PropertyMoney::kes((float) ($tenant->opening_arrears_amount ?? 0)) }}</p>
        @if ((float) ($leaseCarryForward['total'] ?? 0) > 0)
            <p class="mt-2 text-sm">
                Lease carry-forward {{ \App\Services\Property\PropertyMoney::kes((float) $leaseCarryForward['total']) }}
                @if ($leaseCarryForward['invoiced'] ?? false)
                    <span class="text-xs text-emerald-700">(posted as invoices)</span>
                @else
                    <span class="text-xs text-amber-700">(not yet invoiced)</span>
                @endif
            </p>
        @endif
        @if (is_array($tenant->opening_arrears_items) && count($tenant->opening_arrears_items) > 0)
            <ul class="mt-3 space-y-1 text-xs text-amber-900 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                @foreach ($tenant->opening_arrears_items as $line)
                    @php
                        $lineType = (string) ($line['type'] ?? 'other');
                        $lineLabel = trim((string) ($line['label'] ?? '')) !== ''
                            ? trim((string) ($line['label'] ?? ''))
                            : ucfirst(str_replace('_', ' ', $lineType));
                    @endphp
                    <li>{{ $lineLabel }}{{ ! empty($line['period']) ? ' ('.$line['period'].')' : '' }}: {{ \App\Services\Property\PropertyMoney::kes((float) ($line['amount'] ?? 0)) }}</li>
                @endforeach
            </ul>
        @endif
        @if (! empty($leaseCarryForward['lines'] ?? []))
            <ul class="mt-3 space-y-1 text-xs text-indigo-900 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                @foreach ($leaseCarryForward['lines'] as $line)
                    <li>
                        Lease #{{ $line['lease_id'] }} · {{ ucfirst(str_replace('_', ' ', (string) ($line['charge_type'] ?? 'other'))) }}
                        : {{ \App\Services\Property\PropertyMoney::kes((float) ($line['amount'] ?? 0)) }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
