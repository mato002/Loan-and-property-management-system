<x-loan-layout>
    <x-loan.page title="Journal {{ $entry->entry_date->format('Y-m-d') }}" subtitle="{{ $entry->reference ?? 'No reference' }}">
        @php
            $totalDebit = (float) $entry->lines->sum('debit');
            $totalCredit = (float) $entry->lines->sum('credit');
            $entryStatus = strtolower((string) ($entry->status ?? \App\Models\AccountingJournalEntry::STATUS_POSTED));
            $isBalanced = abs($totalDebit - $totalCredit) < 0.005;
            $currency = config('app.currency', 'KES');
            $reversalBlockers = collect($reversalBlockers ?? []);
        @endphp
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.journal.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            @include('loan.accounting.partials.export_buttons')
            <button type="button" onclick="window.print()" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Print</button>
            @if ($entryStatus === \App\Models\AccountingJournalEntry::STATUS_POSTED)
                <form method="post" action="{{ route('loan.accounting.journal.reverse', $entry) }}" class="inline" data-swal-confirm="Reverse this posted journal entry?">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm hover:bg-amber-100 transition-colors">Reverse</button>
                </form>
            @endif
            @if ($entryStatus === \App\Models\AccountingJournalEntry::STATUS_DRAFT)
                <form method="post" action="{{ route('loan.accounting.journal.destroy', $entry) }}" class="inline" data-swal-confirm="Delete this draft journal entry and all lines?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-100 transition-colors">Delete</button>
                </form>
            @endif
        </x-slot>

        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Posting status</p>
                    <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isBalanced ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                        @if ($entryStatus === \App\Models\AccountingJournalEntry::STATUS_REVERSED)
                            ⚠ Reversed
                        @elseif ($entryStatus === \App\Models\AccountingJournalEntry::STATUS_DRAFT)
                            📝 Draft
                        @else
                            {{ $isBalanced ? '✅ Posted' : '⚠ Out of balance' }}
                        @endif
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-slate-600">
                    <p><span class="font-semibold text-slate-700">Entry date:</span> {{ $entry->entry_date->format('Y-m-d') }}</p>
                    <p><span class="font-semibold text-slate-700">Transaction date:</span> {{ optional($entry->created_at)->format('Y-m-d') }}</p>
                    <p><span class="font-semibold text-slate-700">Created by:</span> {{ $entry->createdByUser?->name ?? '—' }}</p>
                    <p><span class="font-semibold text-slate-700">Last updated:</span> {{ optional($entry->updated_at)->format('Y-m-d H:i') }}</p>
                    <p><span class="font-semibold text-slate-700">Reference:</span> {{ $entry->reference ?? '—' }}</p>
                    <p><span class="font-semibold text-slate-700">Approval:</span> {{ $entry->approvedByUser?->name ?? 'Not approved' }}</p>
                </div>
            </div>
            @if ($entry->reversed_from_id)
                <p class="mt-3 text-xs text-amber-700">This entry reverses journal #{{ $entry->reversed_from_id }}.</p>
            @endif
            <div class="mt-3 rounded-lg bg-slate-50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Narration</p>
                <p class="mt-1 text-sm text-slate-700">{{ $entry->description ?: 'No narration provided.' }}</p>
            </div>
        </div>

        @if ($entryStatus === \App\Models\AccountingJournalEntry::STATUS_POSTED && $reversalBlockers->isNotEmpty())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-sm font-semibold text-amber-800">Reversal likely to fail on liquidity rules</p>
                <p class="mt-1 text-xs text-amber-700">One or more accounts would go below allowed limits if reversed now.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-amber-800">
                    @foreach ($reversalBlockers as $blocker)
                        <li>
                            {{ $blocker['code'] }} - {{ $blocker['name'] }}:
                            @if (($blocker['reason'] ?? '') === 'overdraft_limit')
                                overdraft limit exceeded (projected {{ number_format((float) ($blocker['projected_balance'] ?? 0), 2) }}, limit {{ number_format((float) ($blocker['overdraft_limit'] ?? 0), 2) }}).
                            @else
                                insufficient funds (projected {{ number_format((float) ($blocker['projected_balance'] ?? 0), 2) }}).
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">Code</th>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3 text-right">Debit</th>
                        <th class="px-5 py-3 text-right">Credit</th>
                        <th class="px-5 py-3">Memo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $line->account->code }}</td>
                            <td class="px-5 py-3 text-slate-700">{{ $line->account->name }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $line->memo ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-100 font-bold text-slate-900">
                    <tr>
                        <td class="px-5 py-3" colspan="2">Totals ({{ $currency }})</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalDebit, 2) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format($totalCredit, 2) }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-loan.page>
</x-loan-layout>
