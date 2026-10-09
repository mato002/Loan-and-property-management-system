<x-property.workspace
    title="Upload bank / M-Pesa statement"
    subtitle=""
    back-route="property.revenue.payments"
>
    <x-slot name="actions">
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unmatched queue</a>
        <a href="{{ route('property.accounting.cash_bank.reconciliation') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cash &amp; Bank</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Upload statement</h2>

        <form method="POST" action="{{ route('property.revenue.statements.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Provider</label>
                <select name="provider" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach (($providers ?? []) as $value => $label)
                        <option value="{{ $value }}" @selected(old('provider', 'auto') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">File (CSV / TXT / PDF)</label>
                <input type="file" name="statement_file" required accept=".csv,.txt,.pdf,.xls,.xlsx" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-white">
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
                <input type="hidden" name="recover_missing" value="0">
                <input type="checkbox" name="recover_missing" value="1" id="recover_missing" class="rounded border-slate-300" checked>
                <label for="recover_missing" class="text-sm text-slate-700">Recover unmatched credits</label>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="inline-flex rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">Upload &amp; import</button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-sm font-semibold text-slate-900">Recent uploads</h2>
            <form method="get" action="{{ route('property.revenue.statements.index', false) }}" data-turbo="false" class="flex flex-wrap items-center gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Bank, account, or file" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Filter</button>
                @if (($filters['q'] ?? '') !== '')
                    <a href="{{ route('property.revenue.statements.index', false) }}" class="text-sm font-medium text-slate-600 hover:underline">Clear</a>
                @endif
                @include('property.agent.partials.export_dropdown', [
                    'route' => 'property.revenue.statements.index',
                    'query' => request()->except(['export', 'page']),
                ])
            </form>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Bank</th>
                        <th class="px-3 py-2">Account</th>
                        <th class="px-3 py-2">Period</th>
                        <th class="px-3 py-2">Credits</th>
                        <th class="px-3 py-2">File</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse (($statements ?? collect()) as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium text-slate-900">{{ $row->bank_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->account_no ?: $row->account_name }}</td>
                            <td class="px-3 py-2 text-slate-600">{{ $row->periodLabel() }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ \App\Services\Property\PropertyMoney::kes((float) $row->total_credit) }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $row->source_filename ?: '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('property.revenue.statements.show', $row) }}" class="font-semibold text-blue-700 hover:underline">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No statements uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($statements, 'links'))
            <div class="mt-3">{{ $statements->links() }}</div>
        @endif
    </div>
</x-property.workspace>
