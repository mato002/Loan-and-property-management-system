<x-loan-layout>
    <style>
        .loan-compact-table {
            table-layout: fixed;
            width: 100%;
        }

        .loan-compact-table th,
        .loan-compact-table td {
            padding: 0.45rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.15rem;
            vertical-align: top;
            word-break: break-word;
        }
    </style>

    <x-loan.page
        title="Processed payments"
        subtitle="Posted collections and other payment lines."
    >
        @include('loan.payments.partials.flash')

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                    <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                    <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Corporate</label>
                    <select name="corporate" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm min-w-44">
                        <option value="">All</option>
                        @foreach (($corporateOptions ?? collect()) as $option)
                            <option value="{{ $option }}" @selected(($corporate ?? '') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Pay Mode</label>
                    <select name="pay_mode" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm min-w-36">
                        <option value="">All</option>
                        @foreach (($payModeOptions ?? collect()) as $option)
                            <option value="{{ $option }}" @selected(($payMode ?? '') === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, loan, client, receipt..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Source</label>
                    <select name="source" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        <option value="sms_forwarder" @selected(($source ?? '') === 'sms_forwarder')>SMS Forwarder</option>
                        <option value="manual" @selected(($source ?? '') === 'manual')>Manual/Other</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 20, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 20) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.payments.processed') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.payments.processed', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                    <button type="button" onclick="window.print()" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Print</button>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-700">
                    Processed pays for {{ \Carbon\Carbon::parse($displayDate)->format('d-m-Y') }}
                </h2>
                <p class="text-xs text-slate-500">
                    {{ $payments->total() }} row(s) · (Ksh {{ number_format((float) ($totalAmount ?? 0), 2) }})
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="loan-compact-table min-w-full w-full text-xs">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3">Transaction</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Payment Details</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Approval</th>
                            <th class="px-5 py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($payments as $p)
                            @php
                                $processedRowUrl = $p->loan?->loanClient
                                    ? route('loan.clients.show', $p->loan->loanClient)
                                    : null;
                            @endphp
                            <tr
                                class="hover:bg-slate-50/80 {{ $processedRowUrl ? 'cursor-pointer' : '' }}"
                                @if ($processedRowUrl)
                                    role="link"
                                    tabindex="0"
                                    onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $processedRowUrl }}';"
                                    onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ $processedRowUrl }}'; }"
                                @endif
                            >
                                <td class="px-5 py-3 font-mono text-xs text-slate-700">{{ $p->reference }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-medium text-slate-900">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                <td class="px-5 py-3 text-slate-600">
                                    <ul class="list-disc pl-4 space-y-0.5">
                                        @php
                                            $journalLines = collect($p->accountingJournalEntry?->lines ?? [])->take(3);
                                        @endphp
                                        @forelse ($journalLines as $line)
                                            @php
                                                $lineAmount = (float) ($line->debit ?: $line->credit ?: 0);
                                            @endphp
                                            <li>
                                                {{ $line->account?->name ?? 'Ledger line' }} -
                                                {{ number_format($lineAmount, 2) }}
                                            </li>
                                        @empty
                                            <li>{{ ucfirst(str_replace('_', ' ', (string) $p->payment_kind)) }} - {{ number_format((float) $p->amount, 2) }}</li>
                                            @if ($p->mpesa_receipt_number)
                                                <li>Receipt - {{ $p->mpesa_receipt_number }}</li>
                                            @endif
                                        @endforelse
                                    </ul>
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    @if ($p->loan?->loanClient)
                                        <a href="{{ route('loan.clients.show', $p->loan->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $p->loan->loanClient->full_name }}
                                        </a>
                                        <span class="block text-[11px] text-slate-500">{{ $p->loan->loanClient->id_number ?? '—' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs">
                                    @if ($p->validatedByUser || $p->postedByUser)
                                        {{ $p->validatedByUser?->name ?? $p->postedByUser?->name ?? 'System' }}
                                    @else
                                        System
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600 text-xs whitespace-nowrap">
                                    <span>{{ optional($p->transaction_at)->format('h:i a') ?? '—' }}</span>
                                    @if ($p->payment_kind !== \App\Models\LoanBookPayment::KIND_C2B_REVERSAL)
                                        <a href="{{ route('loan.payments.reversal.create', ['from' => $p->id]) }}" class="mt-1 block text-blue-600 hover:underline">
                                            Reverse
                                        </a>
                                    @endif
                                    @if ($p->accounting_journal_entry_id)
                                        <a href="{{ route('loan.accounting.journal.show', $p->accounting_journal_entry_id) }}" class="mt-1 block text-indigo-600 hover:underline">
                                            Journal
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">No processed payments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="px-5 py-4 border-t border-slate-100">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
