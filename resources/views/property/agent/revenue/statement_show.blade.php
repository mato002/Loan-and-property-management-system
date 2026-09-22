<x-property.workspace
    title="Statement · {{ $statement->bank_name }}"
    subtitle="{{ $statement->account_name }} · {{ $statement->periodLabel() }}"
    back-route="property.revenue.statements.index"
    :stats="[
        ['label' => 'Matched', 'value' => (string) ($counts['matched'] ?? 0), 'hint' => 'Already in system'],
        ['label' => 'Unmatched', 'value' => (string) ($counts['unmatched'] ?? 0), 'hint' => 'Need recovery / assign'],
        ['label' => 'Bank only', 'value' => (string) ($counts['bank_only'] ?? 0), 'hint' => 'Cheques / charges'],
        ['label' => 'Credits', 'value' => \App\Services\Property\PropertyMoney::kes((float) $statement->total_credit), 'hint' => 'Statement total'],
    ]"
>
    <x-slot name="actions">
        <form method="POST" action="{{ route('property.revenue.statements.recover', $statement) }}" class="inline">
            @csrf
            <button type="submit" class="inline-flex rounded-xl bg-amber-700 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-800">Recover missing → Unmatched</button>
        </form>
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open Unmatched</a>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('property.revenue.statements.show', $statement) }}" class="rounded-lg px-3 py-1.5 {{ $status === '' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }}">All</a>
        <a href="{{ route('property.revenue.statements.show', [$statement, 'status' => 'unmatched']) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'unmatched' ? 'bg-amber-700 text-white' : 'bg-slate-100 text-slate-700' }}">Unmatched</a>
        <a href="{{ route('property.revenue.statements.show', [$statement, 'status' => 'matched']) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'matched' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-700' }}">Matched</a>
        <a href="{{ route('property.revenue.statements.show', [$statement, 'status' => 'bank_only']) }}" class="rounded-lg px-3 py-1.5 {{ $status === 'bank_only' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-700' }}">Bank only</a>
    </div>

    <p class="mb-3 text-sm text-slate-600">
        Tenant match is by <span class="font-semibold">M-Pesa receipt code</span> (and phone when one deposit is split).
        Bank payer name is not used — two people can share a name.
    </p>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Reference</th>
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2">Payer on bank</th>
                    <th class="px-3 py-2">Tenant in system</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">How it matched</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lines as $line)
                    @php
                        $tenantId = $line->matchedTenantId();
                        $tenantAccount = $line->matchedTenantAccount();
                        $tenantUnit = $line->matchedUnitLabel();
                        $tenantName = $line->matchedTenantName();
                        $phone = $line->displayPhone();
                    @endphp
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $line->txn_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="font-mono text-xs font-semibold text-slate-900">{{ $line->reference ?: '—' }}</div>
                            @if ($line->line_type === 'mpesa_c2b')
                                <div class="mt-0.5 text-[11px] text-slate-400">M-Pesa match key</div>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            @if ($phone !== '')
                                <div class="font-mono text-xs font-semibold text-slate-900">{{ $phone }}</div>
                                @if ($line->phone)
                                    <div class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $line->phone }}</div>
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $line->counterparty ?: '—' }}</td>
                        <td class="px-3 py-2">
                            @if ($tenantAccount !== '' || $tenantName !== '')
                                @if ($tenantAccount !== '')
                                    <div class="font-mono text-xs font-semibold text-slate-900">{{ $tenantAccount }}</div>
                                @endif
                                @if ($tenantUnit !== '')
                                    <div class="text-xs text-slate-500">{{ $tenantUnit }}</div>
                                @endif
                                @if ($tenantName !== '')
                                    @if ($tenantId)
                                        <a href="{{ route('property.tenants.show', $tenantId) }}" class="text-sm font-medium text-blue-700 hover:underline">{{ $tenantName }}</a>
                                    @else
                                        <div class="text-sm text-slate-700">{{ $tenantName }}</div>
                                    @endif
                                @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 tabular-nums {{ $line->direction === 'debit' ? 'text-rose-700' : 'text-emerald-800' }}">
                            {{ $line->direction === 'debit' ? '−' : '+' }}{{ \App\Services\Property\PropertyMoney::kes((float) $line->amount) }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="text-xs font-semibold {{ $line->match_status === 'matched' ? 'text-emerald-700' : ($line->match_status === 'unmatched' ? 'text-amber-700' : 'text-slate-500') }}">
                                {{ $line->displayMatchStatus() }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-600 max-w-xs" title="{{ $line->narration }}">
                            <div>{{ $line->matchReason() }}</div>
                            @if ($line->narration)
                                <div class="mt-0.5 text-slate-400 truncate">{{ $line->narration }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">
                            <p class="font-medium text-slate-700">No transactions were read from this upload.</p>
                            <p class="mt-1 text-sm">
                                Header totals can still show on the list even when the PDF text was not extracted.
                                Re-upload the same statement as a <span class="font-semibold">.txt</span> export
                                (or use Upload &amp; import again after the latest update).
                            </p>
                            @if ($statement->source_filename)
                                <p class="mt-2 text-xs font-mono text-slate-400">{{ $statement->source_filename }}</p>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lines->links() }}</div>
</x-property.workspace>
