<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back</a>
            <a href="{{ route('loan.book.applications.edit', $application) }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Edit application</a>
        </x-slot>

        @php
            $stage = (string) $application->stage;
            $hasLoan = (bool) $application->loan;
            $canBookLoan = in_array($stage, [\App\Models\LoanBookApplication::STAGE_APPROVED, \App\Models\LoanBookApplication::STAGE_DISBURSED], true) && ! $hasLoan;
        @endphp

        <div class="mb-4 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-800">Pipeline — what to do next</h2>
            <p class="mt-1 text-xs text-slate-600">Typical flow: <span class="font-medium text-slate-700">submitted</span> → <span class="font-medium text-slate-700">credit review</span> → <span class="font-medium text-slate-700">approved</span> → <span class="font-medium text-slate-700">book loan</span> → disbursement &amp; collections.</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                @if ($stage === \App\Models\LoanBookApplication::STAGE_SUBMITTED)
                    <p class="text-sm text-slate-700 flex-1"><span class="font-semibold text-slate-900">Credit review:</span> set the stage on the <a href="{{ route('loan.book.applications.index') }}" class="font-medium text-[#2f4f4f] hover:underline">Applications list</a> (dropdown + Update) or use <span class="font-medium">Edit application</span> for the full form.</p>
                    <a href="{{ route('loan.book.applications.edit', $application) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040]">Edit full form →</a>
                @elseif ($stage === \App\Models\LoanBookApplication::STAGE_CREDIT_REVIEW)
                    <p class="text-sm text-slate-700 flex-1"><span class="font-semibold text-slate-900">Decision:</span> move to <em>approved</em> or <em>declined</em> from the <a href="{{ route('loan.book.applications.index') }}" class="font-medium text-[#2f4f4f] hover:underline">Applications list</a> or use <span class="font-medium">Edit application</span> to adjust notes and other fields together.</p>
                    <a href="{{ route('loan.book.applications.edit', $application) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040]">Edit full form →</a>
                @elseif ($canBookLoan)
                    <p class="text-sm text-slate-700 flex-1"><span class="font-semibold text-slate-900">Book the facility:</span> this application is ready for a loan account. Open the booking form — client, amount, and product will be filled from this file.</p>
                    <a href="{{ route('loan.book.loans.create', ['application' => $application->id]) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Book loan from this application</a>
                @elseif ($hasLoan)
                    <p class="text-sm text-slate-700 flex-1"><span class="font-semibold text-slate-900">Loan booked.</span> Open the loan to post disbursements, record repayments, or update status.</p>
                    <a href="{{ route('loan.book.loans.show', $application->loan) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#264040]">Open loan</a>
                    <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Record disbursement</a>
                @elseif ($stage === \App\Models\LoanBookApplication::STAGE_DECLINED)
                    <p class="text-sm text-slate-700 flex-1"><span class="font-semibold text-slate-900">Declined.</span> No loan should be booked from this file unless your policy allows reopening — use Edit if the decision changes.</p>
                    <a href="{{ route('loan.book.applications.edit', $application) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Edit application</a>
                @else
                    <p class="text-sm text-slate-700 flex-1">Update the stage from the <a href="{{ route('loan.book.applications.index') }}" class="font-medium text-[#2f4f4f] hover:underline">Applications list</a> or use <span class="font-medium">Edit application</span> for other fields; open the linked loan if one exists.</p>
                    <a href="{{ route('loan.book.applications.edit', $application) }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Edit application</a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Application snapshot</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-slate-500">Reference</dt><dd class="font-medium text-slate-900">{{ $application->reference }}</dd></div>
                    <div><dt class="text-slate-500">Stage</dt><dd class="font-medium text-slate-900">{{ str_replace('_', ' ', $application->stage) }}</dd></div>
                    <div><dt class="text-slate-500">Client</dt><dd class="font-medium text-slate-900">{{ $application->loanClient->full_name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Client #</dt><dd class="font-medium text-slate-900">{{ $application->loanClient->client_number ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Product</dt><dd class="font-medium text-slate-900">{{ $application->product_name }}</dd></div>
                    <div><dt class="text-slate-500">Term</dt><dd class="font-medium text-slate-900">{{ $application->term_value ?? $application->term_months }} {{ $application->term_unit ?? 'monthly' }}</dd></div>
                    <div><dt class="text-slate-500">Term (months eq.)</dt><dd class="font-medium text-slate-900">{{ $application->term_months }}</dd></div>
                    <div><dt class="text-slate-500">Amount requested</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $application->amount_requested, 2) }}</dd></div>
                    <div><dt class="text-slate-500">Interest</dt><dd class="font-medium text-slate-900">{{ $application->interest_rate !== null ? number_format((float) $application->interest_rate, 4).' % per '.($application->interest_rate_period ?? 'annual') : '—' }}</dd></div>
                    <div><dt class="text-slate-500">Branch</dt><dd class="font-medium text-slate-900">{{ $application->branch ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Submission source</dt><dd class="font-medium text-slate-900">{{ $application->submission_source ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Submitted at</dt><dd class="font-medium text-slate-900">{{ optional($application->submitted_at)->format('Y-m-d H:i') ?: '—' }}</dd></div>
                </dl>
                <div class="mt-4 grid grid-cols-1 gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</p>
                        <p class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $application->purpose ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal notes</p>
                        <p class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $application->notes ?: '—' }}</p>
                    </div>
                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Loan department form</h3>
                            <p class="mt-1 text-xs text-slate-500">Applicant identity and contact details from the client record.</p>
                        </div>
                        <a href="{{ route('loan.clients.show', $application->loanClient) }}" class="shrink-0 text-xs font-semibold text-[#2f4f4f] hover:underline">Open client profile</a>
                    </div>
                    <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-900">{{ $application->loanClient->full_name ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Tel no.</dt><dd class="font-medium text-slate-900">{{ $application->loanClient->phone ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">ID no.</dt><dd class="font-medium text-slate-900">{{ $application->loanClient->id_number ?? '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Home address</dt><dd class="font-medium text-slate-900 whitespace-pre-line">{{ $application->loanClient->address ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Home / business PIN location code</dt><dd class="font-medium text-slate-900">{{ $application->applicant_pin_location_code ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Applicant sign</dt><dd class="font-medium text-slate-900">{{ $application->applicant_signature_name ?: '—' }}</dd></div>
                    </dl>
                    <h4 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-600">Guarantor details</h4>
                    <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 text-sm">
                        <div><dt class="text-slate-500">Name</dt><dd class="font-medium text-slate-900">{{ $application->guarantor_full_name ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">ID no.</dt><dd class="font-medium text-slate-900">{{ $application->guarantor_id_number ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Tel no.</dt><dd class="font-medium text-slate-900">{{ $application->guarantor_phone ?: '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">Signature</dt><dd class="font-medium text-slate-900">{{ $application->guarantor_signature_name ?: '—' }}</dd></div>
                    </dl>
                    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 leading-relaxed">
                        <p class="font-semibold text-slate-800 mb-1">Repayment agreement</p>
                        <p>I hereby agree to surrender all my properties to {{ config('app.name') }} or Auctioneers to auction my property if I fail or I don't pay the loan as agreed in the conditions.</p>
                        <p class="mt-2 text-slate-600">Acknowledged: <span class="font-medium text-slate-900">{{ $application->repayment_agreement_accepted ? 'Yes' : 'No' }}</span></p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-700">Linked loan</h2>
                @if ($application->loan)
                    <p class="mt-3 text-sm text-slate-700">This application has been booked into a loan account.</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Loan #</dt><dd class="font-medium text-slate-900">{{ $application->loan->loan_number }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Status</dt><dd class="font-medium text-slate-900">{{ str_replace('_', ' ', $application->loan->status) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Balance</dt><dd class="font-medium text-slate-900 tabular-nums">{{ number_format((float) $application->loan->balance, 2) }}</dd></div>
                    </dl>
                    <a href="{{ route('loan.book.loans.show', $application->loan) }}" class="mt-4 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open loan</a>
                @else
                    <p class="mt-3 text-sm text-slate-600">No loan account has been created from this application yet.</p>
                    @if ($canBookLoan)
                        <a href="{{ route('loan.book.loans.create', ['application' => $application->id]) }}" class="mt-4 inline-flex items-center rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">Book loan from this application</a>
                    @else
                        <a href="{{ route('loan.book.loans.create') }}" class="mt-4 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Book loan (manual)</a>
                    @endif
                @endif
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-700">Quick links</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('loan.book.applications.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">All applications</a>
                <a href="{{ route('loan.book.applications.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">New application</a>
                <a href="{{ route('loan.book.loans.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Book loan</a>
                <a href="{{ route('loan.book.loans.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View loans</a>
                <a href="{{ route('loan.book.disbursements.create') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Record disbursement</a>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
