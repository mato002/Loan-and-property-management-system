@php
    $showToolbar = $showToolbar ?? true;
    $activeCount = (int) ($activeLoanCounts[$product->name] ?? 0);
    $interestType = (string) ($product->default_interest_rate_type ?? 'percent');
    $periodRaw = strtolower((string) ($product->default_interest_rate_period ?? 'annual'));
    $periodLabel = match ($periodRaw) {
        'daily' => 'Per day',
        'weekly' => 'Per week',
        'monthly' => 'Per month',
        'annual' => 'Per year',
        default => $periodRaw,
    };
    $penaltyType = (string) ($product->penalty_amount_type ?? 'fixed');
    $rolloverType = (string) ($product->rollover_fees_type ?? 'fixed');
    $offsetType = (string) ($product->loan_offset_fees_type ?? 'fixed');
@endphp
<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    @if ($showToolbar)
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-3 mb-3">
            <div class="min-w-0 flex-1">
                <h3 class="text-base font-semibold text-slate-900 truncate">{{ $product->name }}</h3>
                <p class="mt-1 text-sm text-slate-600 break-words">{{ $product->description ?: 'No description' }}</p>
                <p class="mt-2 text-xs text-slate-500">
                    Active / restructured loans using this product name: <span class="font-semibold text-slate-700">{{ $activeCount }}</span>
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $product->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                </span>
                <button
                    type="button"
                    class="js-edit-product inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                    data-product-id="{{ $product->id }}"
                >
                    View details
                </button>
                <button
                    type="button"
                    class="js-edit-product inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                    data-product-id="{{ $product->id }}"
                >
                    Edit
                </button>
                <form method="post" action="{{ route('loan.system.setup.loan_products.destroy', $product) }}" class="inline" data-swal-confirm="Remove this loan product?">
                    @csrf
                    @method('delete')
                    <button type="submit" class="inline-flex items-center rounded-lg px-2 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Delete</button>
                </form>
            </div>
        </div>
    @else
        <div class="mb-3 flex flex-wrap items-center gap-2 border-b border-slate-100 pb-3">
            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $product->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                {{ $product->is_active ? 'Active' : 'Inactive' }}
            </span>
            <p class="text-sm text-slate-600">
                Active / restructured loans: <span class="font-semibold text-slate-800">{{ $activeCount }}</span>
            </p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Interest &amp; term</p>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Default rate</dt>
                    <dd class="text-right font-medium text-slate-900 tabular-nums">
                        @if ($product->default_interest_rate !== null)
                            {{ $interestType === 'percent' ? number_format((float) $product->default_interest_rate, 4).'%' : number_format((float) $product->default_interest_rate, 2) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Interest applies</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $periodLabel }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Rate type</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $interestType === 'percent' ? 'Percentage' : 'Fixed amount' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Default term</dt>
                    <dd class="text-right font-medium text-slate-900">
                        @if ($product->default_term_months !== null)
                            {{ $product->default_term_months }} {{ $product->default_term_unit ?? 'monthly' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if ($product->payment_interval_days !== null)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Payment interval</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $product->payment_interval_days }} days</dd>
                    </div>
                @endif
                @if ($product->interest_type)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Loan math type</dt>
                        <dd class="text-right font-medium text-slate-900">{{ str_replace('_', ' ', $product->interest_type) }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amounts &amp; range</p>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Min — max loan</dt>
                    <dd class="text-right font-medium text-slate-900 tabular-nums text-xs">
                        @if ($product->min_loan_amount !== null || $product->max_loan_amount !== null)
                            {{ $product->min_loan_amount !== null ? number_format((float) $product->min_loan_amount, 2) : '0.00' }}
                            —
                            {{ $product->max_loan_amount !== null ? number_format((float) $product->max_loan_amount, 2) : 'No cap' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @if ($product->total_interest_amount !== null)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Total interest (setup)</dt>
                        <dd class="text-right font-medium text-slate-900 tabular-nums">{{ number_format((float) $product->total_interest_amount, 2) }}</dd>
                    </div>
                @endif
                @if ($product->interest_duration_value !== null)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Interest duration</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $product->interest_duration_value }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 sm:col-span-2 xl:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Penalties &amp; checkoff</p>
            <dl class="mt-2 space-y-1.5 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Arrears scope</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->arrears_penalty_scope ? str_replace('_', ' ', $product->arrears_penalty_scope) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Penalty</dt>
                    <dd class="text-right font-medium text-slate-900 tabular-nums">
                        {{ $product->penalty_amount !== null ? ($penaltyType === 'percent' ? number_format((float) $product->penalty_amount, 4).'%' : number_format((float) $product->penalty_amount, 2)) : '—' }}
                    </dd>
                </div>
                @if ($product->rollover_fees !== null)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Rollover fees</dt>
                        <dd class="text-right font-medium text-slate-900 tabular-nums">{{ $rolloverType === 'percent' ? number_format((float) $product->rollover_fees, 4).'%' : number_format((float) $product->rollover_fees, 2) }}</dd>
                    </div>
                @endif
                @if ($product->loan_offset_fees !== null)
                    <div class="flex justify-between gap-2">
                        <dt class="text-slate-500">Loan offset fees</dt>
                        <dd class="text-right font-medium text-slate-900 tabular-nums">{{ $offsetType === 'percent' ? number_format((float) $product->loan_offset_fees, 4).'%' : number_format((float) $product->loan_offset_fees, 2) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Repay waiver days</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->repay_waiver_days !== null ? $product->repay_waiver_days : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Checkoff exempt</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->exempt_from_checkoffs ? 'Yes' : 'No' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 sm:col-span-2 xl:col-span-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Charges</p>
            @if (($hasProductCharges ?? false) && $product->charges->isNotEmpty())
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($product->charges as $charge)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 rounded border border-white bg-white px-3 py-2">
                            <span class="font-semibold text-slate-800">{{ $charge->charge_name }}</span>
                            <span class="text-slate-600">
                                {{ $charge->amount_type === 'percent' ? number_format((float) $charge->amount, 4).'%' : number_format((float) $charge->amount, 2) }}
                                <span class="text-slate-400">·</span> {{ str_replace('_', ' ', $charge->applies_to_stage) }}
                                <span class="text-slate-400">·</span> {{ str_replace('_', ' ', $charge->applies_to_client_scope) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-slate-500">No charges configured for this product.</p>
            @endif
        </div>

        <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3 sm:col-span-2 xl:col-span-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Other defaults</p>
            <dl class="mt-2 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Client application scope</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->client_application_scope ? str_replace('_', ' ', $product->client_application_scope) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Installment display</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->installment_display_mode ? str_replace('_', ' ', $product->installment_display_mode) : '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500">Cluster</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $product->cluster_name ?: '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
