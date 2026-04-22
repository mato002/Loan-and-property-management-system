<x-loan-layout>
    <style>
        .loan-compact-table {
            table-layout: auto;
            width: 100%;
            min-width: 0;
        }

        .loan-compact-table th,
        .loan-compact-table td {
            padding: 0.4rem 0.4rem !important;
            font-size: 0.75rem !important;
            line-height: 1.15rem !important;
            vertical-align: top;
            word-break: normal;
            overflow-wrap: anywhere;
            hyphens: none;
            white-space: normal;
        }
    </style>

    <x-loan.page :title="$title" :subtitle="$subtitle">
        <div
            x-data="{
                columnMenuOpen: false,
                storageKey: 'loan.book.applications.index.columns.v1',
                defaultCols: {
                    application: true,
                    ref: true,
                    client: true,
                    clientNo: true,
                    product: true,
                    source: true,
                    term: true,
                    amount: true,
                    guarantor: true,
                    guarantorContact: false,
                    residential: false,
                    business: false,
                    asset: false,
                    runs: false,
                    guarantor2: false,
                    guarantor2Contact: false,
                    charges: true,
                    media: false,
                    deductions: false,
                    approvedBy: true,
                    stage: true,
                    branch: true,
                    submitted: true,
                    actions: true
                },
                cols: {},
                scheduleModalOpen: false,
                scheduleTitle: '',
                scheduleClient: '',
                schedulePrincipal: 0,
                scheduleRateLabel: '',
                scheduleFrequencyLabel: '',
                scheduleMethodLabel: '',
                scheduleRows: [],
                scheduleTotals: { principal: 0, interest: 0, installment: 0 },
                init() {
                    this.cols = { ...this.defaultCols };
                    try {
                        const saved = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
                        if (saved && typeof saved === 'object') {
                            Object.keys(this.defaultCols).forEach((k) => {
                                if (Object.prototype.hasOwnProperty.call(saved, k)) {
                                    this.cols[k] = !!saved[k];
                                }
                            });
                        }
                    } catch (e) {}

                    this.$watch('cols', (value) => {
                        localStorage.setItem(this.storageKey, JSON.stringify(value));
                    }, { deep: true });
                },
                visibleExportCols() {
                    return Object.keys(this.defaultCols).filter((k) => k !== 'actions' && !!this.cols[k]);
                },
                exportUrl(format) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('export', format);
                    url.searchParams.set('cols', this.visibleExportCols().join(','));
                    return `${url.pathname}?${url.searchParams.toString()}`;
                },
                openRow(url, event) {
                    const target = event?.target;
                    if (!url || (target && target.closest('a, button, input, select, textarea, form, label, summary, details'))) {
                        return;
                    }

                    window.location.href = url;
                },
                dailyFactor(unit) {
                    const map = { daily: 1, weekly: 7, monthly: 30, annual: 365 };
                    return map[String(unit || '').toLowerCase()] ?? 30;
                },
                stepDate(date, unit, amount = 1) {
                    const d = new Date(date.getTime());
                    const u = String(unit || '').toLowerCase();
                    if (u === 'daily') d.setDate(d.getDate() + amount);
                    else if (u === 'weekly') d.setDate(d.getDate() + (7 * amount));
                    else d.setMonth(d.getMonth() + amount);
                    return d;
                },
                formatDate(date) {
                    const y = date.getFullYear();
                    const m = String(date.getMonth() + 1).padStart(2, '0');
                    const d = String(date.getDate()).padStart(2, '0');
                    return `${d}-${m}-${y}`;
                },
                openSchedule(payload) {
                    const principal = Number(payload?.amount ?? 0);
                    const termValue = Math.max(1, Math.round(Number(payload?.termValue ?? 1)));
                    const termUnit = String(payload?.termUnit ?? 'monthly').toLowerCase();
                    const rate = Math.max(0, Number(payload?.interestRate ?? 0));
                    const ratePeriod = String(payload?.interestRatePeriod ?? 'annual').toLowerCase();
                    const interestModelRaw = String(payload?.interestModel ?? 'flat').toLowerCase();
                    const useReducing = ['reducing', 'reducing_balance', 'diminishing', 'amortized', 'amortisation', 'amortization'].includes(interestModelRaw);
                    const startRaw = payload?.startDate ? new Date(payload.startDate) : new Date();
                    const startDate = Number.isNaN(startRaw.getTime()) ? new Date() : startRaw;

                    const periodicRate = Math.max(0, (rate / 100) * (this.dailyFactor(termUnit) / this.dailyFactor(ratePeriod)));
                    let installmentPer = principal / termValue;
                    if (useReducing) {
                        installmentPer = periodicRate > 0
                            ? (principal * periodicRate / (1 - Math.pow(1 + periodicRate, -termValue)))
                            : (principal / termValue);
                    }

                    const rows = [];
                    let runningDate = startDate;
                    let balance = principal;
                    let principalTotal = 0;
                    let interestTotal = 0;
                    for (let i = 1; i <= termValue; i++) {
                        runningDate = this.stepDate(runningDate, termUnit, 1);
                        const interestBase = useReducing ? balance : principal;
                        const interestPart = periodicRate > 0 ? (interestBase * periodicRate) : 0;
                        let principalPart = installmentPer - interestPart;
                        if (i === termValue) {
                            principalPart = useReducing ? balance : (principal - principalTotal);
                            installmentPer = principalPart + interestPart;
                        }
                        principalPart = Math.max(0, principalPart);
                        const nextBalance = Math.max(0, balance - principalPart);

                        principalTotal += principalPart;
                        interestTotal += interestPart;
                        rows.push({
                            no: i,
                            date: this.formatDate(runningDate),
                            principal: principalPart,
                            interest: interestPart,
                            installment: installmentPer,
                            balance: nextBalance,
                        });
                        balance = nextBalance;
                    }

                    this.scheduleTitle = `${payload?.clientName ?? 'Client'} Loan Schedule`;
                    this.scheduleClient = String(payload?.clientName ?? 'Client');
                    this.schedulePrincipal = principal;
                    this.scheduleRateLabel = `${rate.toLocaleString(undefined, { maximumFractionDigits: 4 })}% ${ratePeriod}`;
                    this.scheduleFrequencyLabel = `${termValue} ${termUnit}`;
                    this.scheduleMethodLabel = useReducing ? 'Reducing balance' : 'Flat rate';
                    this.scheduleRows = rows;
                    this.scheduleTotals = {
                        principal: principalTotal,
                        interest: interestTotal,
                        installment: principalTotal + interestTotal,
                    };
                    this.scheduleModalOpen = true;
                },
                printSchedule() {
                    const content = document.getElementById('application-schedule-print');
                    if (!content) return;
                    const printWindow = window.open('', '_blank', 'width=900,height=700');
                    if (!printWindow) return;
                    printWindow.document.write(`<html><head><title>Loan schedule</title><style>body{font-family:Arial,sans-serif;padding:16px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px;text-align:left}.t-right{text-align:right}h2{margin:0 0 10px 0}</style></head><body>${content.innerHTML}</body></html>`);
                    printWindow.document.close();
                    printWindow.focus();
                    printWindow.print();
                },
            }"
        >
        <x-slot name="actions">
            <a href="{{ route('loan.book.app_loans_report') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                Application report
            </a>
            <a href="{{ route('loan.book.applications.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">
                Create application
            </a>
        </x-slot>

        <form method="get" class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, client, product..." class="h-10 w-72 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Stage</label>
                    <select name="stage" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($stages ?? []) as $k => $lbl)
                            <option value="{{ $k }}" @selected(($stage ?? '') === $k)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Branch</label>
                    <select name="branch" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        <option value="">All</option>
                        @foreach (($branches ?? []) as $b)
                            <option value="{{ $b }}" @selected(($branch ?? '') === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Per page</label>
                    <select name="per_page" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm">
                        @foreach ([10, 15, 25, 50, 100, 200] as $size)
                            <option value="{{ $size }}" @selected((int) ($perPage ?? 15) === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="h-10 rounded-lg bg-[#2f4f4f] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">Filter</button>
                <a href="{{ route('loan.book.applications.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Reset</a>
                <div class="ml-auto flex items-center gap-2">
                    <a :href="exportUrl('csv')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a :href="exportUrl('xls')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a :href="exportUrl('pdf')" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-700">Pipeline</h2>
                    <p class="mt-1 text-xs text-slate-500 max-w-xl">Change an application’s <strong>stage</strong> here (dropdown + Update) or use <strong>Edit</strong> for the full form. <strong>View</strong> is read-only summary and next-step hints — you do not need it to move the pipeline.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <div class="relative" @click.outside="columnMenuOpen = false">
                        <button type="button" @click="columnMenuOpen = !columnMenuOpen" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                            Columns
                        </button>
                        <div x-show="columnMenuOpen" x-cloak class="absolute right-0 mt-2 z-20 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Show / hide columns</p>
                            <div class="grid grid-cols-2 gap-2 max-h-72 overflow-y-auto pr-1">
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.application" class="rounded border-slate-300">Application</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.ref" class="rounded border-slate-300">Ref</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.client" class="rounded border-slate-300">Client</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.clientNo" class="rounded border-slate-300">Client #</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.product" class="rounded border-slate-300">Product</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.source" class="rounded border-slate-300">Source</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.term" class="rounded border-slate-300">Term</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.amount" class="rounded border-slate-300">Amount</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.guarantor" class="rounded border-slate-300">Guarantor</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.guarantorContact" class="rounded border-slate-300">Guarantor contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.residential" class="rounded border-slate-300">Residential</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.business" class="rounded border-slate-300">Business</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.asset" class="rounded border-slate-300">Asset list</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.runs" class="rounded border-slate-300">Loan runs</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.guarantor2" class="rounded border-slate-300">Guarantor2</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.guarantor2Contact" class="rounded border-slate-300">Guarantor2 contact</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.charges" class="rounded border-slate-300">Charges</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.media" class="rounded border-slate-300">Attached media</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.deductions" class="rounded border-slate-300">Deductions</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.approvedBy" class="rounded border-slate-300">Approved by</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.stage" class="rounded border-slate-300">Stage</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.branch" class="rounded border-slate-300">Branch</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.submitted" class="rounded border-slate-300">Submitted</label>
                                <label class="text-xs text-slate-700 inline-flex items-center gap-2"><input type="checkbox" x-model="cols.actions" class="rounded border-slate-300">Actions</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">{{ $applications->total() }} file(s)</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="loan-compact-table min-w-full w-full text-xs">
                    <thead class="bg-slate-50 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th x-show="cols.application" class="px-3 py-2.5">Application</th>
                            <th x-show="cols.ref" class="px-3 py-2.5">Ref</th>
                            <th x-show="cols.client" class="px-3 py-2.5">Client</th>
                            <th x-show="cols.clientNo" class="px-3 py-2.5">Client #</th>
                            <th x-show="cols.product" class="px-3 py-2.5">Product</th>
                            <th x-show="cols.source" class="px-3 py-2.5">Source</th>
                            <th x-show="cols.term" class="px-3 py-2.5">Term</th>
                            <th x-show="cols.amount" class="px-3 py-2.5 text-right">Amount</th>
                            <th x-show="cols.guarantor" class="px-3 py-2.5">Guarantor</th>
                            <th x-show="cols.guarantorContact" class="px-3 py-2.5">Guarantor contact</th>
                            <th x-show="cols.residential" class="px-3 py-2.5">Residential type</th>
                            <th x-show="cols.business" class="px-3 py-2.5">Business / purpose</th>
                            <th x-show="cols.asset" class="px-3 py-2.5">Asset list</th>
                            <th x-show="cols.runs" class="px-3 py-2.5">Loan runs</th>
                            <th x-show="cols.guarantor2" class="px-3 py-2.5">Guarantor2</th>
                            <th x-show="cols.guarantor2Contact" class="px-3 py-2.5">Guarantor2 contact</th>
                            <th x-show="cols.charges" class="px-3 py-2.5">Charges</th>
                            <th x-show="cols.media" class="px-3 py-2.5">Attached media</th>
                            <th x-show="cols.deductions" class="px-3 py-2.5">Deductions</th>
                            <th x-show="cols.approvedBy" class="px-3 py-2.5">Approved by</th>
                            <th x-show="cols.stage" class="px-3 py-2.5">Stage</th>
                            <th x-show="cols.branch" class="px-3 py-2.5">Branch</th>
                            <th x-show="cols.submitted" class="px-3 py-2.5">Submitted</th>
                            <th x-show="cols.actions" class="px-3 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $applicationsAmountTotal = 0.0;
                        @endphp
                        @forelse ($applications as $app)
                            @php
                                $sourceLabel = match ((string) ($app->submission_source ?? '')) {
                                    'tenant_portal' => 'Tenant portal',
                                    'landlord_portal' => 'Landlord portal',
                                    'manual_internal' => 'Manual/Internal',
                                    default => (function () use ($app) {
                                        $notes = strtolower((string) ($app->notes ?? ''));
                                        return str_contains($notes, 'tenant portal')
                                            ? 'Tenant portal'
                                            : (str_contains($notes, 'landlord portal') ? 'Landlord portal' : 'Manual/Internal');
                                    })(),
                                };
                                $sourceClass = str_contains($sourceLabel, 'portal')
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-700 ring-slate-200';
                                $productMeta = $productMetaByName[$app->product_name] ?? null;
                                $chargesSummary = trim((string) ($productMeta['charges_summary'] ?? ''));
                                $guarantorPrimary = trim((string) ($app->guarantor_full_name ?: ($app->loanClient?->guarantor_1_full_name ?? '')));
                                $guarantorPrimaryContact = trim((string) ($app->guarantor_phone ?: ($app->loanClient?->guarantor_1_phone ?? '')));
                                $guarantorSecondary = trim((string) ($app->loanClient?->guarantor_2_full_name ?? ''));
                                $guarantorSecondaryContact = trim((string) ($app->loanClient?->guarantor_2_phone ?? ''));
                                $residentialType = trim((string) ($app->loanClient?->address ?? ''));
                                $businessPurpose = trim((string) ($app->purpose ?? ''));
                                $assetList = trim((string) ($app->notes ?? ''));
                                $amortizationMetaRaw = collect((array) ($app->form_meta ?? []))
                                    ->filter(function ($value, $key): bool {
                                        $k = strtolower((string) $key);
                                        return str_contains($k, 'interest_type')
                                            || str_contains($k, 'rate_type')
                                            || str_contains($k, 'interest_method')
                                            || str_contains($k, 'amort')
                                            || str_contains($k, 'reducing')
                                            || str_contains($k, 'flat');
                                    })
                                    ->map(fn ($v): string => strtolower(trim((string) $v)))
                                    ->filter()
                                    ->first();
                                $amortizationModel = (is_string($amortizationMetaRaw) && (str_contains($amortizationMetaRaw, 'reduc') || str_contains($amortizationMetaRaw, 'diminish') || str_contains($amortizationMetaRaw, 'amort')))
                                    ? 'reducing'
                                    : 'flat';
                                $applicationsAmountTotal += (float) $app->amount_requested;
                            @endphp
                            <tr
                                class="cursor-pointer hover:bg-slate-50/80"
                                role="link"
                                tabindex="0"
                                @click="openRow('{{ route('loan.book.applications.show', $app) }}', $event)"
                                @keydown.enter.prevent="openRow('{{ route('loan.book.applications.show', $app) }}', $event)"
                                @keydown.space.prevent="openRow('{{ route('loan.book.applications.show', $app) }}', $event)"
                            >
                                <td x-show="cols.application" class="px-3 py-2.5 text-slate-600">{{ $app->submitted_at?->format('d.m.Y, h:i A') ?? '—' }}</td>
                                <td x-show="cols.ref" class="px-3 py-2.5 font-mono text-[11px] text-indigo-600 font-medium">{{ $app->reference }}</td>
                                <td x-show="cols.client" class="px-3 py-2.5 font-medium text-slate-900">
                                    @if ($app->loanClient)
                                        <a href="{{ route('loan.clients.show', $app->loanClient) }}" class="text-[#2f4f4f] hover:underline">
                                            {{ $app->loanClient->full_name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td x-show="cols.clientNo" class="px-3 py-2.5 text-slate-600">{{ $app->loanClient?->client_number ?? '—' }}</td>
                                <td x-show="cols.product" class="px-3 py-2.5 text-slate-600">{{ $app->product_name }}</td>
                                <td x-show="cols.source" class="px-3 py-2.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $sourceClass }}">{{ $sourceLabel }}</span>
                                </td>
                                <td x-show="cols.term" class="px-3 py-2.5 text-slate-600">
                                    {{ (int) ($app->term_value ?: $app->term_months) }}
                                    {{ ucfirst((string) ($app->term_unit ?: 'months')) }}
                                </td>
                                <td x-show="cols.amount" class="px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format((float) $app->amount_requested, 2) }}</td>
                                <td x-show="cols.guarantor" class="px-3 py-2.5 text-slate-600">{{ $guarantorPrimary !== '' ? $guarantorPrimary : '—' }}</td>
                                <td x-show="cols.guarantorContact" class="px-3 py-2.5 text-slate-600"><x-phone-link :value="$guarantorPrimaryContact" /></td>
                                <td x-show="cols.residential" class="px-3 py-2.5 text-slate-600">{{ $residentialType !== '' ? \Illuminate\Support\Str::limit($residentialType, 22) : '—' }}</td>
                                <td x-show="cols.business" class="px-3 py-2.5 text-slate-600">{{ $businessPurpose !== '' ? \Illuminate\Support\Str::limit($businessPurpose, 24) : '—' }}</td>
                                <td x-show="cols.asset" class="px-3 py-2.5 text-slate-600">{{ $assetList !== '' ? \Illuminate\Support\Str::limit($assetList, 24) : '—' }}</td>
                                <td x-show="cols.runs" class="px-3 py-2.5 text-slate-600">0</td>
                                <td x-show="cols.guarantor2" class="px-3 py-2.5 text-slate-600">{{ $guarantorSecondary !== '' ? $guarantorSecondary : '—' }}</td>
                                <td x-show="cols.guarantor2Contact" class="px-3 py-2.5 text-slate-600"><x-phone-link :value="$guarantorSecondaryContact" /></td>
                                <td x-show="cols.charges" class="px-3 py-2.5 text-slate-600">{{ $chargesSummary !== '' ? \Illuminate\Support\Str::limit($chargesSummary, 30) : '—' }}</td>
                                <td x-show="cols.media" class="px-3 py-2.5 text-slate-600">—</td>
                                <td x-show="cols.deductions" class="px-3 py-2.5 text-slate-600">Checkoff(0), Prepayment(0)</td>
                                <td x-show="cols.approvedBy" class="px-3 py-2.5 text-slate-600">{{ $app->loanClient?->assignedEmployee?->full_name ?? 'None' }}</td>
                                <td x-show="cols.stage" class="px-3 py-2.5 align-top">
                                    <form method="post" action="{{ route('loan.book.applications.update_stage', $app) }}" class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-2">
                                        @csrf
                                        @method('patch')
                                        <label class="sr-only" for="stage-{{ $app->id }}">Stage for {{ $app->reference }}</label>
                                        @if ($app->stage === \App\Models\LoanBookApplication::STAGE_DISBURSED)
                                            <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">Disbursed (locked)</span>
                                        @else
                                            <select id="stage-{{ $app->id }}" name="stage" class="min-w-[5.8rem] max-w-[6.2rem] rounded border border-slate-200 bg-white py-0.5 px-1 text-[10px] font-medium text-slate-800 shadow-sm">
                                                @foreach (($stages ?? []) as $value => $label)
                                                    @continue($value === \App\Models\LoanBookApplication::STAGE_DISBURSED)
                                                    <option value="{{ $value }}" @selected($app->stage === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold text-white shadow-sm hover:bg-slate-900">Update</button>
                                        @endif
                                    </form>
                                </td>
                                <td x-show="cols.branch" class="px-3 py-2.5 text-slate-500">{{ $app->branch ?? '—' }}</td>
                                <td x-show="cols.submitted" class="px-3 py-2.5 text-slate-500">{{ $app->submitted_at?->format('Y-m-d') ?? '—' }}</td>
                                <td x-show="cols.actions" class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <details class="relative inline-block text-left">
                                        <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                            Actions
                                        </summary>
                                        <div class="absolute right-0 z-10 mt-1 w-44 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                            @if (in_array($app->stage, [\App\Models\LoanBookApplication::STAGE_APPROVED, \App\Models\LoanBookApplication::STAGE_DISBURSED], true) && ! $app->loan)
                                                <a href="{{ route('loan.book.loans.create', ['application' => $app->id]) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-emerald-700 hover:bg-slate-50">Book loan</a>
                                            @endif
                                            <button type="button" @click="openSchedule({
                                                clientName: @js((string) ($app->loanClient?->full_name ?? 'Client')),
                                                amount: @js((float) $app->amount_requested),
                                                termValue: @js((int) ($app->term_value ?: $app->term_months ?: 1)),
                                                termUnit: @js((string) ($app->term_unit ?: 'monthly')),
                                                interestRate: @js((float) ($app->interest_rate ?? 0)),
                                                interestRatePeriod: @js((string) ($app->interest_rate_period ?? 'annual')),
                                                interestModel: @js((string) $amortizationModel),
                                                startDate: @js(optional($app->submitted_at)->format('Y-m-d')),
                                            })" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50">Schedule</button>
                                            <a href="{{ route('loan.book.applications.show', $app) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                            <a href="{{ route('loan.book.applications.edit', $app) }}" class="block rounded-md px-2 py-1.5 text-xs font-medium text-indigo-600 hover:bg-slate-50">Edit</a>
                                            <form method="post" action="{{ route('loan.book.applications.destroy', $app) }}" data-swal-confirm="Delete this application? It must not have a loan yet.">
                                                @csrf
                                                @method('delete')
                                                <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-slate-50">Delete</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="25" class="px-5 py-12 text-center text-slate-500">No applications yet. Create one to start LoanBook.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($applications->count() > 0)
                        <tfoot class="bg-slate-100/80">
                            <tr class="border-t border-slate-200">
                                <td x-show="cols.application" class="px-3 py-2.5 font-bold text-slate-800">Totals (this page)</td>
                                <td x-show="cols.ref" class="px-3 py-2.5"></td>
                                <td x-show="cols.client" class="px-3 py-2.5"></td>
                                <td x-show="cols.clientNo" class="px-3 py-2.5"></td>
                                <td x-show="cols.product" class="px-3 py-2.5"></td>
                                <td x-show="cols.source" class="px-3 py-2.5"></td>
                                <td x-show="cols.term" class="px-3 py-2.5"></td>
                                <td x-show="cols.amount" class="px-3 py-2.5 text-right tabular-nums font-bold text-slate-900">{{ number_format($applicationsAmountTotal, 2) }}</td>
                                <td x-show="cols.guarantor" class="px-3 py-2.5"></td>
                                <td x-show="cols.guarantorContact" class="px-3 py-2.5"></td>
                                <td x-show="cols.residential" class="px-3 py-2.5"></td>
                                <td x-show="cols.business" class="px-3 py-2.5"></td>
                                <td x-show="cols.asset" class="px-3 py-2.5"></td>
                                <td x-show="cols.runs" class="px-3 py-2.5"></td>
                                <td x-show="cols.guarantor2" class="px-3 py-2.5"></td>
                                <td x-show="cols.guarantor2Contact" class="px-3 py-2.5"></td>
                                <td x-show="cols.charges" class="px-3 py-2.5"></td>
                                <td x-show="cols.media" class="px-3 py-2.5"></td>
                                <td x-show="cols.deductions" class="px-3 py-2.5"></td>
                                <td x-show="cols.approvedBy" class="px-3 py-2.5"></td>
                                <td x-show="cols.stage" class="px-3 py-2.5"></td>
                                <td x-show="cols.branch" class="px-3 py-2.5"></td>
                                <td x-show="cols.submitted" class="px-3 py-2.5"></td>
                                <td x-show="cols.actions" class="px-3 py-2.5"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $applications->withQueryString()->links() }}</div>
            @endif
        </div>
        <div
            x-show="scheduleModalOpen"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
            @keydown.escape.window="scheduleModalOpen = false"
        >
            <div class="w-full max-w-3xl rounded-xl bg-white p-4 shadow-2xl" @click.away="scheduleModalOpen = false">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-800" x-text="scheduleTitle"></h3>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="printSchedule()" class="rounded border border-slate-300 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print</button>
                        <button type="button" @click="scheduleModalOpen = false" class="text-xl text-slate-400 hover:text-red-500">&times;</button>
                    </div>
                </div>
                <div id="application-schedule-print">
                    <div class="mb-2 text-sm text-slate-700">
                        <p><span class="font-semibold">Client:</span> <span x-text="scheduleClient"></span></p>
                        <p><span class="font-semibold">Principal:</span> Ksh <span x-text="Number(schedulePrincipal || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></span></p>
                        <p><span class="font-semibold">Rate:</span> <span x-text="scheduleRateLabel"></span></p>
                        <p><span class="font-semibold">Method:</span> <span x-text="scheduleMethodLabel"></span></p>
                        <p><span class="font-semibold">Frequency:</span> <span x-text="scheduleFrequencyLabel"></span></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-2 py-1 text-left">#</th>
                                    <th class="px-2 py-1 text-left">Date</th>
                                    <th class="px-2 py-1 text-right">Principal</th>
                                    <th class="px-2 py-1 text-right">Interest</th>
                                    <th class="px-2 py-1 text-right">Installment</th>
                                    <th class="px-2 py-1 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in scheduleRows" :key="row.no">
                                    <tr class="border-t border-slate-100">
                                        <td class="px-2 py-1" x-text="row.no"></td>
                                        <td class="px-2 py-1" x-text="row.date"></td>
                                        <td class="px-2 py-1 text-right" x-text="Number(row.principal || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                        <td class="px-2 py-1 text-right" x-text="Number(row.interest || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                        <td class="px-2 py-1 text-right" x-text="Number(row.installment || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                        <td class="px-2 py-1 text-right" x-text="Number(row.balance || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-300 bg-slate-50 font-semibold">
                                    <td colspan="2" class="px-2 py-1 text-right">Totals</td>
                                    <td class="px-2 py-1 text-right" x-text="Number(scheduleTotals.principal || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                    <td class="px-2 py-1 text-right" x-text="Number(scheduleTotals.interest || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                    <td class="px-2 py-1 text-right" x-text="Number(scheduleTotals.installment || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></td>
                                    <td class="px-2 py-1 text-right">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </x-loan.page>
</x-loan-layout>
