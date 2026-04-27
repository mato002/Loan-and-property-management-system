<x-property.workspace
    :title="'Tenant Statement: '.$tenant->name"
    subtitle="Full tenant ledger — invoices, payments, and running balance."
    back-route="property.tenants.directory"
    :stats="$stats"
    :columns="$columns"
    :table-rows="$tableRows"
    empty-title="No statement entries"
    empty-hint="This tenant will have a statement once invoices or payments exist."
>
    <x-slot name="actions">
        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >Print / Save PDF</button>
        <a
            href="{{ route('property.tenants.show', $tenant, false) }}"
            data-turbo-frame="property-main"
            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >Tenant profile</a>
    </x-slot>

    <x-slot name="toolbar">
        <form method="get" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-600">From</label>
                <input
                    type="date"
                    name="from"
                    value="{{ $filters['from'] ?? request('from') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600">To</label>
                <input
                    type="date"
                    name="to"
                    value="{{ $filters['to'] ?? request('to') }}"
                    class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                />
            </div>
            <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply</button>
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Reset</a>
        </form>
    </x-slot>

    <x-slot name="above">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Tenant details</h3>
                <div class="mt-2 text-sm text-slate-700 space-y-1">
                    <p><span class="text-slate-500">Name:</span> {{ $tenant->name }}</p>
                    <p><span class="text-slate-500">Phone:</span> <x-phone-link :value="$tenant->phone" /></p>
                    <p><span class="text-slate-500">Email:</span> {{ $tenant->email ?: '—' }}</p>
                    <p><span class="text-slate-500">National ID / ref:</span> {{ $tenant->national_id ?: '—' }}</p>
                    <p><span class="text-slate-500">Account #:</span> {{ $tenant->account_number ?: '—' }}</p>
                    <p><span class="text-slate-500">Risk:</span> {{ ucfirst((string) $tenant->risk_level) }}</p>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Lease summary</h3>
                <div class="mt-2 space-y-2 text-sm text-slate-700">
                    @forelse ($leaseSummary as $l)
                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="font-semibold text-slate-900">{{ $l['units'] }}</div>
                                <div class="text-xs uppercase tracking-wide text-slate-500">{{ ucfirst($l['status']) }}</div>
                            </div>
                            <div class="mt-1 text-slate-700">
                                <span class="text-slate-500">Start:</span> {{ $l['start'] }}
                                <span class="text-slate-300">·</span>
                                <span class="text-slate-500">End:</span> {{ $l['end'] }}
                                <span class="text-slate-300">·</span>
                                <span class="text-slate-500">Rent:</span> {{ $l['rent'] }}
                            </div>
                        </div>
                    @empty
                        <div class="text-slate-500">No lease history found.</div>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-900">Billing snapshot</h3>
                <div class="mt-2 text-sm text-slate-700 space-y-1">
                    <p><span class="text-slate-500">Invoices:</span> {{ $invoiceSummary['count'] }}</p>
                    <p><span class="text-slate-500">Opening arrears:</span> {{ \App\Services\Property\PropertyMoney::kes((float) ($invoiceSummary['opening_arrears'] ?? 0)) }}</p>
                    <p class="text-xs text-slate-500">
                        Rent {{ \App\Services\Property\PropertyMoney::kes((float) ($invoiceSummary['opening_arrears_rent'] ?? 0)) }},
                        Utilities {{ \App\Services\Property\PropertyMoney::kes((float) ($invoiceSummary['opening_arrears_utilities'] ?? 0)) }},
                        Penalties {{ \App\Services\Property\PropertyMoney::kes((float) ($invoiceSummary['opening_arrears_penalties'] ?? 0)) }},
                        Other {{ \App\Services\Property\PropertyMoney::kes((float) ($invoiceSummary['opening_arrears_other'] ?? 0)) }}
                    </p>
                    @if (! empty($invoiceSummary['opening_arrears_items']))
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-2 py-2">
                            <p class="text-xs font-semibold text-amber-900">Opening arrears lines</p>
                            <ul class="mt-1 space-y-1 text-xs text-amber-800">
                                @foreach ($invoiceSummary['opening_arrears_items'] as $line)
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
                    <p><span class="text-slate-500">Invoiced total:</span> {{ \App\Services\Property\PropertyMoney::kes((float) $invoiceSummary['total']) }}</p>
                    <p><span class="text-slate-500">Paid on invoices:</span> {{ \App\Services\Property\PropertyMoney::kes((float) $invoiceSummary['paid']) }}</p>
                    <p><span class="text-slate-500">Outstanding:</span> {{ \App\Services\Property\PropertyMoney::kes((float) $invoiceSummary['outstanding']) }}</p>
                    <p><span class="text-slate-500">Open invoices:</span> {{ $invoiceSummary['openCount'] }}</p>
                </div>
                <hr class="my-3 border-slate-200">
                <div class="text-sm text-slate-700 space-y-1">
                    <p><span class="text-slate-500">Payments:</span> {{ $paymentSummary['count'] }}</p>
                    <p><span class="text-slate-500">Completed:</span> {{ $paymentSummary['completedCount'] }} ({{ \App\Services\Property\PropertyMoney::kes((float) $paymentSummary['completedAmount']) }})</p>
                    <p><span class="text-slate-500">Pending:</span> {{ $paymentSummary['pendingCount'] }} ({{ \App\Services\Property\PropertyMoney::kes((float) $paymentSummary['pendingAmount']) }})</p>
                    <p><span class="text-slate-500">Failed:</span> {{ $paymentSummary['failedCount'] }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Tenant notes</h3>
            <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">
                {{ trim((string) ($tenant->notes ?? '')) !== '' ? $tenant->notes : 'No notes added.' }}
            </div>
        </div>
    </x-slot>
</x-property.workspace>

