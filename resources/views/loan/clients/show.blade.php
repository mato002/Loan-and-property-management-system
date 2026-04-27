<x-loan-layout>
    @php
        $loanHistory = $loan_client->loanBookLoans ?? collect();
        $metrics = $dashboardMetrics ?? [];
        $activeLoans = $loanHistory->whereIn('status', [
            \App\Models\LoanBookLoan::STATUS_ACTIVE,
            \App\Models\LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
            \App\Models\LoanBookLoan::STATUS_RESTRUCTURED,
        ]);
        $totalOutstanding = (float) ($metrics['total_outstanding'] ?? $activeLoans->sum(fn ($loan) => (float) ($loan->balance ?? 0)));
        $totalArrears = (float) ($metrics['total_arrears_days'] ?? $activeLoans->sum(fn ($loan) => (float) ($loan->dpd ?? 0)));
        $totalPrincipal = (float) ($metrics['total_principal'] ?? $loanHistory->sum(fn ($loan) => (float) ($loan->principal ?? 0)));
        $totalRepaid = (float) ($metrics['total_repaid'] ?? $loanHistory->sum(fn ($loan) => (float) ($loan->processed_repayments_sum_amount ?? 0)));
        $completionNumerator = (int) ($metrics['completion_numerator'] ?? $loanHistory->sum(fn ($loan) => min((int) ($loan->term_value ?? 0), (int) (($loan->processedRepayments ?? collect())->count()))));
        $completionDenominator = (int) ($metrics['completion_denominator'] ?? max(1, $loanHistory->sum(fn ($loan) => (int) ($loan->term_value ?? 0))));
        $completionPercent = (int) ($metrics['completion_percent'] ?? min(100, (int) round(($completionNumerator / max(1, $completionDenominator)) * 100)));
        $loanCycles = (int) ($metrics['loan_cycles'] ?? $loanHistory->count());
        $ltv = (float) ($metrics['lifetime_value'] ?? $totalRepaid);
        $creditScore = (int) ($metrics['credit_score'] ?? 780);
        $walletBalance = (float) ($metrics['wallet_balance'] ?? 0);
        $riskLabel = $creditScore >= 740 ? 'Low Risk' : ($creditScore >= 660 ? 'Medium Risk' : 'High Risk');
        $riskClass = $creditScore >= 740
            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
            : ($creditScore >= 660 ? 'bg-amber-100 text-amber-800 border-amber-200' : 'bg-rose-100 text-rose-800 border-rose-200');
        $riskStroke = $creditScore >= 740 ? '#0f766e' : ($creditScore >= 660 ? '#d97706' : '#dc2626');
        $scoreOffset = 239 - (($creditScore / 850) * 239);

        $mainLinks = [
            ['label' => 'Dashboard', 'route' => 'loan.dashboard'],
            ['label' => 'Clients', 'route' => 'loan.clients.index'],
            ['label' => 'Applications', 'route' => 'loan.book.applications.index'],
            ['label' => 'Loans', 'route' => 'loan.book.loans.index'],
            ['label' => 'Disbursements', 'route' => 'loan.book.disbursements.index'],
            ['label' => 'Payments', 'route' => 'loan.payments.processed'],
            ['label' => 'Pay-in report', 'route' => 'loan.payments.report'],
            ['label' => 'Unposted', 'route' => 'loan.payments.unposted'],
            ['label' => 'Accounting', 'route' => 'loan.accounting.books'],
        ];

        $documents = collect([
            ['type' => 'Client ID Front', 'path' => $loan_client->id_front_photo_path, 'date' => optional($loan_client->updated_at)->format('M j, Y'), 'uploader' => 'System'],
            ['type' => 'Client ID Back', 'path' => $loan_client->id_back_photo_path, 'date' => optional($loan_client->updated_at)->format('M j, Y'), 'uploader' => 'System'],
            ['type' => 'Client Photo', 'path' => $loan_client->client_photo_path, 'date' => optional($loan_client->updated_at)->format('M j, Y'), 'uploader' => 'System'],
        ])->filter(fn ($item) => filled($item['path']))->values();

        $guarantors = collect([
            [
                'name' => $loan_client->guarantor_1_full_name,
                'id_number' => $loan_client->guarantor_1_id_number,
                'phone' => $loan_client->guarantor_1_phone,
                'relationship' => $loan_client->guarantor_1_relationship,
                'status' => filled($loan_client->guarantor_1_full_name) ? 'Linked' : 'Not Added',
                'loan' => optional($loanHistory->first())->loan_number,
            ],
            [
                'name' => $loan_client->guarantor_2_full_name,
                'id_number' => $loan_client->guarantor_2_id_number,
                'phone' => $loan_client->guarantor_2_phone,
                'relationship' => $loan_client->guarantor_2_relationship,
                'status' => filled($loan_client->guarantor_2_full_name) ? 'Linked' : 'Not Added',
                'loan' => optional($loanHistory->skip(1)->first())->loan_number,
            ],
        ])->filter(fn ($item) => filled($item['name']))->values();
    @endphp

    <section class="mx-auto w-full max-w-[1600px] space-y-5 text-slate-800" x-data="{ activeTab: 'loan-history' }">
            <header class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="truncate text-xl font-semibold text-slate-900 md:text-2xl">{{ $loan_client->full_name }}</h1>
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $riskClass }}">
                                {{ $riskLabel }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500">Client ID: {{ $loan_client->client_number }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('loan.clients.interactions.for_client.create', $loan_client) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Notes
                        </a>
                        <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center rounded-lg border border-teal-700 bg-teal-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-teal-800">
                            Edit Info
                        </a>
                        <a href="{{ $loan_client->kind === 'lead' ? route('loan.clients.leads') : route('loan.clients.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            Back
                        </a>
                    </div>
                </div>
            </header>

            <nav class="overflow-x-auto rounded-2xl border border-slate-200 bg-white px-2 py-2 shadow-sm">
                <ul class="flex min-w-max items-center gap-1.5">
                    @foreach ($mainLinks as $item)
                        @if (\Illuminate\Support\Facades\Route::has($item['route']))
                            @php
                                $isActive = request()->routeIs($item['route']) || ($item['route'] === 'loan.clients.index' && request()->routeIs('loan.clients.show'));
                            @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" class="inline-flex whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            <section class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Credit Score</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $creditScore }}</p>
                            <p class="mt-1 text-xs font-medium text-emerald-700">{{ $riskLabel }}</p>
                            <p class="mt-1 text-xs text-slate-500">Based on active portfolio behavior.</p>
                            <a href="{{ route('loan.book.loan_arrears', ['q' => $loan_client->client_number]) }}" class="mt-2 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-800">View details</a>
                        </div>
                        <svg class="h-16 w-16 shrink-0" viewBox="0 0 100 100" role="img" aria-label="Credit score meter">
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#e2e8f0" stroke-width="8"></circle>
                            <circle cx="50" cy="50" r="38" fill="none" stroke="{{ $riskStroke }}" stroke-width="8" stroke-linecap="round" stroke-dasharray="239" stroke-dashoffset="{{ $scoreOffset }}" transform="rotate(-90 50 50)"></circle>
                        </svg>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client Wallet</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">KSh {{ number_format($walletBalance, 2) }}</p>
                            <a href="{{ route('loan.payments.processed', ['q' => $loan_client->client_number, 'channel' => 'wallet']) }}" class="mt-2 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-800">View Wallet</a>
                        </div>
                        <svg class="h-10 w-10 text-teal-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <rect x="2" y="5" width="20" height="14" rx="3" stroke="currentColor" stroke-width="1.5"></rect>
                            <path d="M16 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                            <circle cx="16" cy="12" r="1" fill="currentColor"></circle>
                        </svg>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Outstanding</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">KSh {{ number_format($totalOutstanding, 2) }}</p>
                    <p class="mt-1 text-xs font-medium {{ $totalArrears > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Arrears: {{ number_format($totalArrears, 0) }}</p>
                    <div class="mt-3">
                        <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                            <span>Installments</span>
                            <span class="font-semibold text-slate-700">{{ $completionNumerator }} / {{ $completionDenominator }} completed</span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div class="h-full rounded-full bg-teal-600" style="width: {{ $completionPercent }}%"></div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lifetime Value</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">KSh {{ number_format($ltv, 2) }}</p>
                    <dl class="mt-2 space-y-1 text-xs text-slate-600">
                        <div class="flex items-center justify-between"><dt>Total Disbursed</dt><dd class="font-semibold text-slate-800">KSh {{ number_format($totalPrincipal, 2) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Loan Cycles</dt><dd class="font-semibold text-slate-800">{{ number_format($loanCycles) }}</dd></div>
                    </dl>
                </article>

                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client Photo</p>
                    @if ($loan_client->client_photo_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($loan_client->client_photo_path) }}" alt="{{ $loan_client->full_name }}" class="mt-2 h-24 w-full rounded-xl object-cover" />
                    @else
                        <div class="mt-2 flex h-24 w-full items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50">
                            <svg class="h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"></circle>
                                <path d="M4 20c1.8-3.6 4.6-5 8-5s6.2 1.4 8 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                            </svg>
                        </div>
                    @endif
                    <a href="{{ route('loan.clients.edit', $loan_client) }}" class="mt-2 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-800">View / Change</a>
                </article>
            </section>

            <section class="grid grid-cols-1 gap-5 xl:grid-cols-12">
                <aside class="space-y-4 xl:col-span-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h2 class="text-sm font-semibold text-slate-900">Client Details</h2>
                        <div class="mt-4 space-y-4">
                            <section>
                                <h3 class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"></circle><path d="M4 20c1.8-3.6 4.6-5 8-5s6.2 1.4 8 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                                    Personal
                                </h3>
                                <dl class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">ID No</dt><dd class="font-medium text-slate-800">{{ $loan_client->id_number ?? '—' }}</dd></div>
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Branch</dt><dd class="font-medium text-slate-800">{{ $loan_client->branch ?? '—' }}</dd></div>
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Created</dt><dd class="font-medium text-slate-800">{{ optional($loan_client->created_at)->format('M j, Y') ?? '—' }}</dd></div>
                                    <div class="flex items-center justify-between gap-2"><dt class="text-slate-500">Cycles</dt><dd class="font-medium text-slate-800">{{ $loanHistory->count() }}</dd></div>
                                </dl>
                            </section>
                            <section>
                                <h3 class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                                    Financial
                                </h3>
                                <dl class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Outstanding</dt><dd class="font-semibold text-slate-900">KSh {{ number_format($totalOutstanding, 2) }}</dd></div>
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Arrears</dt><dd class="font-medium {{ $totalArrears > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format($totalArrears, 0) }}</dd></div>
                                    <div class="flex items-center justify-between gap-2"><dt class="text-slate-500">Loan Officer</dt><dd class="font-medium text-slate-800">{{ $loan_client->assignedEmployee?->full_name ?? '—' }}</dd></div>
                                </dl>
                            </section>
                            <section>
                                <h3 class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 10l5-5 5 5M7 14l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                    Contact & Kin
                                </h3>
                                <dl class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Client phone</dt><dd><x-phone-link :value="$loan_client->phone" /></dd></div>
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1"><dt class="text-slate-500">Kin contact</dt><dd><x-phone-link :value="$loan_client->next_of_kin_contact" /></dd></div>
                                    <div class="flex items-center justify-between gap-2"><dt class="text-slate-500">Next of kin</dt><dd class="font-medium text-slate-800">{{ $loan_client->next_of_kin_name ?? '—' }}</dd></div>
                                </dl>
                            </section>
                        </div>
                    </article>
                </aside>

                <section class="xl:col-span-9">
                    <article class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 p-3">
                            <div class="overflow-x-auto">
                                <div class="inline-flex min-w-max items-center gap-2 rounded-xl bg-slate-100 p-1">
                                    <button type="button" @click="activeTab='loan-history'" :class="activeTab==='loan-history' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-medium">Loan History</button>
                                    <button type="button" @click="activeTab='notes'" :class="activeTab==='notes' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-medium">Notes / Interactions</button>
                                    <button type="button" @click="activeTab='documents'" :class="activeTab==='documents' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-medium">Documents</button>
                                    <button type="button" @click="activeTab='guarantors'" :class="activeTab==='guarantors' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-medium">Guarantors</button>
                                    <button type="button" @click="activeTab='payments'" :class="activeTab==='payments' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'" class="rounded-lg px-3 py-2 text-sm font-medium">Payments</button>
                                </div>
                            </div>
                        </div>

                        <div class="p-4">
                            <section x-show="activeTab==='loan-history'" x-cloak>
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Loan History</h3>
                                    <a href="{{ route('loan.book.loans.index', ['q' => $loan_client->client_number]) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Export</a>
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-300">
                                    <table class="min-w-[980px] w-full text-sm">
                                        <thead class="sticky top-0 bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                                            <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                                <th class="px-3 py-2 text-left">Date</th>
                                                <th class="px-3 py-2 text-left">Loan</th>
                                                <th class="px-3 py-2 text-left">Product</th>
                                                <th class="px-3 py-2 text-left">Loan Officer</th>
                                                <th class="px-3 py-2 text-right">Principal</th>
                                                <th class="px-3 py-2 text-right">Arrears</th>
                                                <th class="px-3 py-2 text-right">Outstanding</th>
                                                <th class="px-3 py-2 text-left">Installments</th>
                                                <th class="px-3 py-2 text-left">Status</th>
                                                <th class="px-3 py-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white text-slate-700">
                                            @forelse ($loanHistory as $loan)
                                                @php
                                                    $termValue = max(1, (int) ($loan->term_value ?? 1));
                                                    $paidInstallments = min($termValue, (int) (($loan->processedRepayments ?? collect())->count()));
                                                    $installmentPercent = (int) round(($paidInstallments / $termValue) * 100);
                                                    $statusText = ucfirst(str_replace('_', ' ', (string) $loan->status));
                                                    $statusTone = str_contains(strtolower($statusText), 'active')
                                                        ? 'bg-teal-100 text-teal-700 border-teal-200'
                                                        : (str_contains(strtolower($statusText), 'closed') ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200');
                                                @endphp
                                                <tr class="[&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0 hover:bg-slate-50/70">
                                                    <td class="px-3 py-2">{{ optional($loan->disbursed_at)->format('M j, Y') ?? '—' }}</td>
                                                    <td class="px-3 py-2 font-mono text-xs text-slate-700">{{ $loan->loan_number ?: '—' }}</td>
                                                    <td class="px-3 py-2">{{ $loan->product_name ?: '—' }}</td>
                                                    <td class="px-3 py-2">{{ $loan_client->assignedEmployee?->full_name ?? '—' }}</td>
                                                    <td class="px-3 py-2 text-right font-medium">KSh {{ number_format((float) ($loan->principal ?? 0), 2) }}</td>
                                                    <td class="px-3 py-2 text-right {{ (float) ($loan->dpd ?? 0) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">{{ number_format((float) ($loan->dpd ?? 0), 0) }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">KSh {{ number_format((float) ($loan->balance ?? 0), 2) }}</td>
                                                    <td class="px-3 py-2">
                                                        <p class="text-xs font-semibold text-slate-700">{{ $paidInstallments }} / {{ $termValue }}</p>
                                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                            <div class="h-full rounded-full bg-teal-600" style="width: {{ $installmentPercent }}%"></div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2"><span class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold {{ $statusTone }}">{{ $statusText }}</span></td>
                                                    <td class="px-3 py-2">
                                                        <a href="{{ route('loan.book.loans.show', $loan) }}" class="inline-flex items-center text-xs font-semibold text-teal-700 hover:text-teal-800">View</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="px-4 py-12 text-center text-sm text-slate-500">
                                                        <svg class="mx-auto mb-2 h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 10h10M9 14h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                                                        No loan history found for this client.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section x-show="activeTab==='notes'" x-cloak>
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Notes / Interactions</h3>
                                    <a href="{{ route('loan.clients.interactions.for_client.create', $loan_client) }}" class="inline-flex items-center rounded-lg border border-teal-700 bg-teal-700 px-3 py-2 text-xs font-semibold text-white hover:bg-teal-800">Add Note</a>
                                </div>
                                <div class="rounded-xl border border-slate-300 bg-white">
                                    @forelse (($loan_client->interactions ?? collect()) as $interaction)
                                        <article class="flex items-start gap-3 border-b border-slate-200 px-4 py-3 last:border-b-0">
                                            <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-600">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 8h12M6 12h8M6 16h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path></svg>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <h4 class="text-sm font-semibold text-slate-800">{{ ucfirst(str_replace('_', ' ', (string) ($interaction->interaction_type ?? 'note'))) }}</h4>
                                                    <p class="text-xs text-slate-500">{{ optional($interaction->interacted_at)->format('M j, Y g:i A') ?? optional($interaction->created_at)->format('M j, Y g:i A') }}</p>
                                                </div>
                                                <p class="mt-1 text-sm text-slate-600">{{ $interaction->notes ?: 'No additional note.' }}</p>
                                                <p class="mt-1 text-xs text-slate-500">By {{ $interaction->user?->name ?? 'System' }}</p>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="px-4 py-10 text-center text-sm text-slate-500">No interactions logged yet.</div>
                                    @endforelse
                                </div>
                            </section>

                            <section x-show="activeTab==='documents'" x-cloak>
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Documents</h3>
                                    <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add Document</a>
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-300">
                                    <table class="min-w-[740px] w-full text-sm">
                                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                                            <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                                <th class="px-3 py-2 text-left">Document Type</th>
                                                <th class="px-3 py-2 text-left">Upload Date</th>
                                                <th class="px-3 py-2 text-left">Uploader</th>
                                                <th class="px-3 py-2 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white text-slate-700">
                                            @forelse ($documents as $document)
                                                <tr class="[&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0">
                                                    <td class="px-3 py-2">{{ $document['type'] }}</td>
                                                    <td class="px-3 py-2">{{ $document['date'] ?? '—' }}</td>
                                                    <td class="px-3 py-2">{{ $document['uploader'] }}</td>
                                                    <td class="px-3 py-2">
                                                        <a href="{{ \Illuminate\Support\Facades\Storage::url($document['path']) }}" target="_blank" class="inline-flex text-xs font-semibold text-teal-700 hover:text-teal-800">View</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No documents uploaded.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section x-show="activeTab==='guarantors'" x-cloak>
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Guarantors</h3>
                                    <a href="{{ route('loan.clients.edit', $loan_client) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Add Guarantor</a>
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-300">
                                    <table class="min-w-[820px] w-full text-sm">
                                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                                            <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                                <th class="px-3 py-2 text-left">Guarantor Name</th>
                                                <th class="px-3 py-2 text-left">ID Number</th>
                                                <th class="px-3 py-2 text-left">Phone</th>
                                                <th class="px-3 py-2 text-left">Relationship</th>
                                                <th class="px-3 py-2 text-left">Status</th>
                                                <th class="px-3 py-2 text-left">Linked Loan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white text-slate-700">
                                            @forelse ($guarantors as $guarantor)
                                                <tr class="[&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0">
                                                    <td class="px-3 py-2">{{ $guarantor['name'] }}</td>
                                                    <td class="px-3 py-2">{{ $guarantor['id_number'] ?: '—' }}</td>
                                                    <td class="px-3 py-2">@if ($guarantor['phone'])<a href="tel:{{ preg_replace('/\s+/', '', $guarantor['phone']) }}" class="font-medium text-teal-700 hover:text-teal-800">{{ $guarantor['phone'] }}</a>@else — @endif</td>
                                                    <td class="px-3 py-2">{{ $guarantor['relationship'] ?: '—' }}</td>
                                                    <td class="px-3 py-2"><span class="inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">{{ $guarantor['status'] }}</span></td>
                                                    <td class="px-3 py-2">{{ $guarantor['loan'] ?: '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No guarantors linked.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section x-show="activeTab==='payments'" x-cloak>
                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-sm font-semibold text-slate-900">Payments</h3>
                                    <a href="{{ route('loan.payments.processed', ['q' => $loan_client->client_number]) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Export</a>
                                </div>
                                <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                                    <input type="text" value="{{ $loan_client->full_name }}" readonly class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-600" aria-label="Client filter" />
                                    <input type="text" placeholder="Search transaction..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                    <input type="text" placeholder="Filter by approval..." class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div class="overflow-x-auto rounded-xl border border-slate-300">
                                    <table class="min-w-[920px] w-full text-sm">
                                        <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                                            <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                                <th class="px-3 py-2 text-left">Transaction</th>
                                                <th class="px-3 py-2 text-right">Amount</th>
                                                <th class="px-3 py-2 text-left">Payment Details</th>
                                                <th class="px-3 py-2 text-left">Client</th>
                                                <th class="px-3 py-2 text-left">Approval</th>
                                                <th class="px-3 py-2 text-left">Time</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white text-slate-700">
                                            @forelse (($recentPayments ?? collect()) as $payment)
                                                <tr class="[&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0">
                                                    <td class="px-3 py-2 font-mono text-xs">{{ $payment->transaction_reference ?? ('TXN-' . $payment->id) }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold">KSh {{ number_format((float) ($payment->amount ?? 0), 2) }}</td>
                                                    <td class="px-3 py-2">{{ $payment->payment_method ?? $payment->payment_channel ?? 'Manual payment' }}</td>
                                                    <td class="px-3 py-2">{{ $loan_client->full_name }}</td>
                                                    <td class="px-3 py-2">
                                                        <span class="inline-flex rounded-full border px-2 py-1 text-xs font-semibold {{ strtolower((string) ($payment->status ?? 'processed')) === 'processed' ? 'border-emerald-200 bg-emerald-100 text-emerald-700' : 'border-amber-200 bg-amber-100 text-amber-700' }}">
                                                            {{ ucfirst((string) ($payment->status ?? 'processed')) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2">{{ optional($payment->transaction_at)->format('M j, Y g:i A') ?? optional($payment->created_at)->format('M j, Y g:i A') ?? '—' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No payment records found.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    </article>
                </section>
            </section>
    </section>
</x-loan-layout>
