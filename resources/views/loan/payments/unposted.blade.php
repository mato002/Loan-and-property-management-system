<x-loan-layout>
    @php
        $pageItems = $payments->getCollection();
        $visibleUnpostedAmount = (float) $pageItems->sum(fn ($p) => (float) ($p->amount ?? 0));
        $todayCount = (int) $pageItems->filter(fn ($p) => optional($p->transaction_at)?->isToday())->count();
        $staleCount = (int) $pageItems->filter(fn ($p) => optional($p->transaction_at)?->lt(now()->subDay()))->count();
        $highConfidenceCount = (int) $pageItems->filter(fn ($p) => ! is_null($p->loan_book_loan_id) || ! empty($suggestedLoanByPayment[$p->id] ?? null))->count();
        $confidencePercent = $pageItems->count() > 0 ? (int) round(($highConfidenceCount / $pageItems->count()) * 100) : 0;
        $exceptionCount = (int) $pageItems->filter(function ($p) {
            $ref = strtolower((string) ($p->reference ?? ''));
            return str_contains($ref, 'dup') || is_null($p->loan_book_loan_id);
        })->count();
        $confidenceGaugeOffset = 220 - (($confidencePercent / 100) * 220);

        $branchBuckets = $pageItems
            ->groupBy(fn ($p) => $p->loan?->loanClient?->branch ?: 'Unassigned')
            ->map(fn ($rows) => (float) $rows->sum('amount'))
            ->sortDesc();
    @endphp

    <x-loan.page title="UNPOSTED PAYMENTS QUEUE" subtitle="System: Kenya · Branch: Nakuru · Last refresh: {{ now()->format('M j, Y g:i A') }}">
        @include('loan.payments.partials.flash')

        <section class="space-y-5 bg-slate-50/70" x-data="{ paymentModal:false, importModal:false, balancesModal:false, bulkModal:false, advancedFilters:false, selectedRows: [] }">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Unposted (KES)</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">KES {{ number_format($visibleUnpostedAmount, 2) }}</p>
                    <p class="mt-1 text-xs text-purple-700">{{ number_format($payments->total()) }} rows pending · {{ $todayCount }} today</p>
                    <svg class="mt-2 h-5 w-full text-purple-500" viewBox="0 0 120 20" fill="none" aria-hidden="true">
                        <path d="M2 14c12-9 18-9 30 0s18 9 30 0 18-9 30 0 18 9 26 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Auto-Match Confidence</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $confidencePercent }}%</p>
                            <p class="mt-1 text-xs {{ $confidencePercent >= 80 ? 'text-emerald-700' : 'text-amber-700' }}">Confidence: {{ $confidencePercent >= 80 ? 'High' : 'Medium' }} ({{ $confidencePercent }}%)</p>
                        </div>
                        <svg class="h-14 w-14 shrink-0" viewBox="0 0 100 100" fill="none" aria-label="Match confidence">
                            <circle cx="50" cy="50" r="35" stroke="#e2e8f0" stroke-width="8"></circle>
                            <circle cx="50" cy="50" r="35" stroke="#16a34a" stroke-width="8" stroke-linecap="round" stroke-dasharray="220" stroke-dashoffset="{{ $confidenceGaugeOffset }}" transform="rotate(-90 50 50)"></circle>
                        </svg>
                    </div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">High Priority (&gt;24 hrs)</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $staleCount }}</p>
                    <p class="mt-1 text-xs text-amber-700">Stale payments needing quick action</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Exceptions / Needs Review</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $exceptionCount }}</p>
                    <p class="mt-1 text-xs text-purple-700">Low confidence, missing match, or duplicate risk</p>
                </article>
            </div>

            <form method="get" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-[240px] flex-1">
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Search</label>
                        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Ref, Loan #, Client, Phone..." class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Channel</label>
                        <select name="channel" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                            <option value="" @selected(($channel ?? '') === '')>All</option>
                            <option value="mpesa" @selected(($channel ?? '') === 'mpesa')>M-Pesa</option>
                            <option value="bank" @selected(($channel ?? '') === 'bank')>Bank</option>
                            <option value="cash" @selected(($channel ?? '') === 'cash')>Cash</option>
                            <option value="wallet" @selected(($channel ?? '') === 'wallet')>Wallet</option>
                            <option value="other" @selected(($channel ?? '') === 'other')>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">Date</label>
                        <select class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700" onchange="if(this.value==='today'){this.form.from.value='{{ now()->toDateString() }}';this.form.to.value='{{ now()->toDateString() }}';this.form.submit();}">
                            <option>Today</option>
                            <option>Yesterday</option>
                            <option>Last 7 Days</option>
                            <option>Custom Range</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">From</label>
                        <input type="date" name="from" value="{{ $from ?? '' }}" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase text-slate-500">To</label>
                        <input type="date" name="to" value="{{ $to ?? '' }}" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700">
                    </div>
                    <button type="button" @click="advancedFilters=!advancedFilters" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Advanced Filters</button>
                    <button type="submit" class="h-10 rounded-lg bg-teal-800 px-4 text-sm font-semibold text-white hover:bg-teal-900">Filter</button>
                    <a href="{{ route('loan.payments.unposted') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
                        <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'xls'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                        <a href="{{ route('loan.payments.unposted', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
                        <a href="{{ route('loan.payments.unposted.print', request()->query()) }}" target="_blank" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print</a>
                    </div>
                </div>
                <div x-show="advancedFilters" x-cloak class="mt-3 grid grid-cols-1 gap-2 border-t border-slate-100 pt-3 md:grid-cols-4">
                    <input type="text" placeholder="Branch..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Destination paybill/till..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Confidence range..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                    <input type="text" placeholder="Amount range..." class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                    <div class="relative">
                        <button type="button" @click="bulkModal=true" class="inline-flex h-10 items-center rounded-lg border border-teal-700 bg-teal-700 px-4 text-sm font-semibold text-white hover:bg-teal-800">BULK ACTIONS</button>
                    </div>
                    <form method="post" action="{{ route('loan.payments.unposted.auto_match') }}" class="inline-flex">
                        @csrf
                        <input type="hidden" name="q" value="{{ $q ?? '' }}">
                        <input type="hidden" name="channel" value="{{ $channel ?? '' }}">
                        <input type="hidden" name="from" value="{{ $from ?? '' }}">
                        <input type="hidden" name="to" value="{{ $to ?? '' }}">
                        <input type="hidden" name="per_page" value="{{ $perPage ?? 20 }}">
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Auto-match</button>
                    </form>
                    <a href="{{ route('loan.payments.merge') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Merge</a>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <button type="button" @click="paymentModal=true" class="inline-flex h-10 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">Payment</button>
                        <button type="button" @click="importModal=true" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Import</button>
                        <button type="button" @click="balancesModal=true" class="inline-flex h-10 items-center rounded-lg border border-purple-300 bg-purple-50 px-4 text-sm font-semibold text-purple-700 hover:bg-purple-100">Balances</button>
                    </div>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">Unposted Queue</h2>
                    <p class="text-xs text-slate-500">{{ number_format($payments->total()) }} row(s)</p>
                </div>
                <div class="overflow-x-hidden">
                    <table class="w-full table-auto text-[10px] lg:text-[11px]">
                        <thead class="sticky top-0 z-10 bg-slate-100 text-[10px] uppercase tracking-wide text-slate-600">
                            <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                <th class="px-1.5 py-2 text-center">
                                    <input type="checkbox" @change="selectedRows = $event.target.checked ? {{ \Illuminate\Support\Js::from($pageItems->pluck('id')->all()) }} : []" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                </th>
                                <th class="px-2 py-2 text-left">Reference</th>
                                <th class="px-2 py-2 text-left">Loan</th>
                                <th class="px-2 py-2 text-left">Client</th>
                                <th class="px-2 py-2 text-right">Amount</th>
                                <th class="px-2 py-2 text-left">Provider</th>
                                <th class="px-2 py-2 text-left">Kind</th>
                                <th class="px-2 py-2 text-left">Status / Reconciliation</th>
                                <th class="px-2 py-2 text-left">DPD</th>
                                <th class="px-2 py-2 text-left">When</th>
                                <th class="px-2 py-2 text-left">Payer #</th>
                                <th class="px-2 py-2 text-left">Receipt</th>
                                <th class="px-2 py-2 text-left">Message</th>
                                <th class="px-2 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-slate-700">
                            @forelse ($payments as $p)
                                @php
                                    $unpostedRowUrl = $p->loan?->loanClient ? route('loan.clients.show', $p->loan->loanClient) : null;
                                    $rawChannel = strtolower(trim((string) ($p->channel ?? '')));
                                    $rawMessage = strtolower((string) ($p->message ?? ''));
                                    $providerLabel = match (true) {
                                        str_contains($rawMessage, 'equity') || str_contains($rawMessage, 'equitel') || str_contains($rawMessage, 'eazzy') => 'Equity',
                                        str_starts_with($rawChannel, 'equity_'), $rawChannel === 'equity' => 'Equity',
                                        str_starts_with($rawChannel, 'mpesa_'), $rawChannel === 'mpesa' => 'M-Pesa',
                                        str_starts_with($rawChannel, 'paypal_'), $rawChannel === 'paypal' => 'PayPal',
                                        str_starts_with($rawChannel, 'bank_'), $rawChannel === 'bank' => 'Bank',
                                        str_starts_with($rawChannel, 'card_'), $rawChannel === 'card' => 'Card',
                                        str_starts_with($rawChannel, 'cash_'), $rawChannel === 'cash' => 'Cash',
                                        str_starts_with($rawChannel, 'cheque_'), $rawChannel === 'cheque' => 'Cheque',
                                        default => 'Unknown',
                                    };
                                    $rowDpd = (int) ($p->loan?->dpd ?? 0);
                                    $rowConfidence = ! is_null($p->loan_book_loan_id) ? 95 : (! empty($suggestedLoanByPayment[$p->id] ?? null) ? 74 : 36);
                                    $rowStatus = ! is_null($p->loan_book_loan_id)
                                        ? 'Matched'
                                        : (! empty($suggestedLoanByPayment[$p->id] ?? null) ? 'Partial Match' : 'Needs Review');
                                    $statusClass = $rowStatus === 'Matched'
                                        ? 'border-emerald-200 bg-emerald-100 text-emerald-700'
                                        : ($rowStatus === 'Partial Match' ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-purple-200 bg-purple-100 text-purple-700');
                                    $rowClasses = optional($p->transaction_at)?->lt(now()->subDay()) ? 'bg-amber-50/40' : '';
                                @endphp
                                <tr class="{{ $rowClasses }} hover:bg-slate-50/80 [&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0 {{ $unpostedRowUrl ? 'cursor-pointer' : '' }}"
                                    @if ($unpostedRowUrl)
                                        role="link"
                                        tabindex="0"
                                        onclick="if (event.target.closest('a, button, input, select, textarea, form, label, summary, details')) return; window.location.href='{{ $unpostedRowUrl }}';"
                                        onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a, button, input, select, textarea, form, label, summary, details')) { event.preventDefault(); window.location.href='{{ $unpostedRowUrl }}'; }"
                                    @endif
                                >
                                    <td class="px-1.5 py-2 text-center align-top">
                                        <input type="checkbox" value="{{ $p->id }}" @change="$event.target.checked ? selectedRows.push({{ $p->id }}) : selectedRows = selectedRows.filter(id => id !== {{ $p->id }})" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                    </td>
                                    <td class="px-2 py-2 align-top font-mono"><a href="#" class="font-semibold text-blue-600 hover:text-blue-700">{{ $p->reference }}</a></td>
                                    <td class="px-2 py-2 align-top">@if ($p->loan)<a href="{{ route('loan.book.loans.show', $p->loan) }}" class="text-blue-600 hover:underline">{{ $p->loan->loan_number }}</a>@else — @endif</td>
                                    <td class="px-2 py-2 align-top">@if ($p->loan?->loanClient)<a href="{{ route('loan.clients.show', $p->loan->loanClient) }}" class="text-teal-800 hover:underline">{{ $p->loan->loanClient->full_name }}</a>@else — @endif</td>
                                    <td class="px-2 py-2 align-top text-right font-semibold text-emerald-700">{{ $p->currency }} {{ number_format((float) $p->amount, 2) }}</td>
                                    <td class="px-2 py-2 align-top"><span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-1.5 py-0.5">{{ $providerLabel }}</span></td>
                                    <td class="px-2 py-2 align-top">{{ str_replace('_', ' ', $p->payment_kind) }}</td>
                                    <td class="px-2 py-2 align-top">
                                        <span class="inline-flex rounded-full border px-1.5 py-0.5 font-semibold {{ $statusClass }}">{{ $rowStatus }} ({{ $rowConfidence }}%)</span>
                                    </td>
                                    <td class="px-2 py-2 align-top"><span class="inline-flex rounded-full border {{ $rowDpd > 0 ? 'border-amber-200 bg-amber-100 text-amber-700' : 'border-emerald-200 bg-emerald-100 text-emerald-700' }} px-1.5 py-0.5 font-semibold">{{ $rowDpd }}</span></td>
                                    <td class="px-2 py-2 align-top whitespace-nowrap">{{ optional($p->transaction_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="px-2 py-2 align-top font-mono">{{ $p->payer_msisdn ?? '—' }}</td>
                                    <td class="px-2 py-2 align-top font-mono">{{ $p->mpesa_receipt_number ?? '—' }}</td>
                                    <td class="px-2 py-2 align-top text-slate-600">
                                        <div class="line-clamp-2">{{ $p->message ?? '—' }}</div>
                                    </td>
                                    <td class="px-2 py-2 align-top text-right">
                                        <details class="relative inline-block text-left">
                                            <summary class="inline-flex cursor-pointer list-none items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Actions</summary>
                                            <div class="absolute right-0 z-10 mt-1 w-72 rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                                                @if ((str_ends_with((string) $p->channel, '_sms_unmatched') || str_ends_with((string) $p->channel, '_sms_disbursement_unmatched')) && is_null($p->loan_book_loan_id))
                                                    <form method="post" action="{{ route('loan.payments.assign_loan', $p) }}" class="mb-2 flex items-center gap-2">
                                                        @csrf
                                                        <input type="hidden" name="post_now" value="1">
                                                        <select name="loan_book_loan_id" required class="h-8 flex-1 rounded-lg border border-amber-300 bg-amber-50 px-2 text-xs text-slate-700">
                                                            <option value="">Assign loan...</option>
                                                            @foreach (($assignableLoanOptions ?? collect()) as $loanId => $loanLabel)
                                                                <option value="{{ $loanId }}" @selected((int) ($suggestedLoanByPayment[$p->id] ?? 0) === (int) $loanId)>{{ $loanLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="submit" class="h-8 rounded-lg bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">Assign &amp; Post</button>
                                                    </form>
                                                @endif
                                                @if (! is_null($p->loan_book_loan_id))
                                                    <form method="post" action="{{ route('loan.payments.post', $p) }}" class="mb-1">
                                                        @csrf
                                                        <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-teal-800 hover:bg-slate-50">Confirm / Post</button>
                                                    </form>
                                                @endif
                                                @if ($p->canEdit())
                                                    <a href="{{ route('loan.payments.edit', $p) }}" class="mb-1 block rounded-md px-2 py-1.5 text-left text-xs font-medium text-blue-600 hover:bg-slate-50">Edit / Manual assign</a>
                                                    <button type="button" class="mb-1 block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-purple-700 hover:bg-slate-50">Mark Needs Review</button>
                                                    <form method="post" action="{{ route('loan.payments.destroy', $p) }}" data-swal-confirm="Delete this unposted payment?">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="block w-full rounded-md px-2 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-slate-50">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-5 py-12 text-center text-slate-500">
                                        No unposted payments. Use <span class="font-medium text-slate-700">Payment</span> or <span class="font-medium text-slate-700">Import</span>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($payments->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">
                        {{ $payments->links() }}
                    </div>
                @endif
            </div>

            <div x-show="bulkModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 p-4" @click.self="bulkModal=false">
                <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-slate-900">Confirm Bulk Action</h3>
                    <p class="mt-2 text-sm text-slate-600">You are about to bulk post <span class="font-semibold" x-text="selectedRows.length"></span> selected payments to their best matches.</p>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                        <li>Total KES amount: based on current selection</li>
                        <li>Confirmed matches: auto-detected from selected rows</li>
                        <li>Warning rows: review before posting</li>
                    </ul>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" @click="bulkModal=false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                        <button type="button" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white">Yes, Post Selected</button>
                    </div>
                </div>
            </div>

            <div x-show="balancesModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 p-4" @click.self="balancesModal=false">
                <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Balances</h3>
                        <button type="button" @click="balancesModal=false" class="rounded p-1 text-slate-500 hover:bg-slate-100">✕</button>
                    </div>
                    <div class="overflow-x-auto p-5">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-xs uppercase tracking-wide text-slate-600">
                                <tr class="[&>th]:border-b [&>th]:border-r [&>th]:border-slate-300 [&>th:last-child]:border-r-0">
                                    <th class="px-3 py-2 text-left">Branch</th>
                                    <th class="px-3 py-2 text-left">Paybill / Till / Account</th>
                                    <th class="px-3 py-2 text-right">Balance</th>
                                    <th class="px-3 py-2 text-left">Status</th>
                                    <th class="px-3 py-2 text-left">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700">
                                @forelse ($branchBuckets as $branch => $amount)
                                    <tr class="[&>td]:border-b [&>td]:border-r [&>td]:border-slate-200 [&>td:last-child]:border-r-0">
                                        <td class="px-3 py-2">{{ $branch }}</td>
                                        <td class="px-3 py-2">{{ str($branch)->slug('_') }}_paybill</td>
                                        <td class="px-3 py-2 text-right font-semibold text-emerald-700">KES {{ number_format($amount, 2) }}</td>
                                        <td class="px-3 py-2"><span class="inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Healthy</span></td>
                                        <td class="px-3 py-2">{{ now()->format('M j, Y g:i A') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No balance data available.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Refresh</button>
                            <button type="button" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white">View details</button>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 p-4" @click.self="importModal=false">
                <div class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Import Payments</h3>
                        <button type="button" @click="importModal=false" class="rounded p-1 text-slate-500 hover:bg-slate-100">✕</button>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                            <p class="text-sm font-medium text-slate-700">Drag and drop CSV/Excel here, or choose file</p>
                            <input type="file" accept=".csv,.xlsx,.xls" class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-700 file:px-3 file:py-2 file:text-white">
                            <a href="#" class="mt-2 inline-flex text-xs font-semibold text-blue-600 hover:text-blue-700">Download template</a>
                        </div>
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
                            <div class="rounded-lg border border-slate-200 bg-white p-2 text-xs"><p class="text-slate-500">Rows found</p><p class="font-semibold text-slate-900">0</p></div>
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-xs"><p class="text-emerald-700">Valid rows</p><p class="font-semibold text-emerald-800">0</p></div>
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs"><p class="text-amber-700">Duplicates</p><p class="font-semibold text-amber-800">0</p></div>
                            <div class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs"><p class="text-rose-700">Failed rows</p><p class="font-semibold text-rose-800">0</p></div>
                            <div class="rounded-lg border border-purple-200 bg-purple-50 p-2 text-xs"><p class="text-purple-700">Needs review</p><p class="font-semibold text-purple-800">0</p></div>
                        </div>
                        <div class="flex justify-between">
                            <a href="#" class="inline-flex items-center text-xs font-semibold text-blue-600 hover:text-blue-700">Download error report</a>
                            <div class="flex gap-2">
                                <button type="button" @click="importModal=false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                                <button type="button" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white">Validate &amp; Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="paymentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 p-4" @click.self="paymentModal=false">
                <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Manual Payment Booking</h3>
                        <button type="button" @click="paymentModal=false" class="rounded p-1 text-slate-500 hover:bg-slate-100">✕</button>
                    </div>
                    <form action="{{ route('loan.payments.create') }}" method="get" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-2">
                        <input type="text" placeholder="Client / Loan lookup" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="text" placeholder="Payer name" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="text" placeholder="Payer phone" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <select class="h-10 rounded-lg border border-slate-300 px-3 text-sm"><option>Channel</option><option>M-Pesa</option><option>Bank</option><option>Cash</option></select>
                        <input type="number" step="0.01" placeholder="Amount" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="text" placeholder="Reference / transaction code" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="text" placeholder="Branch" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="text" placeholder="Receiving account / paybill / till" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="datetime-local" class="h-10 rounded-lg border border-slate-300 px-3 text-sm">
                        <input type="file" class="h-10 rounded-lg border border-slate-300 px-3 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2">
                        <textarea placeholder="Notes" class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <p class="md:col-span-2 text-xs text-amber-700">Duplicate/reference validation is required before final save.</p>
                        <div class="md:col-span-2 flex justify-end gap-2">
                            <button type="button" @click="paymentModal=false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Continue to Payment Form</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </x-loan.page>
</x-loan-layout>
