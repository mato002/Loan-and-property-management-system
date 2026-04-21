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

        <form method="get" action="{{ route('loan.payments.merge') }}" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, loan, client..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Channel</label>
                    <input type="text" name="channel" value="{{ $channel ?? '' }}" placeholder="e.g. mpesa" class="h-10 w-40 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.payments.merge') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <a href="{{ route('loan.payments.merge', array_merge(request()->except('export'), ['export' => 'csv'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.payments.merge', array_merge(request()->except('export'), ['export' => 'xls'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.payments.merge', array_merge(request()->except('export'), ['export' => 'pdf'])) }}" data-turbo="false" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

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
                                        <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ optional($c->transaction_at)->format('Y-m-d H:i') ?? '—' }}</td>
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
            @if ($candidates->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $candidates->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
