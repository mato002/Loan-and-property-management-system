<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup.loan_products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#264040] transition-colors">+ Add product</a>
            <a href="{{ route('loan.system.setup') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to setup</a>
        </x-slot>

        @php
            $productModalPayload = $products->mapWithKeys(function ($product) use ($activeLoanCounts, $hasProductCharges) {
                return [
                    (string) $product->id => [
                        'id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'description' => (string) ($product->description ?? ''),
                        'default_interest_rate' => $product->default_interest_rate !== null ? (float) $product->default_interest_rate : null,
                        'default_interest_rate_type' => (string) ($product->default_interest_rate_type ?? 'percent'),
                        'default_term_months' => $product->default_term_months !== null ? (int) $product->default_term_months : null,
                        'default_interest_rate_period' => (string) ($product->default_interest_rate_period ?? 'annual'),
                        'default_term_unit' => (string) ($product->default_term_unit ?? 'monthly'),
                        'payment_interval_days' => $product->payment_interval_days !== null ? (int) $product->payment_interval_days : null,
                        'total_interest_amount' => $product->total_interest_amount !== null ? (float) $product->total_interest_amount : null,
                        'interest_duration_value' => $product->interest_duration_value !== null ? (int) $product->interest_duration_value : null,
                        'interest_type' => (string) ($product->interest_type ?? ''),
                        'min_loan_amount' => $product->min_loan_amount !== null ? (float) $product->min_loan_amount : null,
                        'max_loan_amount' => $product->max_loan_amount !== null ? (float) $product->max_loan_amount : null,
                        'arrears_penalty_scope' => (string) ($product->arrears_penalty_scope ?? ''),
                        'penalty_amount' => $product->penalty_amount !== null ? (float) $product->penalty_amount : null,
                        'penalty_amount_type' => (string) ($product->penalty_amount_type ?? 'fixed'),
                        'rollover_fees' => $product->rollover_fees !== null ? (float) $product->rollover_fees : null,
                        'rollover_fees_type' => (string) ($product->rollover_fees_type ?? 'fixed'),
                        'loan_offset_fees' => $product->loan_offset_fees !== null ? (float) $product->loan_offset_fees : null,
                        'loan_offset_fees_type' => (string) ($product->loan_offset_fees_type ?? 'fixed'),
                        'repay_waiver_days' => $product->repay_waiver_days !== null ? (int) $product->repay_waiver_days : null,
                        'client_application_scope' => (string) ($product->client_application_scope ?? ''),
                        'installment_display_mode' => (string) ($product->installment_display_mode ?? ''),
                        'exempt_from_checkoffs' => (bool) $product->exempt_from_checkoffs,
                        'cluster_name' => (string) ($product->cluster_name ?? ''),
                        'is_active' => (bool) $product->is_active,
                        'active_loans' => (int) ($activeLoanCounts[$product->name] ?? 0),
                        'charges' => ($hasProductCharges ?? false)
                            ? $product->charges->map(fn ($charge) => [
                                'name' => (string) $charge->charge_name,
                                'type' => (string) ($charge->amount_type ?? 'fixed'),
                                'amount' => $charge->amount !== null ? (float) $charge->amount : null,
                                'applies_to_stage' => (string) ($charge->applies_to_stage ?? 'installment'),
                                'applies_to_client_scope' => (string) ($charge->applies_to_client_scope ?? 'all'),
                            ])->values()->all()
                            : [],
                    ],
                ];
            })->all();
            $updateActionTemplate = route('loan.system.setup.loan_products.update', ['loan_product' => '__PRODUCT_ID__']);
        @endphp

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-slate-700">Product pricing master</h2>
                <p class="text-xs text-slate-500">{{ $products->count() }} product(s)</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1280px] w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3 text-right">Default interest</th>
                            <th class="px-4 py-3 text-right">Default term</th>
                            <th class="px-4 py-3 text-right">Range</th>
                            <th class="px-4 py-3">Penalty/checkoff</th>
                            <th class="px-4 py-3">Charges</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Edit</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50/70 align-top">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $product->description ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700 whitespace-nowrap">
                                    @if ($product->default_interest_rate !== null)
                                        @php($interestType = (string) ($product->default_interest_rate_type ?? 'percent'))
                                        {{ $interestType === 'percent' ? number_format((float) $product->default_interest_rate, 4).'%' : number_format((float) $product->default_interest_rate, 2) }} / {{ $product->default_interest_rate_period ?? 'annual' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700 whitespace-nowrap">
                                    @if ($product->default_term_months !== null)
                                        {{ $product->default_term_months }} {{ $product->default_term_unit ?? 'monthly' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-xs text-slate-700 whitespace-nowrap">
                                    @if ($product->min_loan_amount !== null || $product->max_loan_amount !== null)
                                        {{ $product->min_loan_amount !== null ? number_format((float) $product->min_loan_amount, 2) : '0.00' }}
                                        —
                                        {{ $product->max_loan_amount !== null ? number_format((float) $product->max_loan_amount, 2) : 'No cap' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                                    @if ($product->penalty_amount !== null || $product->exempt_from_checkoffs)
                                        @php($penaltyType = (string) ($product->penalty_amount_type ?? 'fixed'))
                                        <div>{{ $product->arrears_penalty_scope ? str_replace('_', ' ', $product->arrears_penalty_scope) : 'Penalty' }}: {{ $product->penalty_amount !== null ? ($penaltyType === 'percent' ? number_format((float) $product->penalty_amount, 4).'%' : number_format((float) $product->penalty_amount, 2)) : '—' }}</div>
                                        @if ($product->rollover_fees !== null)
                                            @php($rolloverType = (string) ($product->rollover_fees_type ?? 'fixed'))
                                            <div>Rollover: {{ $rolloverType === 'percent' ? number_format((float) $product->rollover_fees, 4).'%' : number_format((float) $product->rollover_fees, 2) }}</div>
                                        @endif
                                        @if ($product->loan_offset_fees !== null)
                                            @php($offsetType = (string) ($product->loan_offset_fees_type ?? 'fixed'))
                                            <div>Loan offset: {{ $offsetType === 'percent' ? number_format((float) $product->loan_offset_fees, 4).'%' : number_format((float) $product->loan_offset_fees, 2) }}</div>
                                        @endif
                                        <div class="text-slate-500">Checkoff exempt: {{ $product->exempt_from_checkoffs ? 'Yes' : 'No' }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600">
                                    @if (($hasProductCharges ?? false) && $product->charges->isNotEmpty())
                                        <div class="space-y-1">
                                            @foreach ($product->charges as $charge)
                                                <div>
                                                    <span class="font-semibold text-slate-700">{{ $charge->charge_name }}</span>
                                                    <span class="text-slate-500">
                                                        —
                                                        {{ $charge->amount_type === 'percent' ? number_format((float) $charge->amount, 4).'%' : number_format((float) $charge->amount, 2) }}
                                                        · {{ str_replace('_', ' ', $charge->applies_to_stage) }}
                                                        · {{ str_replace('_', ' ', $charge->applies_to_client_scope) }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $product->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="js-edit-product rounded border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                                        data-product-id="{{ $product->id }}"
                                    >
                                        Edit
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <form method="post" action="{{ route('loan.system.setup.loan_products.destroy', $product) }}" class="inline" data-swal-confirm="Remove this loan product?">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="text-red-600 font-medium text-xs hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-5 py-12 text-center text-slate-500">No loan products defined yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="product-edit-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-6xl max-h-[95vh] overflow-y-auto rounded-xl border border-slate-200 bg-white p-0 shadow-xl">
                <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800">Edit loan product</h3>
                    <button id="close-edit-product-modal" type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700">✕</button>
                </div>
                <form id="product-edit-form" method="post" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-8 js-product-update-form">
                @csrf
                @method('patch')
                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Product name</label>
                    <input id="edit_name" name="name" required class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Loan duration</label>
                    <input id="edit_default_term_months" name="default_term_months" type="number" min="1" max="600" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Installment pattern</label>
                    <select id="edit_default_term_unit" name="default_term_unit" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest value</label>
                    <input id="edit_default_interest_rate" name="default_interest_rate" type="number" step="0.0001" min="0" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest type</label>
                    <select id="edit_default_interest_rate_type" name="default_interest_rate_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="percent">Percentage (%)</option>
                        <option value="fixed">Fixed amount</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest period</label>
                    <select id="edit_default_interest_rate_period" name="default_interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="daily">Per day</option>
                        <option value="weekly">Per week</option>
                        <option value="monthly">Per month</option>
                        <option value="annual">Per year</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Active</label>
                    <select id="edit_is_active" name="is_active" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Payment interval (days)</label>
                    <input id="edit_payment_interval_days" name="payment_interval_days" type="number" min="1" max="365" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Total interest (Percentage % or Flat figure)</label>
                    <input id="edit_total_interest_amount" name="total_interest_amount" type="text" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" placeholder="e.g. 15 or 15%" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest duration</label>
                    <input id="edit_interest_duration_value" name="interest_duration_value" type="number" min="1" max="600" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Loan math type</label>
                    <select id="edit_interest_type" name="interest_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select type</option>
                        <option value="flat_rate">Flat rate</option>
                        <option value="reducing_balance">Reducing balance</option>
                        <option value="amortized">Amortized</option>
                        <option value="simple_interest">Simple interest</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Min loan amount</label>
                    <input id="edit_min_loan_amount" name="min_loan_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Max amount</label>
                    <input id="edit_max_loan_amount" name="max_loan_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Arrears penalty</label>
                    <select id="edit_arrears_penalty_scope" name="arrears_penalty_scope" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">None</option>
                        <option value="whole_loan">For whole loan</option>
                        <option value="per_installment">Per installment</option>
                        <option value="none">No penalty</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Penalty amount</label>
                    <input id="edit_penalty_amount" name="penalty_amount" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Penalty type</label>
                    <select id="edit_penalty_amount_type" name="penalty_amount_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Rollover fees</label>
                    <input id="edit_rollover_fees" name="rollover_fees" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Rollover fee type</label>
                    <select id="edit_rollover_fees_type" name="rollover_fees_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Loan offset fees</label>
                    <input id="edit_loan_offset_fees" name="loan_offset_fees" type="number" min="0" step="0.01" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Loan offset fee type</label>
                    <select id="edit_loan_offset_fees_type" name="loan_offset_fees_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="fixed">Fixed amount</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Repay waiver days</label>
                    <input id="edit_repay_waiver_days" name="repay_waiver_days" type="number" min="0" max="365" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Clients apply with</label>
                    <select id="edit_client_application_scope" name="client_application_scope" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any client</option>
                        <option value="no_running_loans">No running loans</option>
                        <option value="new_clients_only">New clients only</option>
                        <option value="existing_clients_only">Existing clients only</option>
                        <option value="any_client">All clients</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Installment display</label>
                    <select id="edit_installment_display_mode" name="installment_display_mode" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Default</option>
                        <option value="all_installments">All installments</option>
                        <option value="due_only">Due only</option>
                        <option value="summary">Summary</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Exempt from checkoffs</label>
                    <select id="edit_exempt_from_checkoffs" name="exempt_from_checkoffs" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Cluster name</label>
                    <input id="edit_cluster_name" name="cluster_name" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="md:col-span-8">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description (optional)</label>
                    <input id="edit_description" name="description" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>

                <div class="md:col-span-8 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-700">Product charges</p>
                        <button
                            type="button"
                            id="edit-add-charge-row"
                            class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                        >
                            + Add charge
                        </button>
                    </div>
                    <div id="edit-charge-rows"></div>
                    <template id="edit-charge-row-template">
                        <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-12 js-edit-charge-row">
                            <input data-field="name" class="md:col-span-3 rounded-lg border-slate-200 px-2 py-1.5 text-xs" placeholder="Charge name (e.g., Processing fee)" />
                            <select data-field="applies_to_stage" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="installment">Every installment</option>
                                <option value="application">Loan application</option>
                                <option value="repeat_application">Repeat application</option>
                                <option value="certain_installments">Certain installments</option>
                                <option value="loan_deduction">Loan deduction</option>
                                <option value="added_to_loan">Added to loan</option>
                            </select>
                            <select data-field="applies_to_client_scope" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="all">All clients</option>
                                <option value="new_clients">New clients</option>
                                <option value="existing_clients">Existing clients</option>
                                <option value="checkoff_only">Checkoff only</option>
                                <option value="non_checkoff">Non-checkoff</option>
                            </select>
                            <select data-field="type" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="fixed">Fixed amount</option>
                                <option value="percent">Percentage</option>
                            </select>
                            <input data-field="amount" type="number" min="0" step="0.0001" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs tabular-nums" placeholder="Amount or %" />
                            <button type="button" class="md:col-span-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 js-remove-edit-charge">Remove</button>
                        </div>
                    </template>
                    <p class="text-[11px] text-slate-500">Charges are captured for setup consistency and can be mapped to pricing rules.</p>
                </div>

                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Repricing effective date (optional)</label>
                    <input id="edit_repricing_effective_date" name="repricing_effective_date" type="date" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Repricing note (optional)</label>
                    <input id="edit_repricing_note" name="repricing_note" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <label class="md:col-span-8 inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <input id="edit_apply_to_existing_active_loans" type="checkbox" name="apply_to_existing_active_loans" value="1" class="rounded border-amber-300 text-amber-700 focus:ring-amber-500 js-apply-existing" />
                    Apply rate to existing active loans
                </label>

                <div class="md:col-span-8 flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" id="cancel-edit-product-modal" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">Update product</button>
                </div>
            </form>
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>
<script>
    const productModalPayload = @json($productModalPayload);
    const updateActionTemplate = @json($updateActionTemplate);
    const editModal = document.getElementById('product-edit-modal');
    const editForm = document.getElementById('product-edit-form');
    const closeEditBtn = document.getElementById('close-edit-product-modal');
    const cancelEditBtn = document.getElementById('cancel-edit-product-modal');
    const editChargeRows = document.getElementById('edit-charge-rows');
    const editChargeRowTemplate = document.getElementById('edit-charge-row-template');
    const addEditChargeBtn = document.getElementById('edit-add-charge-row');
    const openEditModal = () => {
        editModal?.classList.remove('hidden');
        editModal?.classList.add('flex');
    };
    const closeEditModal = () => {
        editModal?.classList.add('hidden');
        editModal?.classList.remove('flex');
    };
    const confirmRemoveChargeRow = async () => {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            const result = await window.Swal.fire({
                icon: 'warning',
                title: 'Remove this charge row?',
                text: 'This only removes it from the current edit form until you save.',
                showCancelButton: true,
                confirmButtonText: 'Yes, remove',
                cancelButtonText: 'Cancel',
            });
            return Boolean(result.isConfirmed);
        }

        return true;
    };

    const reindexEditChargeRows = () => {
        editChargeRows?.querySelectorAll('.js-edit-charge-row').forEach((row, index) => {
            row.querySelector('[data-field="name"]').name = `charges[${index}][name]`;
            row.querySelector('[data-field="applies_to_stage"]').name = `charges[${index}][applies_to_stage]`;
            row.querySelector('[data-field="applies_to_client_scope"]').name = `charges[${index}][applies_to_client_scope]`;
            row.querySelector('[data-field="type"]').name = `charges[${index}][type]`;
            row.querySelector('[data-field="amount"]').name = `charges[${index}][amount]`;
        });
    };

    const addEditChargeRow = (charge = {}) => {
        if (!editChargeRows || !editChargeRowTemplate) {
            return;
        }

        const fragment = editChargeRowTemplate.content.cloneNode(true);
        const row = fragment.querySelector('.js-edit-charge-row');
        if (!row) {
            return;
        }

        row.querySelector('[data-field="name"]').value = charge.name ?? '';
        row.querySelector('[data-field="applies_to_stage"]').value = charge.applies_to_stage ?? 'installment';
        row.querySelector('[data-field="applies_to_client_scope"]').value = charge.applies_to_client_scope ?? 'all';
        row.querySelector('[data-field="type"]').value = charge.type ?? 'fixed';
        row.querySelector('[data-field="amount"]').value = charge.amount ?? '';
        row.querySelector('.js-remove-edit-charge')?.addEventListener('click', async () => {
            const shouldRemove = await confirmRemoveChargeRow();
            if (!shouldRemove) {
                return;
            }
            row.remove();
            reindexEditChargeRows();
        });

        editChargeRows.appendChild(fragment);
        reindexEditChargeRows();
    };

    document.querySelectorAll('.js-edit-product').forEach((btn) => {
        btn.addEventListener('click', () => {
            const productId = String(btn.dataset.productId || '');
            const product = productModalPayload[productId];
            if (!product || !editForm || !editModal) return;

            editForm.action = String(updateActionTemplate).replace('__PRODUCT_ID__', productId);
            editForm.dataset.productName = product.name || 'this product';
            editForm.dataset.activeLoans = String(product.active_loans || 0);
            editForm.querySelector('#edit_name').value = product.name ?? '';
            editForm.querySelector('#edit_description').value = product.description ?? '';
            editForm.querySelector('#edit_default_interest_rate').value = product.default_interest_rate ?? '';
            editForm.querySelector('#edit_default_interest_rate_type').value = product.default_interest_rate_type ?? 'percent';
            editForm.querySelector('#edit_default_term_months').value = product.default_term_months ?? '';
            editForm.querySelector('#edit_default_interest_rate_period').value = product.default_interest_rate_period ?? 'annual';
            editForm.querySelector('#edit_default_term_unit').value = product.default_term_unit ?? 'monthly';
            editForm.querySelector('#edit_is_active').value = product.is_active ? '1' : '0';
            editForm.querySelector('#edit_payment_interval_days').value = product.payment_interval_days ?? '';
            editForm.querySelector('#edit_total_interest_amount').value = product.total_interest_amount ?? '';
            editForm.querySelector('#edit_interest_duration_value').value = product.interest_duration_value ?? '';
            editForm.querySelector('#edit_interest_type').value = product.interest_type ?? '';
            editForm.querySelector('#edit_min_loan_amount').value = product.min_loan_amount ?? '';
            editForm.querySelector('#edit_max_loan_amount').value = product.max_loan_amount ?? '';
            editForm.querySelector('#edit_arrears_penalty_scope').value = product.arrears_penalty_scope ?? '';
            editForm.querySelector('#edit_penalty_amount').value = product.penalty_amount ?? '';
            editForm.querySelector('#edit_penalty_amount_type').value = product.penalty_amount_type ?? 'fixed';
            editForm.querySelector('#edit_rollover_fees').value = product.rollover_fees ?? '';
            editForm.querySelector('#edit_rollover_fees_type').value = product.rollover_fees_type ?? 'fixed';
            editForm.querySelector('#edit_loan_offset_fees').value = product.loan_offset_fees ?? '';
            editForm.querySelector('#edit_loan_offset_fees_type').value = product.loan_offset_fees_type ?? 'fixed';
            editForm.querySelector('#edit_repay_waiver_days').value = product.repay_waiver_days ?? '';
            editForm.querySelector('#edit_client_application_scope').value = product.client_application_scope ?? '';
            editForm.querySelector('#edit_installment_display_mode').value = product.installment_display_mode ?? '';
            editForm.querySelector('#edit_exempt_from_checkoffs').value = product.exempt_from_checkoffs ? '1' : '0';
            editForm.querySelector('#edit_cluster_name').value = product.cluster_name ?? '';
            editForm.querySelector('#edit_repricing_effective_date').value = '';
            editForm.querySelector('#edit_repricing_note').value = '';
            editForm.querySelector('#edit_apply_to_existing_active_loans').checked = false;
            if (editChargeRows) {
                editChargeRows.innerHTML = '';
                const charges = Array.isArray(product.charges) && product.charges.length > 0
                    ? product.charges
                    : [{name: '', type: 'fixed', amount: '', applies_to_stage: 'installment', applies_to_client_scope: 'all'}];
                charges.forEach((charge) => addEditChargeRow(charge));
            }
            openEditModal();
        });
    });
    addEditChargeBtn?.addEventListener('click', () => {
        addEditChargeRow();
    });

    [closeEditBtn, cancelEditBtn].forEach((el) => {
        el?.addEventListener('click', closeEditModal);
    });
    editModal?.addEventListener('click', (event) => {
        if (event.target === editModal) {
            closeEditModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
            closeEditModal();
        }
    });

    document.querySelectorAll('.js-product-update-form').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            const applyCheckbox = form.querySelector('.js-apply-existing');
            if (!applyCheckbox?.checked) {
                return;
            }

            event.preventDefault();
            const productName = form.dataset.productName || 'this product';
            const activeLoans = Number(form.dataset.activeLoans || '0');
            const message = `This will reprice ${activeLoans} active/restructured loan(s) for "${productName}". Continue?`;

            if (window.Swal && typeof window.Swal.fire === 'function') {
                const result = await window.Swal.fire({
                    title: 'Apply rate update?',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, apply',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    confirmButtonColor: '#b45309',
                });

                if (result.isConfirmed) {
                    form.submit();
                    return;
                }

                return;
            }

            form.submit();
            return;
        });
    });
</script>
