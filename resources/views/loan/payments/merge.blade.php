<x-loan-layout>
    <x-loan.page
        title="Merge payments"
        subtitle="Combine two or more unposted lines into one parent row (same currency; totals summed)."
    >
        <x-slot name="actions">
            <a href="{{ route('loan.payments.unposted') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to unposted</a>
        </x-slot>

        @include('loan.payments.partials.flash')

        @if ($errors->has('payment_ids'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 mb-4">
                {{ $errors->first('payment_ids') }}
            </div>
        @endif

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <form method="post" action="{{ route('loan.payments.merge.store') }}" class="divide-y divide-slate-100">
                @csrf
                <div class="px-5 py-4">
                    <p class="text-sm text-slate-600 mb-3">Select at least two unposted payments (merged parents excluded). The new parent appears under Merged and in Unposted until posted.</p>
                    <div class="overflow-x-auto max-h-[420px] overflow-y-auto border border-slate-100 rounded-lg">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 w-10"></th>
                                    <th class="px-4 py-2">Reference</th>
                                    <th class="px-4 py-2">Loan</th>
                                    <th class="px-4 py-2">Client</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2">When</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($candidates as $c)
                                    <tr class="hover:bg-slate-50/80">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" name="payment_ids[]" value="{{ $c->id }}" class="rounded border-slate-300" @checked(is_array(old('payment_ids')) && in_array($c->id, old('payment_ids', []), true)) />
                                        </td>
                                        <td class="px-4 py-2 font-mono text-xs">{{ $c->reference }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ $c->loan?->loan_number ?? '—' }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ $c->loan?->loanClient?->full_name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums">{{ $c->currency }} {{ number_format((float) $c->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $c->transaction_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No eligible unposted rows to merge.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-600 mb-1">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Create merged payment</button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
