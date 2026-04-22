<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <a href="{{ route('loan.system.setup.loan_products') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">Back to products</a>
        </x-slot>

        <div class="mx-auto w-full max-w-4xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-800">Create product</h2>
                    <p class="text-xs text-slate-500">Quick setup panel for new loan product defaults.</p>
                </div>
                <a href="{{ route('loan.system.setup.loan_products') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Close
                </a>
            </div>

            <form method="post" action="{{ route('loan.system.setup.loan_products.store') }}" class="grid grid-cols-1 gap-3 p-5 md:grid-cols-8" x-data="{ charges: [{name: '', type: 'fixed', amount: ''}] }">
                @csrf
                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Product name</label>
                    <input name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Default interest %</label>
                    <input name="default_interest_rate" type="number" step="0.0001" min="0" max="100" value="{{ old('default_interest_rate') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Default term length</label>
                    <input name="default_term_months" type="number" min="1" max="600" value="{{ old('default_term_months') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Term unit</label>
                    <select name="default_term_unit" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="daily" @selected(old('default_term_unit') === 'daily')>Daily</option>
                        <option value="weekly" @selected(old('default_term_unit') === 'weekly')>Weekly</option>
                        <option value="monthly" @selected(old('default_term_unit', 'monthly') === 'monthly')>Monthly</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest period</label>
                    <select name="default_interest_rate_period" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="daily" @selected(old('default_interest_rate_period') === 'daily')>Per day</option>
                        <option value="weekly" @selected(old('default_interest_rate_period') === 'weekly')>Per week</option>
                        <option value="monthly" @selected(old('default_interest_rate_period') === 'monthly')>Per month</option>
                        <option value="annual" @selected(old('default_interest_rate_period', 'annual') === 'annual')>Per year</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Active</label>
                    <select name="is_active" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="1" @selected(old('is_active', '1') === '1')>Yes</option>
                        <option value="0" @selected(old('is_active') === '0')>No</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Payment interval (days)</label>
                    <input name="payment_interval_days" type="number" min="1" max="365" value="{{ old('payment_interval_days') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Total interest</label>
                    <input name="total_interest_amount" type="number" min="0" step="0.01" value="{{ old('total_interest_amount') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest duration</label>
                    <input name="interest_duration_value" type="number" min="1" max="600" value="{{ old('interest_duration_value') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Interest type</label>
                    <select name="interest_type" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Select type</option>
                        <option value="flat_rate" @selected(old('interest_type', 'flat_rate') === 'flat_rate')>Flat rate</option>
                        <option value="reducing_balance" @selected(old('interest_type') === 'reducing_balance')>Reducing balance</option>
                        <option value="amortized" @selected(old('interest_type') === 'amortized')>Amortized</option>
                        <option value="simple_interest" @selected(old('interest_type') === 'simple_interest')>Simple interest</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Min loan amount</label>
                    <input name="min_loan_amount" type="number" min="0" step="0.01" value="{{ old('min_loan_amount') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Max amount</label>
                    <input name="max_loan_amount" type="number" min="0" step="0.01" value="{{ old('max_loan_amount') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Arrears penalty</label>
                    <select name="arrears_penalty_scope" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">None</option>
                        <option value="whole_loan" @selected(old('arrears_penalty_scope', 'whole_loan') === 'whole_loan')>For whole loan</option>
                        <option value="per_installment" @selected(old('arrears_penalty_scope') === 'per_installment')>Per installment</option>
                        <option value="none" @selected(old('arrears_penalty_scope') === 'none')>No penalty</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Penalty amount</label>
                    <input name="penalty_amount" type="number" min="0" step="0.01" value="{{ old('penalty_amount') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Rollover fees</label>
                    <input name="rollover_fees" type="number" min="0" step="0.01" value="{{ old('rollover_fees', '0') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Loan offset fees</label>
                    <input name="loan_offset_fees" type="number" min="0" step="0.01" value="{{ old('loan_offset_fees', '0') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Repay waiver days</label>
                    <input name="repay_waiver_days" type="number" min="0" max="365" value="{{ old('repay_waiver_days', '0') }}" class="w-full rounded-lg border-slate-200 text-sm tabular-nums" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Clients apply with</label>
                    <select name="client_application_scope" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Any client</option>
                        <option value="no_running_loans" @selected(old('client_application_scope', 'no_running_loans') === 'no_running_loans')>No running loans</option>
                        <option value="new_clients_only" @selected(old('client_application_scope') === 'new_clients_only')>New clients only</option>
                        <option value="existing_clients_only" @selected(old('client_application_scope') === 'existing_clients_only')>Existing clients only</option>
                        <option value="any_client" @selected(old('client_application_scope') === 'any_client')>All clients</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Installment display</label>
                    <select name="installment_display_mode" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="">Default</option>
                        <option value="all_installments" @selected(old('installment_display_mode', 'all_installments') === 'all_installments')>All installments</option>
                        <option value="due_only" @selected(old('installment_display_mode') === 'due_only')>Due only</option>
                        <option value="summary" @selected(old('installment_display_mode') === 'summary')>Summary</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Exempt from checkoffs</label>
                    <select name="exempt_from_checkoffs" class="w-full rounded-lg border-slate-200 text-sm">
                        <option value="0" @selected(old('exempt_from_checkoffs', '0') === '0')>No</option>
                        <option value="1" @selected(old('exempt_from_checkoffs') === '1')>Yes</option>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Cluster name</label>
                    <input name="cluster_name" value="{{ old('cluster_name') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>
                <div class="md:col-span-8">
                    <label class="mb-1 block text-xs font-semibold text-slate-600">Description (optional)</label>
                    <input name="description" value="{{ old('description') }}" class="w-full rounded-lg border-slate-200 text-sm" />
                </div>

                <div class="md:col-span-8 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-slate-700">Product charges</p>
                        <button
                            type="button"
                            @click="charges.push({name: '', type: 'fixed', amount: ''})"
                            class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                        >
                            + Add charge
                        </button>
                    </div>

                    <template x-for="(charge, index) in charges" :key="index">
                        <div class="mb-2 grid grid-cols-1 gap-2 md:grid-cols-12">
                            <input
                                :name="`charges[${index}][name]`"
                                x-model="charge.name"
                                class="md:col-span-3 rounded-lg border-slate-200 px-2 py-1.5 text-xs"
                                placeholder="Charge name (e.g., Processing fee)"
                            />
                            <select :name="`charges[${index}][applies_to_stage]`" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="application">On application</option>
                                <option value="loan" selected>On loan booking</option>
                                <option value="disbursement">On disbursement</option>
                                <option value="installment">Per installment</option>
                            </select>
                            <select :name="`charges[${index}][applies_to_client_scope]`" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="all">All clients</option>
                                <option value="new_clients">New clients</option>
                                <option value="existing_clients">Existing clients</option>
                                <option value="checkoff_only">Checkoff only</option>
                                <option value="non_checkoff">Non-checkoff</option>
                            </select>
                            <select :name="`charges[${index}][type]`" x-model="charge.type" class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs">
                                <option value="fixed">Fixed amount</option>
                                <option value="percent">Percentage</option>
                            </select>
                            <input
                                :name="`charges[${index}][amount]`"
                                x-model="charge.amount"
                                type="number"
                                min="0"
                                step="0.0001"
                                class="md:col-span-2 rounded-lg border-slate-200 px-2 py-1.5 text-xs tabular-nums"
                                placeholder="Amount"
                            />
                            <button
                                type="button"
                                @click="charges.splice(index, 1)"
                                class="md:col-span-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                            >
                                Remove
                            </button>
                        </div>
                    </template>
                    <p class="text-[11px] text-slate-500">Charges are captured for setup consistency and can be mapped to pricing rules.</p>
                </div>

                <div class="md:col-span-8 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
                    <a href="{{ route('loan.system.setup.loan_products') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center rounded-lg bg-[#2f4f4f] px-4 py-2 text-sm font-semibold text-white hover:bg-[#264040]">
                        Save product
                    </button>
                </div>
            </form>
        </div>
    </x-loan.page>
</x-loan-layout>
