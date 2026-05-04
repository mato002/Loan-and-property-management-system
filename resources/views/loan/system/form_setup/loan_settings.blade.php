<x-loan-layout>
    <x-loan.page :title="$title" :subtitle="$subtitle">
        <x-slot name="actions">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('loan.system.form_setup.page', ['page' => 'loan-settings', 'export' => 1]) }}" class="inline-flex items-center rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                    Export Policy
                </a>
                <a href="{{ route('loan.system.access_logs.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    View Audit Trail
                </a>
                @if ((string) request('tab', 'product-rules') === 'product-rules')
                    <button form="loan-settings-primary-form" type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Save Changes
                    </button>
                @elseif ((string) request('tab', '') === 'eligibility-affordability')
                    <button form="loan-settings-eligibility-form" type="submit" class="inline-flex items-center rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50">
                        Save eligibility
                    </button>
                    <button form="loan-settings-affordability-form" type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Save affordability
                    </button>
                    <button form="loan-settings-graduation-eligibility-form" type="submit" class="inline-flex items-center rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm font-semibold text-violet-900 hover:bg-violet-50">
                        Save graduation
                    </button>
                @endif
            </div>
        </x-slot>

        @include('loan.accounting.partials.flash')

        @php
            $tab = (string) request('tab', 'product-rules');
            $loanDisbursedEvent = config('accounting_events.events.LoanDisbursed', []);
            $disbursementSlotDebit = data_get($loanDisbursedEvent, 'debit_slot', 'loan_portfolio_performing_account');
            $disbursementSlotCredit = data_get($loanDisbursedEvent, 'credit_slot', 'disbursement_cash_account');
            $engineMaxRatio = \App\Models\LoanSystemSetting::getValue('max_installment_to_income_ratio', '0.35');
            $engineFirstLimit = \App\Models\LoanSystemSetting::getValue('first_loan_limit', '');
            $engineSecondLimit = \App\Models\LoanSystemSetting::getValue('second_loan_limit', '');
            $engineGradPct = \App\Models\LoanSystemSetting::getValue('graduation_increase_percentage', '');
            $engineBlockWo = \App\Models\LoanSystemSetting::getValue('block_if_written_off_history', '0') === '1';
            $engineAllowTopUp = \App\Models\LoanSystemSetting::getValue('allow_top_up_if_active_loan', '0') === '1';
            $engineMinRepay = \App\Models\LoanSystemSetting::getValue('min_repayment_success_rate', '0.6');
            $engineMaxDpd = \App\Models\LoanSystemSetting::getValue('max_allowed_dpd_for_repeat', '5');
            $engineAffordabilityEnabled = \App\Models\LoanSystemSetting::getValue('affordability_engine_enabled', '1') !== '0';
            $engineMaxIndebtedness = \App\Models\LoanSystemSetting::getValue('max_total_indebtedness_to_income_ratio', '0');
            $engineMaxGuarantorExposure = \App\Models\LoanSystemSetting::getValue('max_combined_guarantor_exposure_ratio', '0');
            $approvalRowsCount = count($requiredApprovals);
            $eligActive = 0;
            foreach (['minimum_age', 'maximum_age', 'active_loan_limit', 'minimum_repayment_history', 'minimum_client_score'] as $k) {
                if (($eligibilityRules[$k] ?? null) !== null && (string) ($eligibilityRules[$k] ?? '') !== '') {
                    $eligActive++;
                }
            }
            $blockedConds = (int) (($eligibilityRules['block_with_arrears'] ?? false) || ($eligibilityRules['block_written_off_history'] ?? false));
            $overrideHints = (int) (($additionalProductSettings['auto_approval_low_risk'] ?? false) || ($graduationRules['increase_after_full_payment_only'] ?? false));
            $ov = $loanSettingsOverview ?? [];
            $tier = $ov['approval_tier_counts'] ?? ['standard' => 0, 'manager' => 0, 'director' => 0];
            $loanDisbursedMapping = is_array($ov['loan_disbursed_row'] ?? null) ? $ov['loan_disbursed_row'] : null;
            $chartFloor = (float) ($ov['liquidity_floor_chart'] ?? 0);
            $policyFloor = isset($disbursementControls['policy_liquidity_floor']) && $disbursementControls['policy_liquidity_floor'] !== null
                ? (float) $disbursementControls['policy_liquidity_floor']
                : null;
            $effectiveFloor = max($chartFloor, $policyFloor ?? 0);
        @endphp

        <div class="space-y-5 bg-slate-50 p-1">
            @if ($tab === 'product-rules')
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <a href="{{ route('loan.system.setup.loan_products') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-sm ring-slate-200 transition hover:border-teal-300 hover:ring-1 hover:ring-teal-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        <p class="text-xs font-semibold text-slate-500">Active Loan Products</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ (int) $activeProductsCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-teal-700 opacity-0 transition group-hover:opacity-100">Open loan products →</p>
                    </a>
                    <a href="{{ route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => 'approval-matrix']) }}#required-approvals" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-sm ring-slate-200 transition hover:border-teal-300 hover:ring-1 hover:ring-teal-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        <p class="text-xs font-semibold text-slate-500">Approval Rules Active</p>
                        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $approvalRowsCount }}</p>
                        <p class="mt-2 text-xs font-semibold text-teal-700 opacity-0 transition group-hover:opacity-100">Edit approval matrix →</p>
                    </a>
                    <a href="{{ route('loan.book.collections_reports') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-sm ring-slate-200 transition hover:border-teal-300 hover:ring-1 hover:ring-teal-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        <p class="text-xs font-semibold text-slate-500">Lending Brake Status</p>
                        <p class="mt-2 text-sm font-semibold {{ ($ov['liquidity_status'] ?? '') === 'AT RISK' ? 'text-rose-700' : 'text-emerald-700' }}">{{ $ov['lending_brake_label'] ?? '—' }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $ov['lending_brake_hint'] ?? '' }}</p>
                        <p class="mt-2 text-xs font-semibold text-teal-700 opacity-0 transition group-hover:opacity-100">Collections command center →</p>
                    </a>
                    <a href="{{ route('loan.book.applications.index') }}" class="group block rounded-xl border border-slate-200 bg-white p-4 shadow-sm ring-slate-200 transition hover:border-teal-300 hover:ring-1 hover:ring-teal-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        <p class="text-xs font-semibold text-slate-500">High-Risk Overrides Pending</p>
                        <p class="mt-2 text-2xl font-bold text-rose-700">{{ (int) ($ov['high_risk_applications'] ?? 0) }}</p>
                        <p class="mt-1 text-xs text-slate-500">Pipeline: submitted / credit review with risky or blocked classification.</p>
                        <p class="mt-2 text-xs font-semibold text-teal-700 opacity-0 transition group-hover:opacity-100">Open applications →</p>
                    </a>
                </div>
            @else
                <div class="rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-violet-50 p-4 md:flex md:items-center md:justify-between md:gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Loan policy</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">
                            @switch ($tab)
                                @case('approval-matrix')
                                    Approval matrix & governance
                                    @break
                                @case('disbursement-controls')
                                    Disbursement controls
                                    @break
                                @case('eligibility-affordability')
                                    Eligibility & affordability
                                    @break
                                @case('portfolio-limits')
                                    Portfolio limits & graduation
                                    @break
                                @default
                                    Lending controls
                            @endswitch
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">Define who may approve, when funds may leave, and how clients qualify. Enforcement hooks will consume these policies in a later release.</p>
                    </div>
                    <span class="mt-3 inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-800 ring-1 ring-indigo-200 md:mt-0">Tab: {{ str_replace('-', ' ', $tab) }}</span>
                </div>
            @endif

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                <div class="flex min-w-max gap-2">
                    @foreach ([
                        'product-rules' => 'Product Rules',
                        'approval-matrix' => 'Approval Matrix',
                        'disbursement-controls' => 'Disbursement Controls',
                        'eligibility-affordability' => 'Eligibility & Affordability',
                        'portfolio-limits' => 'Portfolio Limits',
                        'staff-loans' => 'Staff Loans',
                        'dormancy-reactivation' => 'Dormancy & Reactivation',
                        'overrides-security' => 'Overrides & Security',
                        'audit-trail' => 'Audit Trail',
                    ] as $key => $label)
                        <a href="{{ route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => $key]) }}" class="rounded-lg border px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-teal-800 bg-teal-800 text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="space-y-5">
                @if ($tab === 'product-rules')
                    @php
                        $loanFormSetupBoot = [
                            'fieldsPayload' => $fieldsPayload ?? [],
                            'editorScope' => $loanFormEditorScope ?? 'global',
                            'editorProductId' => $loanFormEditorProductId,
                            'payloadUrl' => route('loan.system.form_setup.loan_form_editor_payload'),
                            'applicationsCreateUrl' => route('loan.book.applications.create'),
                            'products' => $products->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name])->values()->all(),
                        ];
                    @endphp
                    <script type="application/json" id="loan-form-setup-boot">@json($loanFormSetupBoot)</script>
                    <div
                        id="loan-form-setup"
                        class="rounded-xl border border-slate-200 bg-white p-5"
                        x-data="loanSettingsLoanFormStateFromBoot()"
                    >
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">A. Loan Form Setup</h3>
                                <p class="text-xs text-slate-500">Controls the fields shown during loan application booking.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="openPreviewModal()" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Preview Form</button>
                                <button type="button" @click="openCloneModal()" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clone Form</button>
                            </div>
                        </div>

                        <div x-show="previewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="closePreviewModal()">
                            <div class="max-h-[85vh] w-full max-w-3xl overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" @click.outside="closePreviewModal()">
                                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Booking form preview</p>
                                        <p class="text-xs text-slate-500">From the table below (including unsaved edits). “On booking” follows the same rules as LoanBook create application for active vs product scope.</p>
                                    </div>
                                    <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50" @click="closePreviewModal()">Close</button>
                                </div>
                                <div class="max-h-[calc(85vh-8rem)] overflow-auto p-4">
                                    <p class="mb-3 text-xs text-slate-600">Image and document types are stored in policy but are not shown on the current internal booking screen for dynamic text fields.</p>
                                    <table class="min-w-full text-xs">
                                        <thead class="bg-slate-50 text-slate-600">
                                            <tr>
                                                <th class="px-2 py-2 text-left">Field</th>
                                                <th class="px-2 py-2 text-left">Key</th>
                                                <th class="px-2 py-2 text-left">Type</th>
                                                <th class="px-2 py-2 text-left">Required</th>
                                                <th class="px-2 py-2 text-left">On booking (est.)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(pr, idx) in previewTableRows()" :key="pr.field_key + '-' + idx">
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-2 py-2 font-medium text-slate-800" x-text="pr.label"></td>
                                                    <td class="px-2 py-2 font-mono text-slate-600" x-text="pr.field_key"></td>
                                                    <td class="px-2 py-2 text-slate-600" x-text="pr.data_type"></td>
                                                    <td class="px-2 py-2" x-text="pr.is_required ? 'Yes' : 'No'"></td>
                                                    <td class="px-2 py-2">
                                                        <span class="rounded px-1.5 py-0.5 font-semibold" :class="pr.on_booking ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'" x-text="pr.on_booking ? 'Yes' : 'No'"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
                                    <a :href="applicationsCreateUrl" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Open Create application</a>
                                    <button type="button" class="inline-flex rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700" @click="closePreviewModal()">Done</button>
                                </div>
                            </div>
                        </div>

                        <div x-show="cloneModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="closeCloneModal()">
                            <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-xl" @click.outside="closeCloneModal()">
                                <p class="text-sm font-semibold text-slate-900">Clone form checklist</p>
                                <p x-show="cloneMode === 'global'" x-cloak class="mt-2 text-xs leading-relaxed text-slate-600">Select a <strong>loan product</strong> in “Product selector”, then return here. Clone copies <strong>include / require / order / visibility</strong> from another product’s <strong>saved</strong> setup into the product you are editing (apply, then Save).</p>
                                <div x-show="cloneMode === 'product'" x-cloak class="mt-3 space-y-3">
                                    <p class="text-xs text-slate-600">Load field rules from another product into <strong>this</strong> editor. Unsaved rows above are updated; click Save to persist.</p>
                                    <p x-show="cloneMode === 'product' && cloneSourceOptions().length === 0" class="rounded-lg bg-amber-50 px-2 py-1.5 text-xs font-medium text-amber-900 ring-1 ring-amber-200">Add at least one other loan product to copy a checklist from.</p>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600">Copy from product</label>
                                        <select x-model="cloneSourceProductId" class="mt-1 w-full rounded-lg border-slate-200 text-sm">
                                            <option value="">Select…</option>
                                            <template x-for="p in cloneSourceOptions()" :key="'src-'+p.id">
                                                <option :value="String(p.id)" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <p x-show="cloneError" x-text="cloneError" class="text-xs font-semibold text-rose-600"></p>
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" @click="closeCloneModal()" :disabled="cloneBusy">Cancel</button>
                                    <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60" x-show="cloneMode === 'product' && cloneSourceOptions().length > 0" @click="applyCloneFromSelectedProduct()" :disabled="cloneBusy">
                                        <span x-show="!cloneBusy">Apply copy</span>
                                        <span x-show="cloneBusy">Loading…</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <form id="loan-settings-primary-form" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="section" value="loan_form_setup">
                            <input type="hidden" name="loan_form_setup_editor" value="{{ $loanFormEditorScope ?? 'global' }}">
                            @if (($loanFormEditorProductId ?? null))
                                <input type="hidden" name="form_product" value="{{ $loanFormEditorProductId }}">
                            @endif
                            @if (! empty($shouldOfferPendingComplete ?? false))
                                <input type="hidden" name="complete_loan_form_setup_product_id" value="{{ $loanFormEditorProductId }}">
                            @endif

                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                                <div class="grid grid-cols-1 gap-6 md:gap-8 lg:grid-cols-2 lg:items-start lg:gap-10">
                                    <div class="min-w-0 space-y-2">
                                        <label class="block text-xs font-semibold text-slate-600">Product selector</label>
                                        <select class="w-full min-w-0 rounded-lg border-slate-200 bg-white py-2 pl-3 pr-8 text-sm shadow-sm" onchange="window.loanFormProductNav(this.value)">
                                            <option value="all" @selected(($loanFormEditorProductId ?? null) === null)>All products (master library)</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" @selected((int) ($loanFormEditorProductId ?? 0) === (int) $product->id)>{{ $product->name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="mt-2 text-xs leading-relaxed text-slate-500">
                                            @if (($loanFormEditorScope ?? 'global') === 'product')
                                                Editing <span class="font-semibold text-slate-700">product-specific</span> include/require/order. The master library is unchanged for other products.
                                            @else
                                                Editing the <span class="font-semibold text-slate-700">global master</span> field library (labels, types, default template). Pick a product to configure its checklist.
                                            @endif
                                        </p>
                                    </div>
                                    <div class="min-w-0 space-y-2 lg:border-l lg:border-slate-200 lg:pl-8 xl:pl-10">
                                        <label class="block text-xs font-semibold text-slate-600">Setup loan product for new product</label>
                                        <select class="w-full min-w-0 rounded-lg border-slate-200 bg-white py-2 pl-3 pr-8 text-sm shadow-sm" onchange="window.loanFormPendingNav(this.value)">
                                            @if ($productsPendingLoanFormSetup->isEmpty())
                                                <option value="">No new products</option>
                                            @else
                                                <option value="">Select a new loan product to configure…</option>
                                                @foreach ($productsPendingLoanFormSetup as $pendingProduct)
                                                    <option value="{{ $pendingProduct->id }}">{{ $pendingProduct->name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @if ($productsPendingLoanFormSetup->isEmpty())
                                            <p class="mt-2 text-xs leading-relaxed text-slate-500">Create a product under <a href="{{ route('loan.system.setup.loan_products') }}" class="font-semibold text-blue-700 underline decoration-blue-300 underline-offset-2 hover:text-blue-800">Loan products</a>; it appears here until you save this form.</p>
                                        @else
                                            <p class="mt-2 text-xs leading-relaxed text-slate-500">Opens that product with <span class="font-semibold">pending complete</span> so the first save can mark setup finished when appropriate.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full text-xs">
                                    <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="px-2 py-2 text-left">Include</th>
                                            <th class="px-2 py-2 text-left">Field Label</th>
                                            <th class="px-2 py-2 text-left">Field Key</th>
                                            <th class="px-2 py-2 text-left">Data Type</th>
                                            <th class="px-2 py-2 text-left">Required</th>
                                            <th class="px-2 py-2 text-left">Use Previous Data</th>
                                            <th class="px-2 py-2 text-left">Visible To / Status</th>
                                            <th class="px-2 py-2 text-left">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, index) in rows" :key="row.master_id ? ('m-'+row.master_id) : ('new-'+index)">
                                            <tr class="border-t border-slate-100">
                                                <td class="px-2 py-2 align-top">
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-slate-300"
                                                        :checked="row.is_core || row.included"
                                                        @change="toggleInclude(row, $event)"
                                                        :disabled="row.is_core"
                                                        :title="row.is_core ? 'System field (always included)' : (row.included ? 'Included for this product' : 'Not included')"
                                                    >
                                                    <input type="hidden" :name="`fields[${index}][included]`" :value="(row.is_core || row.included) ? 1 : 0">
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <div class="flex flex-wrap items-center gap-1">
                                                        <input class="w-40 rounded border-slate-200 text-xs" x-model="row.label" :name="`fields[${index}][label]`" :readonly="editorScope === 'product'" :class="editorScope === 'product' ? 'bg-slate-50' : ''" required>
                                                        <span x-show="row.is_core" class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700">System</span>
                                                    </div>
                                                </td>
                                                <td class="px-2 py-2 align-top"><input class="w-40 rounded border-slate-200 bg-slate-50 text-xs" x-model="row.field_key" readonly tabindex="-1"></td>
                                                <td class="px-2 py-2 align-top">
                                                    <select class="w-40 rounded border-slate-200 text-xs" x-model="row.data_type" :name="`fields[${index}][data_type]`" :disabled="editorScope === 'product'">
                                                        @foreach ($dataTypeLabels as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <textarea x-show="row.data_type === 'select'" x-model="row.select_options" :name="`fields[${index}][select_options]`" rows="2" class="mt-1 w-40 rounded border-slate-200 text-xs" placeholder="Option A, Option B" :disabled="editorScope === 'product'"></textarea>
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <input type="checkbox" x-model="row.is_required" class="rounded border-slate-300" :disabled="!row.is_core && !row.included">
                                                    <input type="hidden" :name="`fields[${index}][is_required]`" :value="row.is_required ? 1 : 0">
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <input type="checkbox" x-model="row.prefill_from_previous" :disabled="row.is_core || (editorScope === 'product' && !row.included)" class="rounded border-slate-300">
                                                    <input type="hidden" :name="`fields[${index}][prefill_from_previous]`" :value="row.prefill_from_previous ? 1 : 0">
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <input class="w-40 rounded border-slate-200 text-xs" x-model="row.visible_to" :name="`fields[${index}][visible_to]`" placeholder="officer, manager" :readonly="editorScope === 'product' && !row.included && !row.is_core" :class="(editorScope === 'product' && !row.included && !row.is_core) ? 'bg-slate-50' : ''">
                                                    <select x-show="editorScope === 'global'" x-cloak class="mt-1 w-full rounded border-slate-200 text-xs" x-model="row.field_status" :disabled="row.is_core || (!row.included && !row.is_core)">
                                                        <option value="active">Active</option>
                                                        <option value="draft">Draft</option>
                                                        <option value="requires_approval">Requires Approval</option>
                                                    </select>
                                                    <select x-show="editorScope === 'product' && (row.included || row.is_core)" x-cloak class="mt-1 w-full rounded border-slate-200 text-xs" x-model="row.detail_status" :disabled="row.is_core">
                                                        <option value="active">Active</option>
                                                        <option value="requires_approval">Requires Approval</option>
                                                    </select>
                                                    <p x-show="editorScope === 'product' && !row.included && !row.is_core" x-cloak class="mt-1 text-[10px] text-slate-400">Off — enable Include to set status</p>
                                                    <input type="hidden" :name="`fields[${index}][field_status]`" :value="fieldStatusForSubmit(row)">
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <div class="flex gap-1">
                                                        <button type="button" @click="up(index)" class="rounded border border-slate-200 px-1.5 py-0.5">↑</button>
                                                        <button type="button" @click="down(index)" class="rounded border border-slate-200 px-1.5 py-0.5">↓</button>
                                                        <button type="button" @click="remove(index)" :disabled="row.is_core || editorScope === 'product'" class="rounded border border-red-200 px-1.5 py-0.5 text-red-600" title="Remove custom field (global library only)">✕</button>
                                                    </div>
                                                    <input type="hidden" :name="`fields[${index}][master_id]`" :value="row.master_id ?? ''">
                                                    <input type="hidden" :name="`fields[${index}][field_key]`" :value="row.field_key">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" x-show="editorScope === 'global'" x-cloak @click="add()" class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700">Add Custom Field</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save Loan Form Setup</button>
                            </div>
                        </form>
                    </div>
                @endif

                @if ($tab === 'approval-matrix')
                    <div id="approval-matrix-root" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Standard approvals</p>
                                <p class="mt-2 text-3xl font-bold text-violet-900">{{ (int) ($tier['standard'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-600">Bands tagged branch / standard tier (see approval tier column).</p>
                            </div>
                            <div class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Manager approvals</p>
                                <p class="mt-2 text-3xl font-bold text-indigo-900">{{ (int) ($tier['manager'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-600">Regional, credit, and mid-ticket bands.</p>
                            </div>
                            <div class="rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">Director approvals</p>
                                <p class="mt-2 text-3xl font-bold text-purple-900">{{ (int) ($tier['director'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-600">Large exposure & policy waivers.</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-gradient-to-br from-rose-50 to-white p-4 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">High-risk pending</p>
                                <p class="mt-2 text-3xl font-bold text-rose-800">{{ (int) ($ov['high_risk_applications'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-600">Applications in review with repeat_risky / blocked classification.</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-indigo-950">Maker–checker governance</h3>
                                    <p class="mt-1 text-sm text-slate-600">Saved policy flags (runtime enforcement can read these keys next).</p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">Governance</span>
                            </div>
                            <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="section" value="approval_governance">
                                <ul class="space-y-3 text-sm text-slate-700">
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-100">
                                        <span class="max-w-xl"><strong class="text-slate-900">1. Maker cannot approve own application.</strong> The officer who originated the file must not be the final approver on the same ticket.</span>
                                        <input type="hidden" name="maker_cannot_approve_own" value="0">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-800"><input type="checkbox" name="maker_cannot_approve_own" value="1" @checked(($approvalGovernance['maker_cannot_approve_own'] ?? true)) class="rounded border-slate-300 text-indigo-600"> On</label>
                                    </li>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-100">
                                        <span class="max-w-xl"><strong class="text-slate-900">2. Creator cannot disburse own approved loan.</strong> Disbursement release should use a different user than the credit approver.</span>
                                        <input type="hidden" name="creator_cannot_disburse_own" value="0">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-800"><input type="checkbox" name="creator_cannot_disburse_own" value="1" @checked(($approvalGovernance['creator_cannot_disburse_own'] ?? true)) class="rounded border-slate-300 text-indigo-600"> On</label>
                                    </li>
                                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-100">
                                        <span class="max-w-xl"><strong class="text-slate-900">3. Record approver audit trail</strong> — identity, role, timestamp, optional comment.</span>
                                        <input type="hidden" name="record_approver_audit" value="0">
                                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-violet-800"><input type="checkbox" name="record_approver_audit" value="1" @checked(($approvalGovernance['record_approver_audit'] ?? true)) class="rounded border-slate-300 text-indigo-600"> On</label>
                                    </li>
                                </ul>
                                <button type="submit" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save governance rules</button>
                            </form>
                        </div>

                        <div id="required-approvals" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-base font-semibold text-slate-900">Amount-based approval matrix</h3>
                                    <p class="text-xs text-slate-500">Bands drive minimum approver seniority. Values persist to <code class="rounded bg-slate-100 px-1">loan_settings_required_approvals</code>.</p>
                                </div>
                                <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="section" value="add_approval_band">
                                    <button type="submit" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">Add approval band</button>
                                </form>
                            </div>
                            @foreach ($requiredApprovals as $ri => $_r)
                                @if (count($requiredApprovals) > 1)
                                    <form id="remove-approval-band-{{ $ri }}" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="hidden">
                                        @csrf
                                        <input type="hidden" name="section" value="remove_approval_band">
                                        <input type="hidden" name="band_index" value="{{ $ri }}">
                                    </form>
                                @endif
                            @endforeach
                            <form id="approval-matrix-save" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="hidden">
                                @csrf
                                <input type="hidden" name="section" value="required_approvals">
                            </form>
                            <div class="space-y-6">
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50">
                                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                                <th class="whitespace-nowrap px-3 py-3">Amount range</th>
                                                <th class="whitespace-nowrap px-3 py-3">Tier</th>
                                                <th class="whitespace-nowrap px-3 py-3">Required level</th>
                                                <th class="whitespace-nowrap px-3 py-3">Role allowed</th>
                                                <th class="whitespace-nowrap px-3 py-3">Maker–checker</th>
                                                <th class="whitespace-nowrap px-3 py-3">Override</th>
                                                <th class="whitespace-nowrap px-3 py-3">Status</th>
                                                <th class="whitespace-nowrap px-3 py-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach ($requiredApprovals as $i => $row)
                                                <tr class="hover:bg-slate-50/80">
                                                    <td class="px-3 py-3 align-top">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <input form="approval-matrix-save" name="approval_rows[{{ $i }}][amount_from]" value="{{ $row['amount_from'] ?? '' }}" class="w-24 rounded-lg border-slate-200 text-sm tabular-nums" placeholder="From">
                                                            <span class="text-slate-400">–</span>
                                                            <input form="approval-matrix-save" name="approval_rows[{{ $i }}][amount_to]" value="{{ $row['amount_to'] ?? '' }}" class="w-24 rounded-lg border-slate-200 text-sm tabular-nums" placeholder="∞">
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <select form="approval-matrix-save" name="approval_rows[{{ $i }}][approval_tier]" class="w-full min-w-[7rem] rounded-lg border-slate-200 text-sm">
                                                            <option value="" @selected(($row['approval_tier'] ?? '') === '')>Auto (from role)</option>
                                                            <option value="standard" @selected(($row['approval_tier'] ?? '') === 'standard')>Standard</option>
                                                            <option value="manager" @selected(($row['approval_tier'] ?? '') === 'manager')>Manager</option>
                                                            <option value="director" @selected(($row['approval_tier'] ?? '') === 'director')>Director</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <input form="approval-matrix-save" name="approval_rows[{{ $i }}][risk_level]" value="{{ $row['risk_level'] ?? '' }}" class="w-full min-w-[8rem] rounded-lg border-slate-200 text-sm" placeholder="e.g. Standard">
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <input form="approval-matrix-save" name="approval_rows[{{ $i }}][approver]" value="{{ $row['approver'] ?? '' }}" class="w-full min-w-[10rem] rounded-lg border-slate-200 text-sm" placeholder="Role / title">
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <input form="approval-matrix-save" type="hidden" name="approval_rows[{{ $i }}][maker_checker_required]" value="0">
                                                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                            <input form="approval-matrix-save" type="checkbox" name="approval_rows[{{ $i }}][maker_checker_required]" value="1" @checked(($row['maker_checker_required'] ?? true)) class="rounded border-slate-300 text-indigo-600"> Required
                                                        </label>
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <select form="approval-matrix-save" name="approval_rows[{{ $i }}][override_allowed]" class="w-full min-w-[8rem] rounded-lg border-slate-200 text-sm">
                                                            @foreach (['no' => 'No', 'committee' => 'Committee', 'allowed' => 'Allowed', 'optional' => 'Optional'] as $overrideKey => $overrideLabel)
                                                                <option value="{{ $overrideKey }}" @selected(($row['override_allowed'] ?? 'committee') === $overrideKey)>{{ $overrideLabel }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-3 align-top">
                                                        <select form="approval-matrix-save" name="approval_rows[{{ $i }}][band_status]" class="w-full min-w-[7rem] rounded-lg border-slate-200 text-sm">
                                                            @foreach (['active' => 'Active', 'draft' => 'Draft', 'review' => 'Review', 'warning' => 'Warning', 'blocked' => 'Blocked'] as $stk => $stl)
                                                                <option value="{{ $stk }}" @selected(($row['band_status'] ?? 'active') === $stk)>{{ $stl }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="px-3 py-3 align-top text-right">
                                                        @if (count($requiredApprovals) > 1)
                                                            <button type="submit" form="remove-approval-band-{{ $i }}" class="text-xs font-semibold text-rose-600 hover:underline" onclick="return confirm('Remove this band?');">Remove</button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-xs text-slate-500">Disbursement approval flags (per row) remain in the form below for backward compatibility.</p>
                                <input form="approval-matrix-save" type="hidden" name="risk_matrix_submit" value="1">
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-slate-700">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Row</th>
                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide">Disbursement approval</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($requiredApprovals as $i => $row)
                                                <tr class="border-t border-slate-100">
                                                    <td class="px-3 py-2 text-slate-600">Band {{ $i + 1 }}</td>
                                                    <td class="px-3 py-2">
                                                        <input form="approval-matrix-save" type="hidden" name="approval_rows[{{ $i }}][disbursement_approval]" value="0">
                                                        <label class="inline-flex items-center gap-2 text-sm">
                                                            <input form="approval-matrix-save" type="checkbox" name="approval_rows[{{ $i }}][disbursement_approval]" value="1" @checked(($row['disbursement_approval'] ?? false)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                            Separate disbursement sign-off for this band
                                                        </label>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="rounded-xl border border-violet-100 bg-violet-50/40 p-5">
                                    <h4 class="text-sm font-semibold text-violet-950">Risk-based approval rules</h4>
                                    <p class="mt-1 text-xs text-slate-600">Saved to <code class="rounded bg-white px-1">loan_settings_risk_approval_matrix</code> when you save the approval matrix.</p>
                                    <div class="mt-4 overflow-x-auto rounded-lg border border-violet-100 bg-white">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-violet-50 text-left text-xs font-semibold uppercase tracking-wide text-violet-900">
                                                <tr>
                                                    <th class="px-3 py-2">Rule</th>
                                                    <th class="px-3 py-2">Required approval</th>
                                                    <th class="px-3 py-2">Can override?</th>
                                                    <th class="px-3 py-2">OTP required?</th>
                                                    <th class="px-3 py-2">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach ($riskApprovalMatrix as $j => $rr)
                                                    <tr class="bg-white">
                                                        <td class="px-3 py-2">
                                                            <input form="approval-matrix-save" type="hidden" name="risk_policy_rows[{{ $j }}][rule_key]" value="{{ $rr['rule_key'] ?? '' }}">
                                                            <input form="approval-matrix-save" name="risk_policy_rows[{{ $j }}][rule_label]" value="{{ $rr['rule_label'] ?? '' }}" class="w-full min-w-[10rem] rounded border-slate-200 text-sm font-medium text-slate-800">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input form="approval-matrix-save" name="risk_policy_rows[{{ $j }}][required_approval]" value="{{ $rr['required_approval'] ?? '' }}" class="w-full min-w-[6rem] rounded border-slate-200 text-sm">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input form="approval-matrix-save" name="risk_policy_rows[{{ $j }}][can_override]" value="{{ $rr['can_override'] ?? '' }}" class="w-full min-w-[6rem] rounded border-slate-200 text-sm text-slate-700">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input form="approval-matrix-save" name="risk_policy_rows[{{ $j }}][otp_required]" value="{{ $rr['otp_required'] ?? '' }}" class="w-full min-w-[6rem] rounded border-slate-200 text-sm text-slate-700">
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input form="approval-matrix-save" name="risk_policy_rows[{{ $j }}][status]" value="{{ $rr['status'] ?? '' }}" class="w-full min-w-[6rem] rounded border-slate-200 text-sm">
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                    <h4 class="text-sm font-semibold text-slate-900">Additional product controls</h4>
                                    <p class="text-xs text-slate-500">Saved with the approval matrix payload (existing backend).</p>
                                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-600">Arrears tolerance (days)</label>
                                            <input form="approval-matrix-save" name="arrears_tolerance_days" value="{{ $additionalProductSettings['arrears_tolerance_days'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Days">
                                        </div>
                                        <div class="flex flex-col gap-3 md:col-span-2">
                                            <input form="approval-matrix-save" type="hidden" name="penalty_on_arrears" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input form="approval-matrix-save" type="checkbox" name="penalty_on_arrears" value="1" @checked(($additionalProductSettings['penalty_on_arrears'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Penalty on arrears</label>
                                            <input form="approval-matrix-save" type="hidden" name="interest_recalculation" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input form="approval-matrix-save" type="checkbox" name="interest_recalculation" value="1" @checked(($additionalProductSettings['interest_recalculation'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Interest recalculation</label>
                                            <input form="approval-matrix-save" type="hidden" name="allow_top_up" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input form="approval-matrix-save" type="checkbox" name="allow_top_up" value="1" @checked(($additionalProductSettings['allow_top_up'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Allow top-up</label>
                                            <input form="approval-matrix-save" type="hidden" name="allow_early_repayment" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input form="approval-matrix-save" type="checkbox" name="allow_early_repayment" value="1" @checked(($additionalProductSettings['allow_early_repayment'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Allow early repayment</label>
                                            <input form="approval-matrix-save" type="hidden" name="auto_approval_low_risk" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input form="approval-matrix-save" type="checkbox" name="auto_approval_low_risk" value="1" @checked(($additionalProductSettings['auto_approval_low_risk'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Auto-approval for low risk</label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" form="approval-matrix-save" class="inline-flex items-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Save approval matrix</button>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($tab === 'disbursement-controls')
                    @php
                        $mapStatus = is_array($loanDisbursedMapping ?? null) ? (string) ($loanDisbursedMapping['status'] ?? 'Needs Setup') : 'Needs Setup';
                        $creditAcctName = is_array($loanDisbursedMapping ?? null) && ! empty($loanDisbursedMapping['credit_account'])
                            ? (string) ($loanDisbursedMapping['credit_account']->name ?? 'Mapped account')
                            : '—';
                    @endphp
                    <div id="disbursement-controls-root" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <p class="text-xs font-semibold text-slate-500">Pending disbursements</p>
                                <p class="mt-2 text-2xl font-bold text-slate-900">{{ (int) ($ov['pending_disbursement_count'] ?? 0) }}</p>
                                <p class="mt-1 text-xs text-slate-500">Loans in <code class="rounded bg-slate-100 px-1">pending_disbursement</code> — principal total {{ number_format((float) ($ov['pending_disbursement_principal'] ?? 0), 2) }}.</p>
                                <p class="mt-1 text-[11px] text-slate-500">No payout record yet: <strong>{{ (int) ($ov['pending_disbursement_no_payout_count'] ?? 0) }}</strong> loan(s).</p>
                            </div>
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-emerald-800">Cash / disbursement account</p>
                                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ number_format((float) ($ov['disbursement_credit_balance'] ?? 0), 2) }}</p>
                                <p class="mt-1 text-xs text-emerald-800/90"><span class="font-semibold">{{ $creditAcctName }}</span> via slot <code class="rounded bg-white/80 px-1">{{ $disbursementSlotCredit }}</code></p>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-amber-900">Liquidity floor</p>
                                <p class="mt-2 text-2xl font-bold text-amber-900">{{ number_format($effectiveFloor, 2) }}</p>
                                <p class="mt-1 text-xs text-amber-900/80">Chart cash floors: {{ number_format($chartFloor, 2) }} @if ($policyFloor !== null) · Policy add-on: {{ number_format($policyFloor, 2) }} @endif</p>
                            </div>
                            <div class="rounded-xl border border-violet-200 bg-violet-50/70 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-violet-900">Lending brake</p>
                                <p class="mt-2 text-sm font-bold text-violet-950">{{ $ov['lending_brake_label'] ?? '—' }}</p>
                                <p class="mt-1 text-xs text-violet-900/90">{{ $ov['lending_brake_hint'] ?? '' }}</p>
                                <span class="mt-2 inline-flex rounded-full bg-white px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-violet-800 ring-1 ring-violet-200">Liquidity: {{ $ov['liquidity_status'] ?? '—' }}</span>
                            </div>
                        </div>

                        <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="space-y-6">
                            @csrf
                            <input type="hidden" name="section" value="disbursement_controls">

                            <div class="rounded-xl border border-rose-200 bg-gradient-to-br from-rose-50/80 to-white p-5 shadow-sm ring-1 ring-rose-100">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-base font-semibold text-rose-950">Borrower classifier — concurrent open loan</h3>
                                        <p class="mt-1 text-xs text-rose-900/85">
                                            Rule: <code class="rounded bg-white/90 px-1 py-0.5 text-[11px] text-rose-900">active_open_loan_exists</code> in
                                            <code class="rounded bg-white/90 px-1 py-0.5 text-[11px]">App\Services\LoanBook\BorrowerClassificationService::classify()</code>.
                                            Recording a disbursement ignores only the loan being paid out in that check via
                                            <code class="rounded bg-white/90 px-1 py-0.5 text-[11px]">LoanBookOperationsController::disbursementsStore()</code>.
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-rose-200/80 bg-white/90 px-4 py-3">
                                    <input type="hidden" name="allow_top_up_if_active_loan" value="0">
                                    <label class="inline-flex cursor-pointer items-center gap-3">
                                        <input
                                            type="checkbox"
                                            name="allow_top_up_if_active_loan"
                                            value="1"
                                            class="h-5 w-5 rounded border-rose-300 text-rose-700 focus:ring-rose-500"
                                            @checked($engineAllowTopUp)
                                        >
                                        <span>
                                            <span class="text-sm font-semibold text-slate-900">Active</span>
                                            <span class="block text-xs text-slate-600">Allow <strong>new applications or additional booked loans</strong> while the client already has another open or pending-disbursement loan (besides the file you are disbursing).</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="mt-4 min-h-[5.5rem] rounded-lg border-2 border-dashed border-slate-300 bg-slate-50/80 px-4 py-3">
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Inactive (checkbox off — default)</p>
                                    <p class="mt-1 text-xs leading-relaxed text-slate-700">
                                        New internal applications and new loan booking are blocked when any <strong>other</strong> open facility exists and this box stays off.
                                        First-time disbursement on a loan in <code class="rounded bg-white px-1">pending_disbursement</code> is still allowed: that loan is excluded from the concurrent-open check at save time.
                                        Turn <strong>Active</strong> on if your policy allows true top-ups or parallel facilities without closing the prior loan.
                                    </p>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <h3 class="text-base font-semibold text-slate-900">Core disbursement rules</h3>
                                <p class="mt-1 text-sm text-slate-600">Saved to <code class="rounded bg-slate-100 px-1">loan_settings_disbursement_controls</code> for policy documentation and future runtime checks.</p>
                                <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                                    @foreach ([
                                        ['rule_application_approved', 'Application must be approved', 'Credit stage = approved before booking payout.'],
                                        ['rule_loan_pending_disbursement', 'Loan status = pending disbursement', 'Aligns LoanBook loan lifecycle.'],
                                        ['rule_no_duplicate_disbursement', 'Prevent duplicate disbursement', 'Guards against double payout on the same loan.'],
                                        ['rule_block_missing_mapping', 'Block if accounting mapping missing', 'GL posting requires LoanDisbursed mapping.'],
                                        ['rule_block_liquidity_floor', 'Block if projected cash below liquidity floor', 'Uses chart cash vs floors plus optional policy floor.'],
                                        ['rule_override_controlled', 'Controlled disbursement needs override', 'Escalation path for sensitive accounts.'],
                                    ] as [$ruleKey, $label, $hint])
                                        <li class="flex flex-wrap items-center justify-between gap-3 bg-white px-4 py-3 first:rounded-t-xl last:rounded-b-xl">
                                            <div>
                                                <p class="text-sm font-medium text-slate-900">{{ $label }}</p>
                                                <p class="text-xs text-slate-500">{{ $hint }}</p>
                                            </div>
                                            <input type="hidden" name="{{ $ruleKey }}" value="0">
                                            <input type="checkbox" name="{{ $ruleKey }}" value="1" class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(($disbursementControls[$ruleKey] ?? false))>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                                <div class="rounded-xl border border-amber-200 bg-amber-50/40 p-5 shadow-sm">
                                    <h3 class="text-base font-semibold text-amber-950">Liquidity policy</h3>
                                    <p class="mt-1 text-xs text-amber-900/80">Optional policy floor is stored with disbursement controls; chart floors still come from cash accounts.</p>
                                    <div class="mt-4 space-y-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700">Disbursement cash slot</label>
                                            <input type="text" readonly class="mt-1 w-full rounded-lg border-slate-200 bg-white/80 text-sm text-slate-600" value="{{ $disbursementSlotCredit }}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700">Policy minimum liquidity floor (amount)</label>
                                            <input type="number" step="0.01" min="0" name="policy_liquidity_floor" value="{{ $disbursementControls['policy_liquidity_floor'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm" placeholder="Optional — leave blank to use chart floors only">
                                        </div>
                                        <input type="hidden" name="block_below_floor" value="0">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox" name="block_below_floor" value="1" class="rounded border-slate-300 text-indigo-600" @checked(($disbursementControls['block_below_floor'] ?? false))> Block disbursement below effective floor
                                        </label>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700">Warning threshold (% of effective floor)</label>
                                            <input type="number" step="0.01" min="0" max="1000" name="liquidity_warning_pct_of_floor" value="{{ $disbursementControls['liquidity_warning_pct_of_floor'] ?? 110 }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700">Override approver role</label>
                                            <input type="text" name="override_approver_role" value="{{ $disbursementControls['override_approver_role'] ?? 'Treasury Manager' }}" class="mt-1 w-full rounded-lg border-slate-200 bg-white text-sm">
                                        </div>
                                    </div>
                                    <button type="submit" class="mt-5 inline-flex rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Save disbursement controls</button>
                                </div>

                                <div class="rounded-xl border border-indigo-200 bg-white p-5 shadow-sm">
                                    <h3 class="text-base font-semibold text-indigo-950">Mapping readiness</h3>
                                    <p class="mt-1 text-xs text-slate-600">Live row from chart rules registry for <strong>LoanDisbursed</strong>.</p>
                                    <dl class="mt-4 space-y-3 rounded-lg border border-slate-100 bg-slate-50 p-4 text-sm">
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-600">Event</dt>
                                            <dd class="font-mono text-xs font-semibold text-indigo-800">{{ data_get($loanDisbursedMapping, 'event_key', data_get($loanDisbursedEvent, 'event_key', 'LoanDisbursed')) }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-600">Debit slot</dt>
                                            <dd class="font-mono text-xs text-slate-800">{{ data_get($loanDisbursedMapping, 'debit_slot', $disbursementSlotDebit) }}</dd>
                                        </div>
                                        <div class="flex justify-between gap-2">
                                            <dt class="text-slate-600">Credit slot</dt>
                                            <dd class="font-mono text-xs text-slate-800">{{ data_get($loanDisbursedMapping, 'credit_slot', $disbursementSlotCredit) }}</dd>
                                        </div>
                                        @if (is_array($loanDisbursedMapping ?? null))
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-600">Debit account</dt>
                                                <dd class="text-right text-xs font-medium text-slate-800">{{ optional($loanDisbursedMapping['debit_account'] ?? null)?->name ?? '—' }}</dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-600">Credit account</dt>
                                                <dd class="text-right text-xs font-medium text-slate-800">{{ $creditAcctName }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @if ($mapStatus === 'Active')
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900 ring-1 ring-emerald-200">{{ $mapStatus }}</span>
                                        @elseif ($mapStatus === 'Awaiting Approval')
                                            <span class="inline-flex items-center rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-900 ring-1 ring-violet-200">{{ $mapStatus }}</span>
                                        @elseif ($mapStatus === 'Disabled')
                                            <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-300">{{ $mapStatus }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-200">{{ $mapStatus }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-4 text-xs text-slate-500">Map slots under <a href="{{ route('loan.accounting.books.chart_rules') }}" class="font-semibold text-indigo-700 hover:underline">Accounting → Chart rules</a>.</p>
                                </div>
                            </div>
                        </form>

                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm ring-1 ring-amber-200/80">
                            <p class="font-semibold">Liquidity brake</p>
                            <p class="mt-1">Snapshot: aggregate cash on chart accounts is <strong>{{ number_format((float) ($ov['available_liquidity'] ?? 0), 2) }}</strong> versus configured floors <strong>{{ number_format((float) ($ov['liquidity_floor_chart'] ?? 0), 2) }}</strong>. Status: <strong>{{ $ov['liquidity_status'] ?? '—' }}</strong>. When status is AT RISK, align operations with your saved “block below floor” policy above.</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('loan.book.disbursements.index') }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">Open disbursements</a>
                            <a href="{{ route('loan.accounting.books.chart_rules') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Chart rules</a>
                            @if (($ov['applications_in_credit_review'] ?? 0) > 0)
                                <a href="{{ route('loan.book.applications.index', ['stage' => 'credit_review']) }}" class="inline-flex items-center rounded-lg border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-900 shadow-sm hover:bg-violet-100">Credit queue ({{ (int) $ov['applications_in_credit_review'] }})</a>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($tab === 'eligibility-affordability')
                    <div id="eligibility-affordability-root" class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-emerald-900">Active rules</p>
                                <p class="mt-2 text-3xl font-bold text-emerald-950">{{ $eligActive + $approvalRowsCount }}</p>
                                <p class="text-xs text-emerald-900/80">Eligibility JSON + matrix bands.</p>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50/50 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-rose-900">Blocked conditions</p>
                                <p class="mt-2 text-3xl font-bold text-rose-950">{{ $blockedConds }}</p>
                                <p class="text-xs text-rose-900/80">Arrears / write-off toggles.</p>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-amber-900">Override-required</p>
                                <p class="mt-2 text-3xl font-bold text-amber-950">{{ $overrideHints }}</p>
                                <p class="text-xs text-amber-900/80">Hints from saved flags.</p>
                            </div>
                            <div class="rounded-xl border border-violet-200 bg-violet-50/60 p-4 shadow-sm">
                                <p class="text-xs font-semibold text-violet-900">High-risk clients</p>
                                <p class="mt-2 text-3xl font-bold text-violet-950">{{ (int) ($ov['risky_borrowers_on_book'] ?? 0) }}</p>
                                <p class="text-xs text-violet-900/80">Open loans tagged repeat_risky with positive balance.</p>
                            </div>
                        </div>

                        <div id="eligibility-rules" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-base font-semibold text-slate-900">Basic eligibility rules</h3>
                            <p class="text-xs text-slate-500">Saved to <code class="rounded bg-slate-100 px-1">loan_settings_eligibility_rules</code>.</p>
                            <form id="loan-settings-eligibility-form" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="mt-4 space-y-5">
                                @csrf
                                <input type="hidden" name="section" value="eligibility_rules">
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <tr>
                                                <th class="px-3 py-2">Rule</th>
                                                <th class="px-3 py-2">Configuration</th>
                                                <th class="px-3 py-2">Mode</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Minimum client age</td>
                                                <td class="px-3 py-3"><input name="minimum_age" value="{{ $eligibilityRules['minimum_age'] ?? '' }}" class="w-full max-w-xs rounded-lg border-slate-200 text-sm" placeholder="Years"></td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Hard</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Maximum client age</td>
                                                <td class="px-3 py-3"><input name="maximum_age" value="{{ $eligibilityRules['maximum_age'] ?? '' }}" class="w-full max-w-xs rounded-lg border-slate-200 text-sm" placeholder="Years"></td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Hard</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Active loan count limit</td>
                                                <td class="px-3 py-3"><input name="active_loan_limit" value="{{ $eligibilityRules['active_loan_limit'] ?? '' }}" class="w-full max-w-xs rounded-lg border-slate-200 text-sm" placeholder="e.g. 1"></td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Hard</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Client types allowed</td>
                                                <td class="px-3 py-3"><input name="allowed_client_types" value="{{ implode(', ', (array) ($eligibilityRules['allowed_client_types'] ?? [])) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Comma separated"></td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Filter</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Block if written-off history</td>
                                                <td class="px-3 py-3">
                                                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="block_written_off_history" value="1" @checked(($eligibilityRules['block_written_off_history'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Enable</label>
                                                </td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-900">Block</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Block if current arrears</td>
                                                <td class="px-3 py-3">
                                                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="block_with_arrears" value="1" @checked(($eligibilityRules['block_with_arrears'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Enable</label>
                                                </td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-900">Block</span></td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-3 font-medium text-slate-800">Blacklist / watchlist</td>
                                                <td class="px-3 py-3 text-xs text-slate-500">Use client flags &amp; interactions (future). Related documents below.</td>
                                                <td class="px-3 py-3"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Planned</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <input name="required_documents" value="{{ implode(', ', (array) ($eligibilityRules['required_documents'] ?? [])) }}" class="rounded-lg border-slate-200 text-sm" placeholder="Required documents (comma separated)">
                                    <input name="allowed_sectors" value="{{ implode(', ', (array) ($eligibilityRules['allowed_sectors'] ?? [])) }}" class="rounded-lg border-slate-200 text-sm" placeholder="Allowed sectors">
                                    <input name="blocked_sectors" value="{{ implode(', ', (array) ($eligibilityRules['blocked_sectors'] ?? [])) }}" class="rounded-lg border-slate-200 text-sm" placeholder="Blocked sectors">
                                    <input name="minimum_repayment_history" value="{{ $eligibilityRules['minimum_repayment_history'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Min repayment history">
                                    <input name="minimum_client_score" value="{{ $eligibilityRules['minimum_client_score'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Min client score">
                                </div>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="guarantor_required" value="1" @checked(($eligibilityRules['guarantor_required'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Guarantor required</label>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="collateral_required" value="1" @checked(($eligibilityRules['collateral_required'] ?? false)) class="rounded border-slate-300 text-indigo-600"> Collateral required</label>
                                </div>
                                <button type="submit" class="inline-flex rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save eligibility rules</button>
                            </form>
                        </div>

                        <div class="rounded-xl border border-indigo-200 bg-indigo-50/30 p-5 shadow-sm">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base font-semibold text-indigo-950">Affordability (runtime engine)</h3>
                                    <p class="mt-1 text-sm text-slate-700">These values are stored as <strong>LoanSystemSetting</strong> keys and read by the live borrower classifier during application review.</p>
                                </div>
                                <div class="shrink-0 space-y-2 lg:max-w-sm lg:text-right">
                                    <p class="text-xs text-slate-600 lg:text-right">When disabled, ratio checks are not enforced; thresholds stay saved for reference.</p>
                                </div>
                            </div>
                            @php
                                $affOld = old('affordability_engine_enabled', $engineAffordabilityEnabled ? '1' : '0');
                                $affSwitchOn = is_array($affOld) ? in_array('1', $affOld, true) : ((string) $affOld === '1');
                            @endphp
                            @unless ($affSwitchOn)
                                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-950 ring-1 ring-amber-200/80">
                                    Affordability checks are disabled for this tenant.
                                </div>
                            @endunless
                            <form id="loan-settings-affordability-form" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="section" value="affordability_engine">
                                <div class="flex flex-wrap items-center gap-3 rounded-lg border border-indigo-200 bg-white/90 p-3 shadow-sm">
                                    @if ($affSwitchOn)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900 ring-1 ring-emerald-200">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-800 ring-1 ring-slate-300">Disabled</span>
                                    @endif
                                    <label class="inline-flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-800">
                                        <span class="whitespace-nowrap">Enable affordability checks</span>
                                        <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
                                            <input type="hidden" name="affordability_engine_enabled" value="0">
                                            <input type="checkbox" name="affordability_engine_enabled" value="1" @checked($affSwitchOn) class="peer sr-only" role="switch" @if ($affSwitchOn) aria-checked="true" @else aria-checked="false" @endif>
                                            <span class="absolute inset-0 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-indigo-500" aria-hidden="true"></span>
                                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5" aria-hidden="true"></span>
                                        </span>
                                    </label>
                                    <button type="submit" class="ml-auto inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
                                </div>
                                <div @class(['space-y-4 transition-opacity', 'opacity-50' => ! $affSwitchOn])>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <div class="rounded-lg border border-white/80 bg-white p-3 shadow-sm">
                                            <label class="block text-xs font-semibold text-slate-500" for="max_installment_to_income_ratio">Installment-to-income cap</label>
                                            <input id="max_installment_to_income_ratio" name="max_installment_to_income_ratio" type="number" step="0.01" min="0" max="5" value="{{ old('max_installment_to_income_ratio', $engineMaxRatio) }}" class="mt-1 w-full rounded-lg border-slate-200 text-base font-bold text-indigo-900" required>
                                            <p class="mt-1 text-xs text-slate-500">Key: <code class="rounded bg-slate-100 px-1">max_installment_to_income_ratio</code> (e.g. 0.40)</p>
                                        </div>
                                        <div class="rounded-lg border border-white/80 bg-white p-3 shadow-sm">
                                            <label class="block text-xs font-semibold text-slate-500" for="min_repayment_success_rate">Min repayment success rate</label>
                                            <input id="min_repayment_success_rate" name="min_repayment_success_rate" type="number" step="0.01" min="0" max="1" value="{{ old('min_repayment_success_rate', $engineMinRepay) }}" class="mt-1 w-full rounded-lg border-slate-200 text-base font-bold text-indigo-900" required>
                                            <p class="mt-1 text-xs text-slate-500">Key: <code class="rounded bg-slate-100 px-1">min_repayment_success_rate</code></p>
                                        </div>
                                        <div class="rounded-lg border border-white/80 bg-white p-3 shadow-sm">
                                            <label class="block text-xs font-semibold text-slate-500" for="max_allowed_dpd_for_repeat">Max DPD for repeat</label>
                                            <input id="max_allowed_dpd_for_repeat" name="max_allowed_dpd_for_repeat" type="number" step="1" min="0" max="365" value="{{ old('max_allowed_dpd_for_repeat', $engineMaxDpd) }}" class="mt-1 w-full rounded-lg border-slate-200 text-base font-bold text-indigo-900" required>
                                            <p class="mt-1 text-xs text-slate-500">Key: <code class="rounded bg-slate-100 px-1">max_allowed_dpd_for_repeat</code></p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div class="rounded-lg border border-indigo-100 bg-white p-3 shadow-sm">
                                            <label class="block text-xs font-semibold text-slate-600" for="max_total_indebtedness_to_income_ratio">Total indebtedness cap</label>
                                            <input id="max_total_indebtedness_to_income_ratio" name="max_total_indebtedness_to_income_ratio" type="number" step="0.01" min="0" max="1000" value="{{ old('max_total_indebtedness_to_income_ratio', $engineMaxIndebtedness) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-900" placeholder="0 = off">
                                            <p class="mt-1 text-xs text-slate-500">Max <span class="font-medium">(sum of loan balances + requested principal) ÷ verified monthly income</span>. Use <span class="font-mono">0</span> to disable.</p>
                                            <p class="mt-0.5 text-xs text-slate-400">Key: <code class="rounded bg-slate-100 px-1">max_total_indebtedness_to_income_ratio</code></p>
                                        </div>
                                        <div class="rounded-lg border border-indigo-100 bg-white p-3 shadow-sm">
                                            <label class="block text-xs font-semibold text-slate-600" for="max_combined_guarantor_exposure_ratio">Combined / guarantor exposure cap</label>
                                            <input id="max_combined_guarantor_exposure_ratio" name="max_combined_guarantor_exposure_ratio" type="number" step="0.01" min="0" max="1000" value="{{ old('max_combined_guarantor_exposure_ratio', $engineMaxGuarantorExposure) }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-900" placeholder="0 = off">
                                            <p class="mt-1 text-xs text-slate-500">Same ratio as above; applied when the client record has a guarantor. If both caps are set, the <span class="font-medium">stricter</span> applies. <span class="font-mono">0</span> disables this extra rule.</p>
                                            <p class="mt-0.5 text-xs text-slate-400">Key: <code class="rounded bg-slate-100 px-1">max_combined_guarantor_exposure_ratio</code></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-amber-800">Example: block if installments exceed <span class="font-semibold">40%</span> of income — set installment cap to <code class="rounded bg-white/80 px-1">0.40</code>.</p>
                                </div>
                            </form>
                        </div>

                        <div id="graduation-logic" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-base font-semibold text-slate-900">Repeat loan graduation</h3>
                            <p class="text-xs text-slate-500">Saved to <code class="rounded bg-slate-100 px-1">loan_settings_graduation_rules</code>. Engine also uses system keys: <code class="rounded bg-slate-100 px-1">first_loan_limit</code>, <code class="rounded bg-slate-100 px-1">second_loan_limit</code>, <code class="rounded bg-slate-100 px-1">graduation_increase_percentage</code>.</p>
                            <div class="mt-3 rounded-lg border border-violet-100 bg-violet-50/50 p-3 text-xs text-violet-950">
                                <p><strong>Live engine snapshot:</strong> first loan limit <strong>{{ $engineFirstLimit !== '' ? $engineFirstLimit : '—' }}</strong>, second <strong>{{ $engineSecondLimit !== '' ? $engineSecondLimit : '—' }}</strong>, graduation % <strong>{{ $engineGradPct !== '' ? $engineGradPct : '—' }}</strong>, allow top-up with active loan <strong>{{ $engineAllowTopUp ? 'yes' : 'no' }}</strong>, block written-off history <strong>{{ $engineBlockWo ? 'yes' : 'no' }}</strong>.</p>
                            </div>
                            <form id="loan-settings-graduation-eligibility-form" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="mt-4 space-y-4">
                                @csrf
                                <input type="hidden" name="section" value="graduation_logic">
                                <input type="hidden" name="redirect_tab" value="eligibility-affordability">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600">First loan max limit</label>
                                        <input name="first_loan_max_limit" value="{{ $graduationRules['first_loan_max_limit'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Amount">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600">Second loan max limit</label>
                                        <input name="second_loan_max_limit" value="{{ $graduationRules['second_loan_max_limit'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Amount">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600">Subsequent increase %</label>
                                        <input name="subsequent_increase_pct" value="{{ $graduationRules['subsequent_increase_pct'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Percent">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-600">Reduce limit after default %</label>
                                        <input name="reduce_limit_after_default_pct" value="{{ $graduationRules['reduce_limit_after_default_pct'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Percent haircut">
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm text-slate-700">
                                    <label class="flex items-start gap-2"><input type="checkbox" name="increase_after_full_payment_only" value="1" @checked(($graduationRules['increase_after_full_payment_only'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Increase only if previous loan fully paid</label>
                                    <label class="flex items-start gap-2"><input type="checkbox" name="block_if_arrears_exist" value="1" @checked(($graduationRules['block_if_arrears_exist'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block graduation if arrears exist</label>
                                    <label class="flex items-start gap-2"><input type="checkbox" name="block_if_late_payment_exists" value="1" @checked(($graduationRules['block_if_late_payment_exists'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block if late payment history</label>
                                    <label class="flex items-start gap-2"><input type="checkbox" name="block_if_written_off_history_exists" value="1" @checked(($graduationRules['block_if_written_off_history_exists'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block if written-off history exists</label>
                                </div>
                                <button type="submit" class="inline-flex rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">Save graduation rules</button>
                            </form>
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                            <h3 class="text-base font-semibold text-amber-950">Sector &amp; concentration (warnings)</h3>
                            <p class="text-xs text-slate-600">Warnings by default; mark as enforced only after limits are instrumented.</p>
                            <div class="mt-4 overflow-x-auto rounded-lg border border-amber-100">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-amber-50 text-left text-xs font-semibold uppercase tracking-wide text-amber-950">
                                        <tr>
                                            <th class="px-3 py-2">Exposure type</th>
                                            <th class="px-3 py-2">Policy reference</th>
                                            <th class="px-3 py-2">Severity</th>
                                            <th class="px-3 py-2">Enforced?</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">Sector concentration</td>
                                            <td class="px-3 py-2 text-slate-600">Allowed: {{ implode(', ', array_slice((array) ($eligibilityRules['allowed_sectors'] ?? []), 0, 3)) }}{{ count((array) ($eligibilityRules['allowed_sectors'] ?? [])) > 3 ? '…' : (count((array) ($eligibilityRules['allowed_sectors'] ?? [])) === 0 ? '—' : '') }}</td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Warning</span></td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">No</span></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">Employer / checkoff concentration</td>
                                            <td class="px-3 py-2 text-slate-600">Monitor payroll-linked portfolios.</td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Warning</span></td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">No</span></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">Branch exposure</td>
                                            <td class="px-3 py-2 text-slate-600">Per-branch concentration vs capital.</td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Warning</span></td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">No</span></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">Product exposure</td>
                                            <td class="px-3 py-2 text-slate-600">Single product as % of book.</td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Warning</span></td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">No</span></td>
                                        </tr>
                                        <tr>
                                            <td class="px-3 py-2 font-medium text-slate-800">Blocked sectors</td>
                                            <td class="px-3 py-2 text-slate-600">{{ implode(', ', (array) ($eligibilityRules['blocked_sectors'] ?? [])) ?: '—' }}</td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-900">High</span></td>
                                            <td class="px-3 py-2"><span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900">Via list</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($tab === 'portfolio-limits')
                    <div id="graduation-logic" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-base font-semibold text-slate-900">Portfolio limits &amp; graduation</h3>
                        <p class="text-xs text-slate-500">Same graduation payload as the eligibility tab; kept here for portfolio officers.</p>
                        <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="mt-4 space-y-4">
                            @csrf
                            <input type="hidden" name="section" value="graduation_logic">
                            <input type="hidden" name="redirect_tab" value="portfolio-limits">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">First loan max limit</label>
                                    <input name="first_loan_max_limit" value="{{ $graduationRules['first_loan_max_limit'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Amount">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Second loan max limit</label>
                                    <input name="second_loan_max_limit" value="{{ $graduationRules['second_loan_max_limit'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Amount">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Subsequent increase %</label>
                                    <input name="subsequent_increase_pct" value="{{ $graduationRules['subsequent_increase_pct'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Percent">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600">Reduce limit after default %</label>
                                    <input name="reduce_limit_after_default_pct" value="{{ $graduationRules['reduce_limit_after_default_pct'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-200 text-sm" placeholder="Percent haircut">
                                </div>
                            </div>
                            <div class="space-y-2 text-sm text-slate-700">
                                <label class="flex items-start gap-2"><input type="checkbox" name="increase_after_full_payment_only" value="1" @checked(($graduationRules['increase_after_full_payment_only'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Increase only if previous loan fully paid</label>
                                <label class="flex items-start gap-2"><input type="checkbox" name="block_if_arrears_exist" value="1" @checked(($graduationRules['block_if_arrears_exist'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block graduation if arrears exist</label>
                                <label class="flex items-start gap-2"><input type="checkbox" name="block_if_late_payment_exists" value="1" @checked(($graduationRules['block_if_late_payment_exists'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block if late payment history</label>
                                <label class="flex items-start gap-2"><input type="checkbox" name="block_if_written_off_history_exists" value="1" @checked(($graduationRules['block_if_written_off_history_exists'] ?? false)) class="mt-1 rounded border-slate-300 text-indigo-600"> Block if written-off history exists</label>
                            </div>
                            <button type="submit" class="inline-flex rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save portfolio graduation</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    window.loanFormProductNav = function (value) {
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'product-rules');
        if (value === 'all') {
            u.searchParams.delete('form_product');
            u.searchParams.delete('pending_complete');
        } else {
            u.searchParams.set('form_product', value);
            u.searchParams.delete('pending_complete');
        }
        window.location.href = u.toString();
    };
    window.loanFormPendingNav = function (value) {
        if (!value) {
            return;
        }
        const u = new URL(window.location.href);
        u.searchParams.set('tab', 'product-rules');
        u.searchParams.set('form_product', value);
        u.searchParams.set('pending_complete', '1');
        window.location.href = u.toString();
    };

    function loanSettingsLoanFormStateFromBoot() {
        const el = document.getElementById('loan-form-setup-boot');
        if (!el || !el.textContent.trim()) {
            return loanSettingsLoanFormState([], 'global', null, '', '', []);
        }
        try {
            const boot = JSON.parse(el.textContent);
            return loanSettingsLoanFormState(
                boot.fieldsPayload ?? [],
                boot.editorScope ?? 'global',
                boot.editorProductId ?? null,
                boot.payloadUrl ?? '',
                boot.applicationsCreateUrl ?? '',
                boot.products ?? [],
            );
        } catch (e) {
            console.error('loan-form-setup-boot parse failed', e);
            return loanSettingsLoanFormState([], 'global', null, '', '', []);
        }
    }

    function loanSettingsLoanFormState(rows, editorScope, editorProductId, payloadUrl, applicationsCreateUrl, products) {
        const normalize = (r) => ({
            ...r,
            included: Boolean(r.included ?? r.is_core),
            detail_status: r.detail_status === 'requires_approval' ? 'requires_approval' : 'active',
            field_status: r.field_status ?? 'active',
            is_required: Boolean(r.is_required),
            prefill_from_previous: Boolean(r.prefill_from_previous),
        });

        return {
            rows: Array.isArray(rows) ? rows.map(normalize) : [],
            editorScope: editorScope === 'product' ? 'product' : 'global',
            editorProductId: editorProductId === null || editorProductId === undefined ? null : Number(editorProductId),
            payloadUrl: String(payloadUrl || ''),
            applicationsCreateUrl: String(applicationsCreateUrl || ''),
            products: Array.isArray(products) ? products : [],
            previewModal: false,
            cloneModal: false,
            cloneMode: 'global',
            cloneError: '',
            cloneBusy: false,
            cloneSourceProductId: '',
            fieldStatusForSubmit(row) {
                if (row.is_core) {
                    return 'active';
                }
                if (this.editorScope !== 'product') {
                    return row.field_status ?? 'active';
                }
                if (!row.included) {
                    return 'draft';
                }
                return row.detail_status === 'requires_approval' ? 'requires_approval' : 'active';
            },
            rowEstimatedOnBooking(row) {
                if (row.is_core) {
                    return true;
                }
                if (this.editorScope === 'product') {
                    return !!row.included;
                }
                return !!row.included && (row.field_status === 'active');
            },
            previewTableRows() {
                const sorted = [...this.rows].sort(
                    (a, b) =>
                        (Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0)) ||
                        (Number(a.master_id ?? 0) - Number(b.master_id ?? 0)),
                );
                return sorted.map((row) => ({
                    label: row.label,
                    field_key: row.field_key,
                    data_type: row.data_type,
                    is_required: !!(row.is_required && (row.is_core || row.included)),
                    on_booking: this.rowEstimatedOnBooking(row),
                }));
            },
            openPreviewModal() {
                this.previewModal = true;
                this.cloneError = '';
            },
            closePreviewModal() {
                this.previewModal = false;
            },
            openCloneModal() {
                if (this.editorScope === 'product' && this.editorProductId) {
                    this.cloneMode = 'product';
                    const others = this.products.filter((p) => Number(p.id) !== Number(this.editorProductId));
                    this.cloneSourceProductId = others[0] ? String(others[0].id) : '';
                } else {
                    this.cloneMode = 'global';
                    this.cloneSourceProductId = '';
                }
                this.cloneError = '';
                this.cloneModal = true;
            },
            closeCloneModal() {
                if (this.cloneBusy) {
                    return;
                }
                this.cloneModal = false;
            },
            cloneSourceOptions() {
                if (this.editorScope !== 'product' || !this.editorProductId) {
                    return [];
                }
                return this.products.filter((p) => Number(p.id) !== Number(this.editorProductId));
            },
            async applyCloneFromSelectedProduct() {
                if (this.editorScope !== 'product' || !this.editorProductId) {
                    return;
                }
                const sid = String(this.cloneSourceProductId || '').trim();
                if (!sid) {
                    this.cloneError = 'Pick a product to copy from.';
                    return;
                }
                if (Number(sid) === Number(this.editorProductId)) {
                    this.cloneError = 'Choose a different product.';
                    return;
                }
                this.cloneBusy = true;
                this.cloneError = '';
                try {
                    const u = new URL(this.payloadUrl, window.location.origin);
                    u.searchParams.set('form_product', sid);
                    const res = await fetch(u.toString(), { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (!res.ok || !data.ok || !Array.isArray(data.rows)) {
                        this.cloneError = (data && data.message) || 'Could not load that product’s form.';
                        return;
                    }
                    const byKey = new Map(data.rows.map((r) => [r.field_key, r]));
                    this.rows.forEach((row) => {
                        if (row.is_core) {
                            return;
                        }
                        const src = byKey.get(row.field_key);
                        if (!src) {
                            return;
                        }
                        row.included = !!src.included;
                        row.is_required = !!src.is_required;
                        row.prefill_from_previous = !!src.prefill_from_previous;
                        row.visible_to = src.visible_to ? String(src.visible_to) : '';
                        row.detail_status = src.detail_status === 'requires_approval' ? 'requires_approval' : 'active';
                        if (this.editorScope === 'global') {
                            row.field_status = src.field_status ?? row.field_status;
                        }
                        row.sort_order = Number(src.sort_order ?? row.sort_order ?? 0);
                    });
                    this.rows.sort(
                        (a, b) =>
                            (Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0)) ||
                            (Number(a.master_id ?? 0) - Number(b.master_id ?? 0)),
                    );
                    this.cloneModal = false;
                } catch (e) {
                    this.cloneError = 'Network error. Try again.';
                } finally {
                    this.cloneBusy = false;
                }
            },
            toggleInclude(row, event) {
                if (row.is_core) {
                    return;
                }
                const on = event.target.checked;
                row.included = on;
                if (this.editorScope === 'global') {
                    if (!on) {
                        row.field_status = 'draft';
                    } else if (row.field_status === 'draft') {
                        row.field_status = 'active';
                    }
                }
                if (this.editorScope === 'product' && !on) {
                    row.is_required = false;
                    row.prefill_from_previous = false;
                    row.detail_status = 'active';
                }
            },
            add() {
                this.rows.push({
                    master_id: 0,
                    override_id: null,
                    field_key: '',
                    label: '',
                    data_type: 'alphanumeric',
                    select_options: '',
                    is_core: false,
                    included: true,
                    field_status: 'active',
                    detail_status: 'active',
                    is_required: false,
                    prefill_from_previous: false,
                    visible_to: '',
                    sort_order: this.rows.length,
                });
            },
            remove(i) {
                if (this.rows[i].is_core || this.editorScope === 'product') {
                    return;
                }
                this.rows.splice(i, 1);
            },
            up(i) {
                if (i === 0) {
                    return;
                }
                [this.rows[i - 1], this.rows[i]] = [this.rows[i], this.rows[i - 1]];
            },
            down(i) {
                if (i === this.rows.length - 1) {
                    return;
                }
                [this.rows[i + 1], this.rows[i]] = [this.rows[i], this.rows[i + 1]];
            },
        };
    }

    (() => {
        const tabTargets = {
            'product-rules': 'loan-form-setup',
            'approval-matrix': 'approval-matrix-root',
            'disbursement-controls': 'disbursement-controls-root',
            'eligibility-affordability': 'eligibility-affordability-root',
            'portfolio-limits': 'graduation-logic',
            'staff-loans': null,
            'dormancy-reactivation': null,
            'overrides-security': null,
            'audit-trail': null,
        };

        const crossPage = {
            'staff-loans': @json(route('loan.accounting.advances.index')),
            'dormancy-reactivation': @json(route('loan.system.setup.preferences')),
            'overrides-security': @json(route('loan.system.setup.access_roles')),
            'audit-trail': @json(route('loan.system.access_logs.index')),
        };

        const currentTab = @json($tab);
        const targetId = tabTargets[currentTab] ?? null;
        const jumpUrl = crossPage[currentTab] ?? null;

        if (!targetId && jumpUrl) {
            window.location.assign(jumpUrl);
            return;
        }
        if (targetId) {
            requestAnimationFrame(() => {
                document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    })();
</script>
