<x-loan-layout>
    <x-loan.page title="Journal {{ $entry->entry_date->format('Y-m-d') }}" subtitle="{{ $entry->reference ?? 'No reference' }}">
        <x-slot name="actions">
            <a href="{{ route('loan.accounting.journal.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            @include('loan.accounting.partials.export_buttons')
        </x-slot>

        @if ($entry->description)
            <p class="text-sm text-slate-600 mb-4">{{ $entry->description }}</p>
        @endif
        <p class="text-xs text-slate-500 mb-4">Created by {{ $entry->createdByUser?->name ?? '—' }} · {{ $entry->created_at->format('Y-m-d H:i') }}</p>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-3xl">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">Account</th>
                        <th class="px-5 py-3 text-right">Debit</th>
                        <th class="px-5 py-3 text-right">Credit</th>
                        <th class="px-5 py-3">Memo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs text-slate-700">{{ $line->account->code }}</span>
                                <span class="text-slate-600"> {{ $line->account->name }}</span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $line->memo ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 font-semibold text-slate-800">
                    <tr>
                        <td class="px-5 py-3">Totals</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $entry->lines->sum('debit'), 2) }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ number_format((float) $entry->lines->sum('credit'), 2) }}</td>
                        <td class="px-5 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-loan.page>
</x-loan-layout>
