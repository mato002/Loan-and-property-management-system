<x-property.workspace
    title="Matched payments"
    subtitle="Every payment that already has a tenant — M-Pesa, cash, bank, Equity, statement, or register import. Not tied to one bank."
    back-route="property.revenue.overview"
    :stats="array_merge(
        [['label' => 'All methods', 'value' => (string) ($totals['count'] ?? 0), 'hint' => \App\Services\Property\PropertyMoney::kes((float) ($totals['amount'] ?? 0))]],
        collect($methodStats ?? [])->take(3)->map(fn ($row) => [
            'label' => $row['label'],
            'value' => (string) $row['count'],
            'hint' => \App\Services\Property\PropertyMoney::kes((float) $row['amount']),
        ])->all()
    )"
>
    <x-slot name="actions">
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unmatched queue</a>
        <a href="{{ route('property.revenue.payments') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Record payment</a>
    </x-slot>

    <div class="mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">Last 7 days by method</h3>
            <p class="text-xs text-slate-500">Amounts in KES. Methods come from what this workspace actually collected.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2 text-left font-semibold">Date</th>
                        @foreach (($trendMethods ?? []) as $method)
                            <th class="px-4 py-2 text-right font-semibold">{{ $method }}</th>
                        @endforeach
                        <th class="px-4 py-2 text-right font-semibold">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach (($sourceTrend ?? []) as $row)
                        <tr>
                            <td class="px-4 py-2">{{ $row['date'] }}</td>
                            @foreach (($trendMethods ?? []) as $method)
                                <td class="px-4 py-2 text-right">{{ number_format((float) ($row[$method] ?? 0), 2) }}</td>
                            @endforeach
                            <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <form method="get" class="mb-4 grid grid-cols-1 items-end gap-3 md:grid-cols-6">
        <div>
            <label class="text-xs text-slate-500">Search</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ref, tenant, phone, account…" class="block w-full rounded-xl border-slate-300 shadow-sm">
        </div>
        <div>
            <label class="text-xs text-slate-500">Method</label>
            <select name="channel" class="block w-full rounded-xl border-slate-300 shadow-sm">
                <option value="">All methods</option>
                @foreach (($channelOptions ?? []) as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['channel'] ?? '') === (string) $value)>{{ $label }}{{ $value !== '' ? ' ('.$value.')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-slate-500">Tenant</label>
            <select name="tenant_id" class="block w-full rounded-xl border-slate-300 shadow-sm">
                <option value="">All</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected(($filters['tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-slate-500">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
        </div>
        <div>
            <label class="text-xs text-slate-500">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
        </div>
        <div class="flex flex-wrap gap-2">
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
            <a href="{{ route('property.equity.matched') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
        <div class="md:col-span-6">
            @include('property.agent.partials.export_dropdown', [
                'csvUrl' => route('property.equity.matched', array_merge(request()->query(), ['export' => 'csv'])),
                'xlsUrl' => route('property.equity.matched', array_merge(request()->query(), ['export' => 'xls'])),
                'pdfUrl' => route('property.equity.matched', array_merge(request()->query(), ['export' => 'pdf'])),
            ])
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Reference</th>
                    <th class="px-3 py-2">Method</th>
                    <th class="px-3 py-2">Origin</th>
                    <th class="px-3 py-2">Tenant</th>
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2">Property / unit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $payment)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $payment->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs font-semibold">{{ \App\Support\Property\PmPaymentPresentation::transactionRef($payment) }}</td>
                        <td class="px-3 py-2">{{ \App\Support\Property\PmPaymentPresentation::paymentMethod($payment, \App\Support\Property\PmPaymentPresentation::methodGroupFromChannel($payment->channel)) }}</td>
                        <td class="px-3 py-2 text-xs text-slate-600">{{ \App\Support\Property\PmPaymentPresentation::originLabel($payment) }}</td>
                        <td class="px-3 py-2">
                            @if ($payment->pm_tenant_id)
                                <a href="{{ route('property.tenants.show', $payment->pm_tenant_id) }}" class="font-medium text-blue-700 hover:underline">{{ $payment->tenant?->name ?? 'Tenant #'.$payment->pm_tenant_id }}</a>
                                @if ($payment->tenant?->account_number)
                                    <div class="font-mono text-[11px] text-slate-400">{{ $payment->tenant->account_number }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2"><x-phone-link :value="\App\Support\Property\PmPaymentPresentation::payerPhone($payment, '')" /></td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $payment->amount) }}</td>
                        <td class="px-3 py-2 text-slate-600">{!! \App\Support\Property\PmPaymentPresentation::propertyUnit($payment) !!}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">No matched tenant payments in this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</x-property.workspace>
