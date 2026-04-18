<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            @if ($disbursement->loan)
                <a href="{{ route('loan.book.loans.show', $disbursement->loan) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Open loan</a>
            @endif
        </x-slot>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Disbursement snapshot</h2>
            <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                <div><dt class="text-slate-500">Reference</dt><dd class="font-medium text-slate-900">{{ $disbursement->reference }}</dd></div>
                <div><dt class="text-slate-500">Date</dt><dd class="font-medium text-slate-900">{{ optional($disbursement->disbursed_at)->format('Y-m-d') ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Method</dt><dd class="font-medium text-slate-900">{{ $disbursement->method }}</dd></div>
                <div><dt class="text-slate-500">Payout status</dt><dd class="font-medium text-slate-900">{{ ucfirst((string) ($disbursement->payout_status ?? 'completed')) }}</dd></div>
                <div><dt class="text-slate-500">Amount</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $disbursement->amount, 2) }}</dd></div>
                <div><dt class="text-slate-500">Loan #</dt><dd class="font-medium text-slate-900">{{ $disbursement->loan?->loan_number ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $disbursement->loan?->loanClient?->full_name ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">M-Pesa phone</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_phone ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Payout transaction</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_transaction_id ?: '—' }}</dd></div>
                <div><dt class="text-slate-500">Conversation ID</dt><dd class="font-medium text-slate-900">{{ $disbursement->payout_conversation_id ?: '—' }}</dd></div>
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-slate-500">Notes</dt><dd class="mt-1 rounded-lg bg-slate-50 p-3 text-slate-700">{{ $disbursement->notes ?: '—' }}</dd></div>
            </dl>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Accounting link</h2>
            @if ($disbursement->accounting_journal_entry_id)
                <p class="mt-2 text-sm text-slate-700">This disbursement was posted to journal entry #{{ $disbursement->accounting_journal_entry_id }}.</p>
                <a href="{{ route('loan.accounting.journal.show', $disbursement->accounting_journal_entry_id) }}" class="mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open journal entry</a>
            @else
                <p class="mt-2 text-sm text-slate-600">No journal entry is linked yet.</p>
                @if (($disbursement->method ?? '') === 'mpesa' && ($disbursement->payout_status ?? '') === 'failed' && ($b2cPayoutConfigured ?? false))
                    <form method="post" action="{{ route('loan.book.disbursements.retry_payout', $disbursement) }}" class="mt-3" data-swal-confirm="Retry this M-Pesa payout request?">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">Retry M-Pesa payout</button>
                    </form>
                @endif
            @endif
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Quick links</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All disbursements</a>
                <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">New disbursement</a>
                @if ($disbursement->loan)
                    <a href="{{ route('loan.book.loans.show', $disbursement->loan) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Loan profile</a>
                @endif
                <a href="{{ route('loan.book.collection_sheet.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Collection sheet</a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
