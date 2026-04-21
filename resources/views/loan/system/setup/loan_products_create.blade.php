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
