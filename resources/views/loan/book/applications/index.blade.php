<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
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
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">CSV</a>
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Excel</a>
                    <a href="{{ route('loan.book.applications.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">PDF</a>
                </div>
            </div>
        </form>

        <div
            class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"
            x-data="{
                columnMenuOpen: false,
                cols: {
                    application: true,
                    ref: true,
                    client: true,
                    clientNo: true,
                    product: true,
                    source: true,
                    term: true,
                    amount: true,
                    guarantor: true,
                    guarantorContact: true,
                    residential: true,
                    business: true,
                    asset: true,
                    runs: true,
                    guarantor2: true,
                    guarantor2Contact: true,
                    charges: true,
                    media: true,
                    deductions: true,
                    approvedBy: true,
                    stage: true,
                    branch: true,
                    submitted: true,
                    actions: true
                }
            }"
        >
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
                <table class="min-w-full text-xs">
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
                            @endphp
                            <tr class="hover:bg-slate-50/80">
                                <td x-show="cols.application" class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $app->submitted_at?->format('d.m.Y, h:i A') ?? '—' }}</td>
                                <td x-show="cols.ref" class="px-3 py-2.5 font-mono text-[11px] text-indigo-600 font-medium">{{ $app->reference }}</td>
                                <td x-show="cols.client" class="px-3 py-2.5 font-medium text-slate-900">{{ $app->loanClient?->full_name ?? '—' }}</td>
                                <td x-show="cols.clientNo" class="px-3 py-2.5 text-slate-600">{{ $app->loanClient?->client_number ?? '—' }}</td>
                                <td x-show="cols.product" class="px-3 py-2.5 text-slate-600">{{ $app->product_name }}</td>
                                <td x-show="cols.source" class="px-3 py-2.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $sourceClass }}">{{ $sourceLabel }}</span>
                                </td>
                                <td x-show="cols.term" class="px-3 py-2.5 text-slate-600 whitespace-nowrap">
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
                                            <select id="stage-{{ $app->id }}" name="stage" class="min-w-[8.8rem] max-w-[10rem] rounded-lg border border-slate-200 bg-white py-1 px-2 text-[11px] font-medium text-slate-800 shadow-sm">
                                                @foreach (($stages ?? []) as $value => $label)
                                                    @continue($value === \App\Models\LoanBookApplication::STAGE_DISBURSED)
                                                    <option value="{{ $value }}" @selected($app->stage === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-slate-800 px-2 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-slate-900 whitespace-nowrap">Update</button>
                                        @endif
                                    </form>
                                </td>
                                <td x-show="cols.branch" class="px-3 py-2.5 text-slate-500">{{ $app->branch ?? '—' }}</td>
                                <td x-show="cols.submitted" class="px-3 py-2.5 text-slate-500 whitespace-nowrap">{{ $app->submitted_at?->format('Y-m-d') ?? '—' }}</td>
                                <td x-show="cols.actions" class="px-3 py-2.5 text-right whitespace-nowrap">
                                    @if (in_array($app->stage, [\App\Models\LoanBookApplication::STAGE_APPROVED, \App\Models\LoanBookApplication::STAGE_DISBURSED], true) && ! $app->loan)
                                        <a href="{{ route('loan.book.loans.create', ['application' => $app->id]) }}" class="text-emerald-700 font-semibold text-sm hover:underline mr-3">Book loan</a>
                                    @endif
                                    <a href="{{ route('loan.book.applications.show', $app) }}" class="text-slate-700 font-medium text-sm hover:underline mr-3">View</a>
                                    <a href="{{ route('loan.book.applications.edit', $app) }}" class="text-indigo-600 font-medium text-sm hover:underline mr-3">Edit</a>
                                    <form method="post" action="{{ route('loan.book.applications.destroy', $app) }}" class="inline" data-swal-confirm="Delete this application? It must not have a loan yet.">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-sm hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="25" class="px-5 py-12 text-center text-slate-500">No applications yet. Create one to start LoanBook.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())
                <div class="px-5 py-3 border-t border-slate-100">{{ $applications->withQueryString()->links() }}</div>
            @endif
        </div>
    </x-loan.page>
</x-loan-layout>
