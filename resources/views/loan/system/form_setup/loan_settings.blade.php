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
                <button form="loan-settings-primary-form" type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Save Changes
                </button>
            </div>
        </x-slot>

        @include('loan.accounting.partials.flash')

        <div class="space-y-5 bg-slate-50 p-1">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500">Active Loan Products</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ (int) $activeProductsCount }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500">Approval Rules Active</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-700">{{ count($requiredApprovals) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500">Lending Brake Status</p>
                    <p class="mt-2 text-sm font-semibold text-orange-700">Guarded</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold text-slate-500">High-Risk Overrides Pending</p>
                    <p class="mt-2 text-2xl font-bold text-red-700">0</p>
                </div>
            </div>

            @php($tab = (string) request('tab', 'product-rules'))
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
                    <div id="loan-form-setup" class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">A. Loan Form Setup</h3>
                                <p class="text-xs text-slate-500">Controls the fields shown during loan application booking.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a href="#loan-form-setup" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700">Setup Loan Form</a>
                                <button type="button" @click="window.dispatchEvent(new CustomEvent('loan-settings-preview-form'))" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700">Preview Form</button>
                                <button type="button" @click="window.dispatchEvent(new CustomEvent('loan-settings-clone-form'))" class="rounded-lg border border-blue-200 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700">Clone Form</button>
                            </div>
                        </div>

                        <form id="loan-settings-primary-form" method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings']) }}" class="space-y-3" x-data="{ rows: @js($fieldsPayload), add(){ this.rows.push({id:null,label:'',field_key:'',data_type:'alphanumeric',is_required:false,select_options:'',prefill_from_previous:false,visible_to:'',field_status:'draft',product_id:'',is_core:false}); }, remove(i){ if(this.rows[i].is_core){ return; } this.rows.splice(i,1); }, up(i){ if(i===0){ return; } [this.rows[i-1],this.rows[i]]=[this.rows[i],this.rows[i-1]]; }, down(i){ if(i===this.rows.length-1){ return; } [this.rows[i+1],this.rows[i]]=[this.rows[i],this.rows[i+1]]; } }">
                            @csrf
                            <input type="hidden" name="section" value="loan_form_setup">

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-slate-600">Product selector</label>
                                    <select class="w-full rounded-lg border-slate-200 text-sm">
                                        <option>All products</option>
                                        @foreach ($products as $product)
                                            <option>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
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
                                            <th class="px-2 py-2 text-left">Visible To</th>
                                            <th class="px-2 py-2 text-left">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(row, index) in rows" :key="row.id ?? ('new-'+index)">
                                            <tr class="border-t border-slate-100">
                                                <td class="px-2 py-2">
                                                    <input
                                                        type="checkbox"
                                                        class="rounded border-slate-300"
                                                        :checked="row.field_status === 'active'"
                                                        @change="row.field_status = $event.target.checked ? 'active' : 'draft'"
                                                        :title="row.field_status === 'active' ? 'Included in form' : 'Excluded from form'"
                                                    >
                                                </td>
                                                <td class="px-2 py-2"><input class="w-40 rounded border-slate-200 text-xs" x-model="row.label" :name="`fields[${index}][label]`" required></td>
                                                <td class="px-2 py-2"><input class="w-40 rounded border-slate-200 bg-slate-50 text-xs" x-model="row.field_key" readonly></td>
                                                <td class="px-2 py-2">
                                                    <select class="w-40 rounded border-slate-200 text-xs" x-model="row.data_type" :name="`fields[${index}][data_type]`">
                                                        @foreach ($dataTypeLabels as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <textarea x-show="row.data_type === 'select'" x-model="row.select_options" :name="`fields[${index}][select_options]`" rows="2" class="mt-1 w-40 rounded border-slate-200 text-xs" placeholder="Option A, Option B"></textarea>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="checkbox" x-model="row.is_required" class="rounded border-slate-300">
                                                    <input type="hidden" :name="`fields[${index}][is_required]`" :value="row.is_required ? 1 : 0">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input type="checkbox" x-model="row.prefill_from_previous" :disabled="row.is_core" class="rounded border-slate-300">
                                                    <input type="hidden" :name="`fields[${index}][prefill_from_previous]`" :value="row.prefill_from_previous ? 1 : 0">
                                                </td>
                                                <td class="px-2 py-2">
                                                    <input class="w-40 rounded border-slate-200 text-xs" x-model="row.visible_to" :name="`fields[${index}][visible_to]`" placeholder="officer, manager">
                                                    <select class="mt-1 w-40 rounded border-slate-200 text-xs" x-model="row.product_id" :name="`fields[${index}][product_id]`">
                                                        <option value="">All products</option>
                                                        @foreach ($products as $product)
                                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select class="mt-1 w-40 rounded border-slate-200 text-xs" x-model="row.field_status" :name="`fields[${index}][field_status]`">
                                                        <option value="active">Active</option>
                                                        <option value="draft">Draft</option>
                                                        <option value="requires_approval">Requires Approval</option>
                                                    </select>
                                                </td>
                                                <td class="px-2 py-2">
                                                    <div class="flex gap-1">
                                                        <button type="button" @click="up(index)" class="rounded border border-slate-200 px-1.5 py-0.5">↑</button>
                                                        <button type="button" @click="down(index)" class="rounded border border-slate-200 px-1.5 py-0.5">↓</button>
                                                        <button type="button" @click="remove(index)" :disabled="row.is_core" class="rounded border border-red-200 px-1.5 py-0.5 text-red-600">✕</button>
                                                    </div>
                                                    <input type="hidden" :name="`fields[${index}][id]`" :value="row.id ?? ''">
                                                    <input type="hidden" :name="`fields[${index}][field_status]`" :value="row.field_status ?? 'active'">
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" @click="add()" class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700">Add Custom Field</button>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save Loan Form Setup</button>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <div id="eligibility-rules" class="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 class="text-base font-semibold text-slate-900">B. Eligibility Rules</h3>
                            <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings', 'tab' => 'product-rules']) }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="section" value="eligibility_rules">
                                <div class="grid grid-cols-2 gap-3">
                                    <input name="minimum_age" value="{{ $eligibilityRules['minimum_age'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Minimum Age">
                                    <input name="maximum_age" value="{{ $eligibilityRules['maximum_age'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Maximum Age">
                                </div>
                                <input name="allowed_client_types" value="{{ implode(', ', (array) ($eligibilityRules['allowed_client_types'] ?? [])) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Allowed Client Types (comma separated)">
                                <input name="required_documents" value="{{ implode(', ', (array) ($eligibilityRules['required_documents'] ?? [])) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Required Documents (comma separated)">
                                <input name="allowed_sectors" value="{{ implode(', ', (array) ($eligibilityRules['allowed_sectors'] ?? [])) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Allowed Sectors">
                                <input name="blocked_sectors" value="{{ implode(', ', (array) ($eligibilityRules['blocked_sectors'] ?? [])) }}" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Blocked Sectors">
                                <div class="grid grid-cols-3 gap-3">
                                    <input name="minimum_repayment_history" value="{{ $eligibilityRules['minimum_repayment_history'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Min Repayment History">
                                    <input name="minimum_client_score" value="{{ $eligibilityRules['minimum_client_score'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Min Client Score">
                                    <input name="active_loan_limit" value="{{ $eligibilityRules['active_loan_limit'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Active Loan Limit">
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <label><input type="checkbox" name="guarantor_required" value="1" @checked(($eligibilityRules['guarantor_required'] ?? false)) class="rounded border-slate-300"> Guarantor Required</label>
                                    <label><input type="checkbox" name="collateral_required" value="1" @checked(($eligibilityRules['collateral_required'] ?? false)) class="rounded border-slate-300"> Collateral Required</label>
                                    <label><input type="checkbox" name="block_with_arrears" value="1" @checked(($eligibilityRules['block_with_arrears'] ?? false)) class="rounded border-slate-300"> Block With Arrears</label>
                                    <label><input type="checkbox" name="block_written_off_history" value="1" @checked(($eligibilityRules['block_written_off_history'] ?? false)) class="rounded border-slate-300"> Block Written-off History</label>
                                </div>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save Eligibility Rules</button>
                            </form>
                        </div>

                        <div id="graduation-logic" class="rounded-xl border border-slate-200 bg-white p-5">
                            <h3 class="text-base font-semibold text-slate-900">C. Repeat-Loan Graduation Logic</h3>
                            <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings', 'tab' => 'product-rules']) }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="section" value="graduation_logic">
                                <div class="grid grid-cols-2 gap-3">
                                    <input name="first_loan_max_limit" value="{{ $graduationRules['first_loan_max_limit'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="First Loan Max Limit">
                                    <input name="second_loan_max_limit" value="{{ $graduationRules['second_loan_max_limit'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Second Loan Max Limit">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <input name="subsequent_increase_pct" value="{{ $graduationRules['subsequent_increase_pct'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Subsequent Increase %">
                                    <input name="reduce_limit_after_default_pct" value="{{ $graduationRules['reduce_limit_after_default_pct'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Reduce Limit After Default %">
                                </div>
                                <div class="grid grid-cols-1 gap-2 text-sm">
                                    <label><input type="checkbox" name="increase_after_full_payment_only" value="1" @checked(($graduationRules['increase_after_full_payment_only'] ?? false)) class="rounded border-slate-300"> Increase allowed only if previous loan fully paid</label>
                                    <label><input type="checkbox" name="block_if_arrears_exist" value="1" @checked(($graduationRules['block_if_arrears_exist'] ?? false)) class="rounded border-slate-300"> Block graduation if arrears exist</label>
                                    <label><input type="checkbox" name="block_if_late_payment_exists" value="1" @checked(($graduationRules['block_if_late_payment_exists'] ?? false)) class="rounded border-slate-300"> Block graduation if late payment exists</label>
                                    <label><input type="checkbox" name="block_if_written_off_history_exists" value="1" @checked(($graduationRules['block_if_written_off_history_exists'] ?? false)) class="rounded border-slate-300"> Block graduation if written-off history exists</label>
                                </div>
                                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save Graduation Logic</button>
                            </form>
                        </div>
                    </div>

                    <div id="required-approvals" class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="text-base font-semibold text-slate-900">D. Required Approvals</h3>
                        <form method="post" action="{{ route('loan.system.form_setup.page.save', ['page' => 'loan-settings', 'tab' => 'product-rules']) }}" class="mt-3 space-y-4">
                            @csrf
                            <input type="hidden" name="section" value="required_approvals">
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-700">
                                        <tr>
                                            <th class="px-3 py-2 text-left">Amount From</th>
                                            <th class="px-3 py-2 text-left">Amount To</th>
                                            <th class="px-3 py-2 text-left">Approver</th>
                                            <th class="px-3 py-2 text-left">Risk Level</th>
                                            <th class="px-3 py-2 text-left">Disbursement Approval</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($requiredApprovals as $i => $row)
                                            <tr class="border-t border-slate-100">
                                                <td class="px-3 py-2"><input name="approval_rows[{{ $i }}][amount_from]" value="{{ $row['amount_from'] ?? '' }}" class="w-32 rounded border-slate-200 text-sm"></td>
                                                <td class="px-3 py-2"><input name="approval_rows[{{ $i }}][amount_to]" value="{{ $row['amount_to'] ?? '' }}" class="w-32 rounded border-slate-200 text-sm"></td>
                                                <td class="px-3 py-2"><input name="approval_rows[{{ $i }}][approver]" value="{{ $row['approver'] ?? '' }}" class="w-44 rounded border-slate-200 text-sm"></td>
                                                <td class="px-3 py-2"><input name="approval_rows[{{ $i }}][risk_level]" value="{{ $row['risk_level'] ?? '' }}" class="w-32 rounded border-slate-200 text-sm"></td>
                                                <td class="px-3 py-2">
                                                    <input type="hidden" name="approval_rows[{{ $i }}][disbursement_approval]" value="0">
                                                    <input type="checkbox" name="approval_rows[{{ $i }}][disbursement_approval]" value="1" @checked(($row['disbursement_approval'] ?? false)) class="rounded border-slate-300">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-semibold text-slate-900">E. Additional Product Settings</h4>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <input name="arrears_tolerance_days" value="{{ $additionalProductSettings['arrears_tolerance_days'] ?? '' }}" class="rounded-lg border-slate-200 text-sm" placeholder="Arrears Tolerance Days">
                                    <label class="text-sm"><input type="checkbox" name="penalty_on_arrears" value="1" @checked(($additionalProductSettings['penalty_on_arrears'] ?? false)) class="rounded border-slate-300"> Penalty On Arrears</label>
                                    <label class="text-sm"><input type="checkbox" name="interest_recalculation" value="1" @checked(($additionalProductSettings['interest_recalculation'] ?? false)) class="rounded border-slate-300"> Interest Recalculation</label>
                                    <label class="text-sm"><input type="checkbox" name="allow_top_up" value="1" @checked(($additionalProductSettings['allow_top_up'] ?? false)) class="rounded border-slate-300"> Allow Top-up</label>
                                    <label class="text-sm"><input type="checkbox" name="allow_early_repayment" value="1" @checked(($additionalProductSettings['allow_early_repayment'] ?? false)) class="rounded border-slate-300"> Allow Early Repayment</label>
                                    <label class="text-sm"><input type="checkbox" name="auto_approval_low_risk" value="1" @checked(($additionalProductSettings['auto_approval_low_risk'] ?? false)) class="rounded border-slate-300"> Auto Approval Low Risk</label>
                                </div>
                            </div>

                            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Save Required Approvals & Product Settings</button>
                        </form>
                    </div>
                </div>
        </div>
    </x-loan.page>
</x-loan-layout>

<script>
    (() => {
        const tabTargets = {
            'product-rules': 'loan-form-setup',
            'approval-matrix': 'required-approvals',
            'disbursement-controls': 'required-approvals',
            'eligibility-affordability': 'eligibility-rules',
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

        const currentTab = @json((string) request('tab', 'product-rules'));
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

        const previewHandler = () => {
            document.getElementById('loan-form-setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            const firstInput = document.querySelector('#loan-settings-primary-form input[name="fields[0][label]"]');
            firstInput?.focus();
        };

        const cloneHandler = () => {
            const rows = document.querySelectorAll('#loan-settings-primary-form tbody tr');
            if (!rows.length) {
                return;
            }
            alert('Form rows are already editable and clonable by adding new rows. Use "Add Custom Field" and save.');
            document.getElementById('loan-form-setup')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        window.addEventListener('loan-settings-preview-form', previewHandler);
        window.addEventListener('loan-settings-clone-form', cloneHandler);
    })();
</script>
