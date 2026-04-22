<x-loan-layout>
    <x-loan.page
        title="{{ $loan_client->full_name }}"
        subtitle="{{ $loan_client->kind === 'lead' ? 'Lead' : 'Client' }} · {{ $loan_client->client_number }}"
    >
        <x-slot name="actions">
            <a href="{{ route('loan.clients.interactions.for_client.create', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Notes
            </a>
            <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Edit Info
            </a>
            <a href="{{ $loan_client->kind === 'lead' ? route('loan.clients.leads') : route('loan.clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Back
            </a>
        </x-slot>

        @if ($loan_client->kind === 'lead')
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This record is a <strong>lead</strong> (status: {{ $loan_client->lead_status ?? 'new' }}).
            </div>
        @endif

        @php
            $loanHistory = $loan_client->loanBookLoans ?? collect();
            $activeLoans = $loanHistory->whereIn('status', [
                \App\Models\LoanBookLoan::STATUS_ACTIVE,
                \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                \App\Models\LoanBookLoan::STATUS_RESTRUCTURED,
            ]);
            $totalOutstanding = (float) $activeLoans->sum(fn ($loan) => (float) ($loan->balance ?? 0));
            $totalRepaid = (float) $loanHistory->sum(fn ($loan) => (float) ($loan->processed_repayments_sum_amount ?? 0));
            $loyaltyPoints = (int) floor($totalRepaid / 1000);
            $photoItems = [
                ['label' => 'Client photo', 'path' => $loan_client->client_photo_path],
                ['label' => 'ID back photo', 'path' => $loan_client->id_back_photo_path],
                ['label' => 'ID front photo', 'path' => $loan_client->id_front_photo_path],
            ];
            $extraMeta = collect((array) ($loan_client->biodata_meta ?? []))
                ->filter(fn ($value) => filled($value));
        @endphp

        <div
            class="space-y-5"
            x-data="{
                detailsOpen: false,
                activeLoanDetails: null,
                repaymentOpen: false,
                activeRepayment: null,
                actionOpen: false,
                activeActionLoan: null,
                assignAgentOpen: false,
                assignLoanPayload: null,
                selectedCollectionAgentId: '',
                openLoanDetails(payload) {
                    this.activeLoanDetails = payload;
                    this.detailsOpen = true;
                },
                closeLoanDetails() {
                    this.detailsOpen = false;
                },
                openRepaymentBreakdown(payload) {
                    this.activeRepayment = payload;
                    this.repaymentOpen = true;
                },
                closeRepaymentBreakdown() {
                    this.repaymentOpen = false;
                },
                openLoanActions(payload) {
                    this.activeActionLoan = payload;
                    this.actionOpen = true;
                },
                closeLoanActions() {
                    this.actionOpen = false;
                },
                openAssignAgent(payload) {
                    this.assignLoanPayload = payload;
                    this.selectedCollectionAgentId = String(payload?.current_agent_id || '');
                    this.assignAgentOpen = true;
                },
                closeAssignAgent() {
                    this.assignAgentOpen = false;
                }
            }"
        >
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="rounded-lg bg-indigo-700 p-4 text-white">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-100">Loyalty points</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($loyaltyPoints) }}</p>
                    </div>
                    <div class="rounded-lg bg-blue-700 p-4 text-white">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-100">Acc balances (KSH)</p>
                        <p class="mt-2 text-2xl font-semibold tabular-nums">{{ number_format($totalOutstanding, 2) }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($photoItems as $item)
                            <div class="rounded border border-slate-200 bg-slate-50 p-1.5 text-center">
                                @if (filled($item['path']))
                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item['path']) }}" target="_blank">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($item['path']) }}" alt="{{ $item['label'] }}" class="h-16 w-full rounded object-cover" />
                                    </a>
                                @else
                                    <div class="flex h-16 items-center justify-center rounded bg-white text-[10px] text-slate-400">No photo</div>
                                @endif
                                <p class="mt-1 text-[10px] text-slate-600">{{ $item['label'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-4">
                <div class="xl:col-span-1 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-700">Client Details</h3>
                    </div>
                    <dl class="space-y-2 px-4 py-3 text-xs">
                        <div><dt class="text-slate-500">ID No</dt><dd class="font-medium text-slate-900">{{ $loan_client->id_number ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Branch</dt><dd class="font-medium text-slate-900">{{ $loan_client->branch ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Cycles</dt><dd class="font-medium text-slate-900">{{ $loanHistory->count() }}</dd></div>
                        <div><dt class="text-slate-500">Contact</dt><dd class="font-medium text-slate-900"><x-phone-link :value="$loan_client->phone" /></dd></div>
                        <div><dt class="text-slate-500">Created</dt><dd class="font-medium text-slate-900">{{ optional($loan_client->created_at)->format('M j, Y') ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Kin contact</dt><dd class="font-medium text-slate-900"><x-phone-link :value="$loan_client->next_of_kin_contact" /></dd></div>
                        <div><dt class="text-slate-500">Next of kin</dt><dd class="font-medium text-slate-900">{{ $loan_client->next_of_kin_name ?? '—' }}</dd></div>
                        <div><dt class="text-slate-500">Loan officer</dt><dd class="font-medium text-slate-900">{{ $loan_client->assignedEmployee?->full_name ?? '—' }}</dd></div>
                    </dl>
                </div>

                <div class="xl:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-700">Loan History</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Loan</th>
                                    <th class="px-3 py-2 text-left">Product</th>
                                    <th class="px-3 py-2 text-left">Coll Agent</th>
                                    <th class="px-3 py-2 text-right">Penalty</th>
                                    <th class="px-3 py-2 text-right">Arrears</th>
                                    <th class="px-3 py-2 text-right">Principal</th>
                                    <th class="px-3 py-2 text-right">Total Bal</th>
                                    <th class="px-3 py-2 text-right">Repayment</th>
                                    <th class="px-3 py-2 text-right">Points</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @forelse ($loanHistory as $loan)
                                    @php
                                        $statusText = ucfirst(str_replace('_', ' ', (string) $loan->status));
                                        $statusClass = str_contains(strtolower($statusText), 'active')
                                            ? 'text-amber-700'
                                            : (str_contains(strtolower($statusText), 'closed') ? 'text-emerald-700' : 'text-slate-700');
                                        $rowPoints = max(0, 50 - (int) ($loan->dpd ?? 0));
                                        $paymentsForSchedule = ($loan->processedRepayments ?? collect())
                                            ->sortBy('transaction_at')
                                            ->values();
                                        $termValue = max(1, (int) ($loan->term_value ?? 1));
                                        $principalTotal = (float) ($loan->principal ?? 0);
                                        $totalPaid = (float) ($loan->processed_repayments_sum_amount ?? 0);
                                        $currentBalance = max(0, (float) ($loan->balance ?? 0));
                                        $totalRepayable = max(0, $totalPaid + $currentBalance);
                                        $interestTotal = max(0, $totalRepayable - $principalTotal);
                                        $installmentAmount = $termValue > 0 ? ($totalRepayable / $termValue) : 0.0;
                                        $principalPerInstallment = $termValue > 0 ? ($principalTotal / $termValue) : 0.0;
                                        $interestPerInstallment = $termValue > 0 ? ($interestTotal / $termValue) : 0.0;
                                        $startDate = $loan->disbursed_at?->copy();
                                        $intervalUnit = strtolower((string) ($loan->term_unit ?? 'monthly'));
                                        $scheduleRows = [];
                                        $runningPaid = 0.0;
                                        for ($i = 0; $i < $termValue; $i++) {
                                            $payment = $paymentsForSchedule->get($i);
                                            $paidAmount = (float) ($payment?->amount ?? 0);
                                            $runningPaid += $paidAmount;
                                            $rowDate = $payment?->transaction_at?->format('d-m-Y');
                                            if (! $rowDate && $startDate) {
                                                $projected = $startDate->copy();
                                                if ($intervalUnit === 'daily') {
                                                    $projected->addDays($i + 1);
                                                } elseif ($intervalUnit === 'weekly') {
                                                    $projected->addWeeks($i + 1);
                                                } else {
                                                    $projected->addMonths($i + 1);
                                                }
                                                $rowDate = $projected->format('d-m-Y');
                                            }
                                            $scheduleRows[] = [
                                                'schedule' => $i + 1,
                                                'amount' => round($installmentAmount, 2),
                                                'principal' => round($principalPerInstallment, 2),
                                                'interest' => round($interestPerInstallment, 2),
                                                'paid' => round($paidAmount, 2),
                                                'date' => $rowDate ?: '—',
                                                'balance' => round(max(0, $totalRepayable - $runningPaid), 2),
                                            ];
                                        }
                                        $guarantorBusinessPhoto = (string) (data_get($loan_client->biodata_meta, 'guarantor_business_photo') ?? '');
                                        $businessPhoto = (string) (data_get($loan_client->biodata_meta, 'business_photo') ?? '');
                                        $loanFormFrontPhoto = (string) (data_get($loan_client->biodata_meta, 'loanform_front_photo') ?? '');
                                        $loanDetailsPayload = [
                                            'loan_number' => (string) ($loan->loan_number ?? '—'),
                                            'product_name' => (string) ($loan->product_name ?: '—'),
                                            'term' => trim(((string) ($loan->term_value ?? '')) . ' ' . ((string) ($loan->term_unit ?? ''))) ?: '—',
                                            'status' => $statusText ?: '—',
                                            'loan_amount' => (float) ($loan->principal ?? 0),
                                            'disbursement' => optional($loan->disbursed_at)->format('d-m-Y, h:i a') ?? '—',
                                            'posted_by' => (string) ($loan_client->assignedEmployee?->full_name ?? 'System'),
                                            'template_creation' => optional($loan->created_at)->format('d-m-Y, h:i a') ?? '—',
                                            'loan_maturity' => optional($loan->maturity_date)->format('d-m-Y, h:i a') ?? '—',
                                            'clearance' => (float) max(0, (float) ($loan->balance ?? 0)),
                                            'total_paid' => (float) ($loan->processed_repayments_sum_amount ?? 0),
                                            'loan_officer' => (string) ($loan_client->assignedEmployee?->full_name ?? '—'),
                                            'guarantor_name' => (string) ($loan_client->guarantor_1_full_name ?? '—'),
                                            'guarantor_contact' => (string) ($loan_client->guarantor_1_phone ?? '—'),
                                            'residential_type' => (string) ($loan_client->address ?? '—'),
                                            'business_name' => (string) (data_get($loan_client->biodata_meta, 'business_name') ?? '—'),
                                            'asset_list' => (string) ($loan->notes ?? '—'),
                                            'guarantor_business_photo_url' => $guarantorBusinessPhoto !== '' ? \Illuminate\Support\Facades\Storage::url($guarantorBusinessPhoto) : null,
                                            'business_photo_url' => $businessPhoto !== '' ? \Illuminate\Support\Facades\Storage::url($businessPhoto) : null,
                                            'loanform_front_photo_url' => $loanFormFrontPhoto !== '' ? \Illuminate\Support\Facades\Storage::url($loanFormFrontPhoto) : null,
                                        ];
                                        $repaymentPayload = [
                                            'loan_number' => (string) ($loan->loan_number ?? 'Loan'),
                                            'rows' => $scheduleRows,
                                            'totals' => [
                                                'amount' => round($installmentAmount * $termValue, 2),
                                                'principal' => round($principalPerInstallment * $termValue, 2),
                                                'interest' => round($interestPerInstallment * $termValue, 2),
                                                'paid' => round($totalPaid, 2),
                                                'balance' => round($currentBalance, 2),
                                            ],
                                        ];
                                        $loanActionPayload = [
                                            'loan_number' => (string) ($loan->loan_number ?? ''),
                                            'show_url' => route('loan.book.loans.show', $loan),
                                            'edit_url' => route('loan.book.loans.edit', $loan),
                                            'payments_url' => route('loan.payments.create', ['loan_book_loan_id' => $loan->id]),
                                            'disbursements_url' => route('loan.book.disbursements.index', ['q' => $loan->loan_number]),
                                            'tag_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['focus' => 'tags'])),
                                            'charges_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'charges'])),
                                            'reschedule_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'schedule'])),
                                            'offset_url' => route('loan.payments.create', ['loan_book_loan_id' => $loan->id, 'payment_kind' => 'overpayment']),
                                            'topup_url' => route('loan.book.applications.create', ['client' => $loan_client->id, 'prefill_loan_id' => $loan->id]),
                                            'rollover_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'rollover'])),
                                            'deactivate_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'status'])),
                                            'writeoff_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'writeoff'])),
                                            'delete_url' => route('loan.book.loans.edit', array_merge(['loan_book_loan' => $loan->id], ['tab' => 'delete'])),
                                        ];
                                        $assignAgentPayload = [
                                            'loan_number' => (string) ($loan->loan_number ?? 'Loan'),
                                            'assign_url' => route('loan.clients.loans.collection_agent.assign', [$loan_client, $loan]),
                                            'current_agent_id' => (int) ($loan->collection_agent_employee_id ?? 0),
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-3 py-2 whitespace-nowrap">{{ optional($loan->disbursed_at)->format('d-m-Y') ?? '—' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @if ($loan->loan_number)
                                                <a href="{{ route('loan.book.loans.show', $loan) }}" class="font-mono text-indigo-600 hover:underline">{{ $loan->loan_number }}</a>
                                                <div class="mt-0.5">
                                                    <button type="button" @click.prevent="openLoanDetails(@js($loanDetailsPayload))" class="text-[10px] font-semibold text-blue-600 hover:underline">Details</button>
                                                </div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-3 py-2">{{ $loan->product_name ?: '—' }}</td>
                                        <td class="px-3 py-2">
                                            @if ($loan->collectionAgent)
                                                <div class="font-medium text-slate-800">{{ $loan->collectionAgent->full_name }}</div>
                                                <button type="button" @click.prevent="openAssignAgent(@js($assignAgentPayload))" class="text-[10px] font-semibold text-blue-600 hover:underline">Reassign</button>
                                            @else
                                                <button type="button" @click.prevent="openAssignAgent(@js($assignAgentPayload))" class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 hover:bg-blue-100">Assign</button>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($loan->fees_outstanding ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($loan->dpd ?? 0), 0) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($loan->principal ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) ($loan->balance ?? 0), 2) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">
                                            <div>{{ number_format((float) ($loan->processed_repayments_sum_amount ?? 0), 2) }}</div>
                                            <div class="mt-0.5">
                                                <button type="button" @click.prevent="openRepaymentBreakdown(@js($repaymentPayload))" class="text-[10px] font-semibold text-blue-600 hover:underline">View</button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $rowPoints }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <span class="{{ $statusClass }}">{{ $statusText }}</span>
                                            @if ($loan->maturity_date)
                                                <div class="text-[10px] text-slate-400">{{ $loan->maturity_date->format('d-m-Y') }}</div>
                                            @endif
                                            <div class="mt-0.5 flex items-center gap-2">
                                                <a href="{{ route('loan.book.loans.show', $loan) }}" class="text-[10px] font-semibold text-blue-600 hover:underline">View</a>
                                                <button type="button" @click.prevent="openLoanActions(@js($loanActionPayload))" class="text-[10px] font-semibold text-blue-600 hover:underline">Action</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-3 py-8 text-center text-slate-500">No loan history found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($extraMeta->isNotEmpty())
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-600">Additional details</h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($extraMeta as $key => $value)
                            @php
                                $label = ucfirst(str_replace('_', ' ', (string) $key));
                                $stringValue = is_scalar($value) ? trim((string) $value) : '';
                            @endphp
                            <div class="text-xs">
                                <p class="text-slate-500">{{ $label }}</p>
                                <p class="font-medium text-slate-800">{{ $stringValue !== '' ? $stringValue : '—' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div
                x-show="detailsOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                @keydown.escape.window="closeLoanDetails()"
                @click.self="closeLoanDetails()"
            >
                <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">Loan Details</h3>
                            <p class="text-xs text-slate-500">Professional summary for this facility</p>
                        </div>
                        <button type="button" @click="closeLoanDetails()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
                    </div>
                    <div class="space-y-4 p-6 text-sm text-slate-700">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Loan Amount</p>
                                <p class="mt-1 font-semibold tabular-nums text-indigo-900" x-text="'KSH ' + Number(activeLoanDetails?.loan_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-600">Total Paid</p>
                                <p class="mt-1 font-semibold tabular-nums text-emerald-900" x-text="'KSH ' + Number(activeLoanDetails?.total_paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-600">Outstanding</p>
                                <p class="mt-1 font-semibold tabular-nums text-amber-900" x-text="'KSH ' + Number(activeLoanDetails?.clearance || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Status</p>
                                <p class="mt-1 font-semibold text-slate-800" x-text="activeLoanDetails?.status || '—'"></p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white">
                            <div class="border-b border-slate-100 px-4 py-2.5">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Facility Information</h4>
                            </div>
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 px-4 py-3 text-xs sm:grid-cols-2">
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Loan Number</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.loan_number || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Product</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.product_name || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Term</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.term || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Disbursement</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.disbursement || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Created</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.template_creation || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Maturity</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.loan_maturity || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Posted By</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.posted_by || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Loan Officer</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.loan_officer || '—'"></dd></div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white">
                            <div class="border-b border-slate-100 px-4 py-2.5">
                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guarantor & Business Information</h4>
                            </div>
                            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 px-4 py-3 text-xs sm:grid-cols-2">
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Guarantor Name</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.guarantor_name || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Guarantor Contact</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.guarantor_contact || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Residential Type</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.residential_type || '—'"></dd></div>
                                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-1"><dt class="font-semibold text-slate-500">Business Name</dt><dd class="text-right font-medium text-slate-800" x-text="activeLoanDetails?.business_name || '—'"></dd></div>
                                <div class="sm:col-span-2 rounded-md bg-slate-50 px-3 py-2">
                                    <dt class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Asset List</dt>
                                    <dd class="text-xs leading-5 text-slate-700 break-words" x-text="activeLoanDetails?.asset_list || '—'"></dd>
                                </div>
                            </dl>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Attached Photos</h4>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <template x-if="activeLoanDetails?.guarantor_business_photo_url">
                                <a :href="activeLoanDetails.guarantor_business_photo_url" target="_blank" class="block rounded border border-slate-200 bg-slate-50 p-1">
                                    <img :src="activeLoanDetails.guarantor_business_photo_url" alt="Guarantor business photo" class="h-20 w-full rounded object-cover" />
                                    <p class="mt-1 text-[10px] text-slate-600">Guarantor business photo</p>
                                </a>
                            </template>
                            <template x-if="activeLoanDetails?.business_photo_url">
                                <a :href="activeLoanDetails.business_photo_url" target="_blank" class="block rounded border border-slate-200 bg-slate-50 p-1">
                                    <img :src="activeLoanDetails.business_photo_url" alt="Business photo" class="h-20 w-full rounded object-cover" />
                                    <p class="mt-1 text-[10px] text-slate-600">Business photo</p>
                                </a>
                            </template>
                            <template x-if="activeLoanDetails?.loanform_front_photo_url">
                                <a :href="activeLoanDetails.loanform_front_photo_url" target="_blank" class="block rounded border border-slate-200 bg-slate-50 p-1">
                                    <img :src="activeLoanDetails.loanform_front_photo_url" alt="Loan form front photo" class="h-20 w-full rounded object-cover" />
                                    <p class="mt-1 text-[10px] text-slate-600">Loanform front photo</p>
                                </a>
                            </template>
                            <template x-if="!activeLoanDetails?.guarantor_business_photo_url && !activeLoanDetails?.business_photo_url && !activeLoanDetails?.loanform_front_photo_url">
                                <div class="sm:col-span-3 rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-xs text-slate-500">
                                    No attached photos for this loan.
                                </div>
                            </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-show="repaymentOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                @keydown.escape.window="closeRepaymentBreakdown()"
                @click.self="closeRepaymentBreakdown()"
            >
                <div class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">
                                Repayment Breakdown
                            </h3>
                            <p class="text-xs text-slate-500">
                                Loan: <span class="font-semibold text-slate-700" x-text="activeRepayment?.loan_number || '—'"></span>
                            </p>
                        </div>
                        <button type="button" @click="closeRepaymentBreakdown()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-indigo-600">Total Amount</p>
                                <p class="mt-1 text-xs font-semibold tabular-nums text-indigo-900" x-text="Number(activeRepayment?.totals?.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-sky-100 bg-sky-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-sky-600">Principal</p>
                                <p class="mt-1 text-xs font-semibold tabular-nums text-sky-900" x-text="Number(activeRepayment?.totals?.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-violet-100 bg-violet-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-600">Interest</p>
                                <p class="mt-1 text-xs font-semibold tabular-nums text-violet-900" x-text="Number(activeRepayment?.totals?.interest || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-600">Paid</p>
                                <p class="mt-1 text-xs font-semibold tabular-nums text-emerald-900" x-text="Number(activeRepayment?.totals?.paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-600">Balance</p>
                                <p class="mt-1 text-xs font-semibold tabular-nums text-amber-900" x-text="Number(activeRepayment?.totals?.balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full text-xs">
                                <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Schedule</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                        <th class="px-3 py-2 text-right">Principal</th>
                                        <th class="px-3 py-2 text-right">Interest</th>
                                        <th class="px-3 py-2 text-right">Paid</th>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-right">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    <template x-for="row in (activeRepayment?.rows || [])" :key="row.schedule">
                                        <tr class="hover:bg-slate-50/70">
                                            <td class="px-3 py-2" x-text="row.schedule"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="Number(row.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="Number(row.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="Number(row.interest || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="Number(row.paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                            <td class="px-3 py-2 whitespace-nowrap" x-text="row.date || '—'"></td>
                                            <td class="px-3 py-2 text-right tabular-nums" x-text="Number(row.balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        </tr>
                                    </template>
                                    <template x-if="(activeRepayment?.rows || []).length === 0">
                                        <tr>
                                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">No repayment rows available.</td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-slate-50 border-t border-slate-200 text-slate-800 font-semibold">
                                    <tr>
                                        <td class="px-3 py-2">Totals</td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Number(activeRepayment?.totals?.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Number(activeRepayment?.totals?.principal || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Number(activeRepayment?.totals?.interest || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Number(activeRepayment?.totals?.paid || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                        <td class="px-3 py-2">—</td>
                                        <td class="px-3 py-2 text-right tabular-nums" x-text="Number(activeRepayment?.totals?.balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-show="actionOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                @keydown.escape.window="closeLoanActions()"
                @click.self="closeLoanActions()"
            >
                <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl max-h-[92vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">Loan Action Options</h3>
                            <p class="text-xs text-slate-500">Choose the next action for this loan account</p>
                        </div>
                        <button type="button" @click="closeLoanActions()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            Loan: <span class="font-semibold text-slate-800" x-text="activeActionLoan?.loan_number || '—'"></span>
                        </div>
                        <div class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                            <a :href="activeActionLoan?.payments_url || '#'" class="inline-flex items-center justify-center rounded-md border border-sky-200 bg-sky-50 px-3 py-2 font-semibold text-sky-700 hover:bg-sky-100">Make Payment</a>
                            <a :href="activeActionLoan?.tag_url || '#'" class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 font-semibold text-indigo-700 hover:bg-indigo-100">Tag Loan</a>
                            <a :href="activeActionLoan?.edit_url || '#'" class="inline-flex items-center justify-center rounded-md border border-cyan-200 bg-cyan-50 px-3 py-2 font-semibold text-cyan-700 hover:bg-cyan-100">Edit Details</a>
                            <a :href="activeActionLoan?.charges_url || '#'" class="inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 py-2 font-semibold text-amber-700 hover:bg-amber-100">Add Charges</a>
                            <a :href="activeActionLoan?.edit_url || '#'" class="inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 font-semibold text-emerald-700 hover:bg-emerald-100">Waive Interest</a>
                            <a :href="activeActionLoan?.reschedule_url || '#'" class="inline-flex items-center justify-center rounded-md border border-violet-200 bg-violet-50 px-3 py-2 font-semibold text-violet-700 hover:bg-violet-100">Reschedule</a>
                            <a :href="activeActionLoan?.offset_url || '#'" class="inline-flex items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Offset Loan</a>
                            <a :href="activeActionLoan?.topup_url || '#'" class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-3 py-2 font-semibold text-blue-700 hover:bg-blue-100">Topup Loan</a>
                            <a :href="activeActionLoan?.show_url || '#'" class="inline-flex items-center justify-center rounded-md border border-purple-200 bg-purple-50 px-3 py-2 font-semibold text-purple-700 hover:bg-purple-100">Close Loan</a>
                            <a :href="activeActionLoan?.rollover_url || '#'" class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 font-semibold text-indigo-700 hover:bg-indigo-100">Roll-Over Loan</a>
                            <a :href="activeActionLoan?.deactivate_url || '#'" class="inline-flex items-center justify-center rounded-md border border-teal-200 bg-teal-50 px-3 py-2 font-semibold text-teal-700 hover:bg-teal-100">Deactivate Loan</a>
                            <a :href="activeActionLoan?.show_url || '#'" class="inline-flex items-center justify-center rounded-md border border-lime-200 bg-lime-50 px-3 py-2 font-semibold text-lime-700 hover:bg-lime-100">Loan Statement</a>
                            <a :href="activeActionLoan?.writeoff_url || '#'" class="inline-flex items-center justify-center rounded-md border border-fuchsia-200 bg-fuchsia-50 px-3 py-2 font-semibold text-fuchsia-700 hover:bg-fuchsia-100">Write-Off Loan</a>
                            <a :href="activeActionLoan?.delete_url || '#'" class="inline-flex items-center justify-center rounded-md border border-rose-200 bg-rose-50 px-3 py-2 font-semibold text-rose-700 hover:bg-rose-100">Delete Loan</a>
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-show="assignAgentOpen"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
                @keydown.escape.window="closeAssignAgent()"
                @click.self="closeAssignAgent()"
            >
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-slate-800">Assign Loan to Collection Agent</h3>
                        <button type="button" @click="closeAssignAgent()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">✕</button>
                    </div>
                    <form method="post" :action="assignLoanPayload?.assign_url || '#'" class="space-y-4 px-6 py-5">
                        @csrf
                        @method('patch')
                        <div>
                            <p class="mb-2 text-xs text-slate-500">Loan: <span class="font-semibold text-slate-700" x-text="assignLoanPayload?.loan_number || '—'"></span></p>
                            <label for="collection_agent_employee_id" class="mb-1 block text-xs font-semibold text-slate-600">Select Collection Agent</label>
                            <select id="collection_agent_employee_id" name="collection_agent_employee_id" x-model="selectedCollectionAgentId" class="w-full rounded-lg border-slate-200 text-sm" required>
                                <option value="">Choose agent</option>
                                @foreach (($collectionAgents ?? collect()) as $agent)
                                    <option value="{{ $agent->id }}">
                                        {{ $agent->full_name }}@if($agent->job_title) — {{ $agent->job_title }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Assign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
