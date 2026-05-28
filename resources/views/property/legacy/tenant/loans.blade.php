<x-property-layout>
    <x-slot name="header">Loans</x-slot>

    <x-property.page
        title="Loan services"
        subtitle="Submit your loan request directly from tenant portal."
    >
        @if (session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->has('loan'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first('loan') }}
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3 mb-4">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Portal role</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Tenant</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Loan request path</p>
                <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">Direct application</p>
            </div>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-4 md:col-span-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Property arrears gate</p>
                <p class="mt-1 text-lg font-semibold {{ $hasArrears ? 'text-red-600 dark:text-red-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                    {{ $hasArrears ? 'Arrears pending: '.\App\Services\Property\PropertyMoney::kes((float) $arrearsOutstanding) : 'No arrears — loan application allowed' }}
                </p>
                @if ($hasArrears)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">You must clear outstanding rent arrears before opening the loan application flow.</p>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Your active loans</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Track repayment deadline countdown, outstanding return amount, and submit repayment.</p>
            @if(($portalLoans ?? collect())->isEmpty())
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    No active loans found yet. Once approved/disbursed, they will appear here.
                </div>
            @else
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @foreach($portalLoans as $loan)
                        @php
                            $maturity = $loan->maturity_date;
                            $daysLeft = $maturity ? now()->startOfDay()->diffInDays($maturity, false) : null;
                            $countdownLabel = is_null($daysLeft)
                                ? 'No maturity date set'
                                : ($daysLeft >= 0 ? $daysLeft.' day(s) remaining' : abs($daysLeft).' day(s) overdue');
                        @endphp
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-900/40 p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">{{ $loan->loan_number ?: ('Loan #'.$loan->id) }} • {{ ucfirst(str_replace('_', ' ', $loan->status)) }}</p>
                            <p class="mt-1 text-base font-semibold text-slate-900 dark:text-white">{{ $loan->product_name ?: 'Loan facility' }}</p>
                            <div class="mt-3 grid gap-2 text-sm">
                                <div class="flex items-center justify-between"><span class="text-slate-500">Return amount</span><span class="font-semibold text-slate-900 dark:text-white">{{ \App\Services\Property\PropertyMoney::kes((float) ($loan->return_amount ?? 0)) }}</span></div>
                                <div class="flex items-center justify-between"><span class="text-slate-500">Repaid (posted)</span><span class="font-semibold text-slate-900 dark:text-white">{{ \App\Services\Property\PropertyMoney::kes((float) ($loan->processed_paid_total ?? 0)) }}</span></div>
                                <div class="flex items-center justify-between"><span class="text-slate-500">Maturity date</span><span class="font-semibold {{ !is_null($daysLeft) && $daysLeft < 0 ? 'text-red-600' : 'text-slate-900 dark:text-white' }}">{{ $maturity ? $maturity->format('M d, Y') : '—' }}</span></div>
                                <div class="flex items-center justify-between"><span class="text-slate-500">Countdown</span><span class="font-semibold {{ !is_null($daysLeft) && $daysLeft < 0 ? 'text-red-600' : 'text-emerald-700 dark:text-emerald-400' }}">{{ $countdownLabel }}</span></div>
                            </div>
                            <form method="post" action="{{ route('property.tenant.loans.repay') }}" class="mt-4 grid gap-2 sm:grid-cols-2" data-swal-confirm="Submit this loan repayment request?">
                                @csrf
                                <input type="hidden" name="loan_id" value="{{ $loan->id }}">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Repay amount</label>
                                    <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', number_format((float) ($loan->return_amount ?? 0), 2, '.', '')) }}" class="w-full rounded-lg border-slate-200 text-sm" required />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Channel</label>
                                    <select name="channel" class="w-full rounded-lg border-slate-200 text-sm">
                                        @foreach(['mpesa' => 'M-Pesa', 'bank' => 'Bank', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old('channel') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Transaction date</label>
                                    <input name="transaction_at" type="date" value="{{ old('transaction_at', now()->toDateString()) }}" class="w-full rounded-lg border-slate-200 text-sm" required />
                                </div>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Repay now</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-gray-800/80 p-5">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Apply for a loan</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Submit your application here and it will appear in the loan module applications queue for review.
            </p>
            @if ($hasArrears)
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    Loan application is locked until your arrears are fully cleared.
                </div>
            @else
                <form method="post" action="{{ route('property.tenant.loans.apply') }}" class="mt-4 grid gap-4 sm:grid-cols-2" data-swal-confirm="Submit this loan application?">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Loan product</label>
                        <input name="product_name" value="{{ old('product_name', $defaultProductName ?? '') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Amount requested</label>
                        <input name="amount_requested" type="number" min="1" step="0.01" value="{{ old('amount_requested') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Term (months)</label>
                        <input name="term_months" type="number" min="1" max="600" value="{{ old('term_months', 12) }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Purpose</label>
                        <textarea name="purpose" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('purpose', $defaultPurpose ?? '') }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Branch (optional)</label>
                        <input name="branch" value="{{ old('branch') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Additional notes (optional)</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">
                            Submit loan request
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </x-property.page>
</x-property-layout>
