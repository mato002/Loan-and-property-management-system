<x-property.workspace
    title="Bank reconciliation"
    subtitle="Match cash-book movements against the imported Co-op bank statement."
    back-route="property.accounting.index"
    :stats="[
        ['label' => 'System side', 'value' => (string) (count($cashSide ?? [])), 'hint' => 'Cash book rows'],
        ['label' => 'Bank side', 'value' => (string) (count($bankSide ?? [])), 'hint' => 'Statement rows'],
        ['label' => 'Matched', 'value' => (string) (($bankSide ?? collect())->where('match_status', 'matched')->count()), 'hint' => 'M-Pesa vs receipts'],
        ['label' => 'Unmatched credits', 'value' => (string) (($bankSide ?? collect())->where('match_status', 'unmatched')->count()), 'hint' => 'In bank, not in system'],
    ]"
    :columns="[]"
    :table-rows="[]"
>
    <x-slot name="actions">
        <a href="{{ route('property.revenue.statements.index') }}" class="inline-flex rounded-xl bg-teal-800 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-900">Upload statement</a>
        @if (! empty($statement))
            <a href="{{ route('property.revenue.statements.show', $statement) }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Review lines</a>
            <form method="POST" action="{{ route('property.revenue.statements.recover', $statement) }}" class="inline">
                @csrf
                <button type="submit" class="inline-flex rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100">Recover missing</button>
            </form>
        @endif
        <a href="{{ route('property.equity.unmatched') }}" class="inline-flex rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unmatched queue</a>
    </x-slot>

    @if (! empty($statement))
        <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">{{ $statement->bank_name }} · {{ $statement->account_name }}</h3>
            <p class="mt-1 text-sm text-slate-600">
                Account {{ $statement->account_no }}
                · {{ $statement->periodLabel() }}
                · Opening {{ \App\Services\Property\PropertyMoney::kes((float) $statement->opening_balance) }}
                · Closing {{ \App\Services\Property\PropertyMoney::kes((float) $statement->closing_balance) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Credits {{ \App\Services\Property\PropertyMoney::kes((float) $statement->total_credit) }}
                · Debits {{ \App\Services\Property\PropertyMoney::kes((float) $statement->total_debit) }}
            </p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">System transactions</h3>
            <div class="mt-3 space-y-2 max-h-[420px] overflow-auto">
                @forelse (($cashSide ?? collect()) as $r)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="font-medium">{{ $r->entry_date?->format('Y-m-d') ?? '—' }} · {{ $r->account_name }}</div>
                        <div class="text-slate-600">{{ $r->reference ?: '—' }} · {{ \App\Services\Property\PropertyMoney::kes((float) $r->amount) }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No cash-book rows yet.</p>
                @endforelse
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-900">Bank statement side</h3>
            <div class="mt-3 space-y-2 max-h-[420px] overflow-auto">
                @forelse (($bankSide ?? collect()) as $r)
                    @if (isset($r->line_type))
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <div class="flex items-start justify-between gap-2">
                                <div class="font-medium">{{ $r->txn_date?->format('Y-m-d') ?? '—' }} · {{ $r->reference }}</div>
                                <span class="shrink-0 text-xs {{ $r->match_status === 'matched' ? 'text-emerald-700' : ($r->match_status === 'unmatched' ? 'text-amber-700' : 'text-slate-500') }}">
                                    {{ method_exists($r, 'displayMatchStatus') ? $r->displayMatchStatus() : ($r->match_status ?? '') }}
                                </span>
                            </div>
                            <div class="text-slate-600">
                                {{ $r->direction === 'debit' ? 'Dr' : 'Cr' }}
                                {{ \App\Services\Property\PropertyMoney::kes((float) ($r->amount ?? 0)) }}
                                @if ($r->counterparty)
                                    · {{ $r->counterparty }}
                                @endif
                            </div>
                            @if ($r->narration)
                                <div class="text-xs text-slate-500">{{ $r->narration }}</div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-lg border border-slate-200 p-3 text-sm">
                            <div class="font-medium">{{ $r->transaction_date ?? $r->created_at ?? '—' }}</div>
                            <div class="text-slate-600">{{ $r->reference ?? $r->transaction_id ?? '—' }} · {{ \App\Services\Property\PropertyMoney::kes((float) ($r->amount ?? 0)) }}</div>
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-slate-500">Import a Co-op statement with <code>property:import-coop-bank-statement</code>.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-property.workspace>
