<x-property-layout>
    @php
        $status = (string) ($filters['status'] ?? '');
        $pageTitle = $status === 'matched' ? 'Matched Equity Payments' : 'All Equity Payments';
        $pageSubtitle = $status === 'matched'
            ? 'Payments that have been successfully matched/posted from Equity and SMS ingest.'
            : 'Full transaction audit including matched and unmatched items.';
    @endphp
    <x-slot name="header">{{ $pageTitle }}</x-slot>

    <x-property.page
        title="{{ $pageTitle }}"
        subtitle="{{ $pageSubtitle }}"
    >
        <div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total All Sources</p>
                <p class="mt-1 text-lg font-semibold text-emerald-900">{{ number_format((int) ($sourceStats['all']['count'] ?? 0)) }} txns</p>
                <p class="text-sm text-emerald-800">KES {{ number_format((float) ($sourceStats['all']['amount'] ?? 0), 2) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Equity API</p>
                <p class="mt-1 text-lg font-semibold text-indigo-900">{{ number_format((int) ($sourceStats['equity']['count'] ?? 0)) }} txns</p>
                <p class="text-sm text-indigo-800">KES {{ number_format((float) ($sourceStats['equity']['amount'] ?? 0), 2) }}</p>
                <p class="mt-1 text-xs font-medium text-indigo-700">{{ number_format((float) ($sourceStats['equity']['percent'] ?? 0), 1) }}% of total</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">SMS Ingest (M-Pesa/Equity)</p>
                <p class="mt-1 text-lg font-semibold text-amber-900">{{ number_format((int) ($sourceStats['sms_forwarder']['count'] ?? 0)) }} txns</p>
                <p class="text-sm text-amber-800">KES {{ number_format((float) ($sourceStats['sms_forwarder']['amount'] ?? 0), 2) }}</p>
                <p class="mt-1 text-xs font-medium text-amber-700">{{ number_format((float) ($sourceStats['sms_forwarder']['percent'] ?? 0), 1) }}% of total</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-700">Manual / Legacy</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format((int) ($sourceStats['manual']['count'] ?? 0)) }} txns</p>
                <p class="text-sm text-slate-700">KES {{ number_format((float) ($sourceStats['manual']['amount'] ?? 0), 2) }}</p>
                <p class="mt-1 text-xs font-medium text-slate-700">{{ number_format((float) ($sourceStats['manual']['percent'] ?? 0), 1) }}% of total</p>
            </div>
        </div>

        <div class="mb-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-900">7-day collection trend by source</h3>
                <p class="text-xs text-slate-500">Amounts in KES by day.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Date</th>
                            <th class="px-4 py-2 text-right font-semibold">Equity API</th>
                            <th class="px-4 py-2 text-right font-semibold">SMS Ingest</th>
                            <th class="px-4 py-2 text-right font-semibold">Manual / Legacy</th>
                            <th class="px-4 py-2 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach(($sourceTrend ?? []) as $row)
                            <tr>
                                <td class="px-4 py-2">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) ($row['equity'] ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) ($row['sms_forwarder'] ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format((float) ($row['manual'] ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-right font-semibold text-slate-900">{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <form method="get" class="mb-4 grid grid-cols-1 md:grid-cols-8 gap-3 items-end">
            <div>
                <label class="text-xs text-slate-500">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Txn, account, phone, tenant..." class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Status</label>
                <select name="status" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    <option value="matched" @selected($filters['status'] === 'matched')>Matched</option>
                    <option value="unmatched" @selected($filters['status'] === 'unmatched')>Unmatched</option>
                    <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Source</label>
                <select name="source" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    <option value="equity" @selected($filters['source'] === 'equity')>Equity API</option>
                    <option value="sms_forwarder" @selected($filters['source'] === 'sms_forwarder')>SMS Ingest (M-Pesa/Equity)</option>
                    <option value="manual" @selected($filters['source'] === 'manual')>Manual / Legacy</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Tenant</label>
                <select name="tenant_id" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="">All</option>
                    @foreach($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected($filters['tenant_id'] === (string) $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="block w-full rounded-xl border-slate-300 shadow-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Per page</label>
                <select name="per_page" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    @foreach ([10, 30, 50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 30) === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Sort</label>
                <select name="sort" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="transaction_date" @selected(($filters['sort'] ?? 'transaction_date') === 'transaction_date')>Transaction date</option>
                    <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Amount</option>
                    <option value="status" @selected(($filters['sort'] ?? '') === 'status')>Status</option>
                    <option value="transaction_id" @selected(($filters['sort'] ?? '') === 'transaction_id')>Transaction ID</option>
                    <option value="id" @selected(($filters['sort'] ?? '') === 'id')>ID</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-500">Direction</label>
                <select name="dir" class="block w-full rounded-xl border-slate-300 shadow-sm">
                    <option value="desc" @selected(($filters['dir'] ?? 'desc') === 'desc')>Desc</option>
                    <option value="asc" @selected(($filters['dir'] ?? '') === 'asc')>Asc</option>
                </select>
            </div>
            <button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Apply</button>
            <div class="flex flex-wrap gap-2 md:col-span-8">
                <a href="{{ url()->current() }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                @include('property.agent.partials.export_dropdown', [
                    'csvUrl' => route('property.equity.all', array_merge(request()->query(), ['export' => 'csv'])),
                    'xlsUrl' => route('property.equity.all', array_merge(request()->query(), ['export' => 'xls'])),
                    'pdfUrl' => route('property.equity.all', array_merge(request()->query(), ['export' => 'pdf'])),
                    'class' => 'rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50',
                ])
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Date</th>
                        <th class="px-4 py-3 text-left font-bold">Transaction</th>
                        <th class="px-4 py-3 text-left font-bold">Source</th>
                        <th class="px-4 py-3 text-left font-bold">Tenant</th>
                        <th class="px-4 py-3 text-right font-bold">Amount</th>
                        <th class="px-4 py-3 text-left font-bold">Account</th>
                        <th class="px-4 py-3 text-left font-bold">Phone</th>
                        <th class="px-4 py-3 text-left font-bold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3">{{ optional($item->transaction_date)->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">{{ $item->transaction_id }}</td>
                            <td class="px-4 py-3">
                                @if ($item->payment_method === 'equity')
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">Equity API</span>
                                @elseif ($item->payment_method === 'sms_forwarder')
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">SMS Ingest</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Manual / Legacy</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->tenant?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $item->amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $item->account_number ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $item->phone ?: '—' }}</td>
                            <td class="px-4 py-3">{{ ucfirst($item->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}
            </p>
            {{ $items->links() }}
        </div>
    </x-property.page>
</x-property-layout>

