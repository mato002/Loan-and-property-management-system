<x-loan-layout>
    <x-loan.page
        :title="'Payment '.$payment->reference"
        subtitle="Payment details and posting trace."
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('loan.payments.processed') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Back to processed payments
            </a>
            <div class="flex items-center gap-2">
                @if ($payment->loan)
                    <a href="{{ route('loan.book.loans.show', $payment->loan) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Open loan
                    </a>
                @endif
                @if ($payment->accounting_journal_entry_id)
                    <a href="{{ route('loan.accounting.journal.show', $payment->accounting_journal_entry_id) }}" class="inline-flex items-center rounded-lg bg-[#2f4f4f] px-3 py-2 text-sm font-semibold text-white hover:bg-[#264040]">
                        Open journal
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Payment summary</h3>
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Reference</dt>
                        <dd class="font-mono text-slate-800">{{ $payment->reference ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Amount</dt>
                        <dd class="font-semibold text-slate-900">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Status</dt>
                        <dd class="text-slate-700">{{ ucfirst((string) $payment->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Kind</dt>
                        <dd class="text-slate-700">{{ ucfirst(str_replace('_', ' ', (string) $payment->payment_kind)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Transaction time</dt>
                        <dd class="text-slate-700">{{ optional($payment->transaction_at)->format('d M Y, h:i a') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Posted time</dt>
                        <dd class="text-slate-700">{{ optional($payment->posted_at)->format('d M Y, h:i a') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Channel</dt>
                        <dd class="text-slate-700">{{ $payment->channel ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">M-Pesa receipt</dt>
                        <dd class="font-mono text-slate-700">{{ $payment->mpesa_receipt_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Payer phone</dt>
                        <dd class="font-mono text-slate-700">{{ $payment->payer_msisdn ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Loan</dt>
                        <dd class="text-slate-700">{{ $payment->loan->loan_number ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Client</dt>
                        <dd class="text-slate-700">{{ $payment->loan?->loanClient?->full_name ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Message</dt>
                        <dd class="whitespace-pre-wrap text-slate-700">{{ $payment->message ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">Notes</dt>
                        <dd class="whitespace-pre-wrap text-slate-700">{{ $payment->notes ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-slate-800">Audit trail</h3>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Created by</dt>
                        <dd class="text-slate-700">{{ $payment->createdByUser?->name ?? 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Posted by</dt>
                        <dd class="text-slate-700">{{ $payment->postedByUser?->name ?? 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Validated by</dt>
                        <dd class="text-slate-700">{{ $payment->validatedByUser?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">Journal entry</dt>
                        <dd class="text-slate-700">{{ $payment->accounting_journal_entry_id ? '#'.$payment->accounting_journal_entry_id : '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
