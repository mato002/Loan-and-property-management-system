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

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Reference</th>
                    <th class="px-3 py-2">Counterparty</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Narration</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lines as $line)
                    <tr>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $line->txn_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono text-xs font-semibold">{{ $line->reference }}</td>
                        <td class="px-3 py-2">{{ $line->counterparty ?: '—' }}</td>
                        <td class="px-3 py-2 tabular-nums {{ $line->direction === 'debit' ? 'text-rose-700' : 'text-emerald-800' }}">
                            {{ $line->direction === 'debit' ? '−' : '+' }}{{ \App\Services\Property\PropertyMoney::kes((float) $line->amount) }}
                        </td>
                        <td class="px-3 py-2">
                            <span class="text-xs font-semibold {{ $line->match_status === 'matched' ? 'text-emerald-700' : ($line->match_status === 'unmatched' ? 'text-amber-700' : 'text-slate-500') }}">
                                {{ $line->displayMatchStatus() }}
                                @if ($line->matched_type)
                                    <span class="font-normal text-slate-400">({{ $line->matched_type }})</span>
                                @endif
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-500 max-w-xs truncate" title="{{ $line->narration }}">{{ $line->narration ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-slate-500">No lines for this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lines->links() }}</div>
</x-property.workspace>
