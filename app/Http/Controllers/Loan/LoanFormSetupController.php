<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanFormFieldDefinition;
use App\Models\LoanProduct;
use App\Models\LoanSystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanFormSetupController extends Controller
{
    public const FORM_SETUP_PAGE_PATTERN = 'access|loan-products|leave-settings|client-biodata|group-lending|accounting-forms|staff-leaves|staff-structure|staff-performance|loan-settings';

    public function setupPage(Request $request, string $page): View|Response
    {
        if ($page === 'loan-settings') {
            if ($request->boolean('export')) {
                return $this->exportLoanSettingsPage();
            }
            return $this->renderLoanSettingsPage();
        }

        $cfg = $this->setupPageConfig($page);

        if ($page === 'accounting-forms') {
            $approvalEnabled = LoanSystemSetting::getValue('coa_approval_required', '0') === '1';
            $rawWorkflow = LoanSystemSetting::getValue('coa_approval_workflow', '[]') ?? '[]';
            $workflow = collect(json_decode($rawWorkflow, true) ?: [])
                ->map(function (array $row): array {
                    return [
                        'sequence' => (int) ($row['sequence'] ?? 0),
                        'user_id' => (int) ($row['user_id'] ?? 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['sequence'] > 0 && $row['user_id'] > 0)
                ->sortBy('sequence')
                ->values()
                ->all();

            $mappingGovernanceRaw = LoanSystemSetting::getValue('accounting_mapping_governance', '{}') ?? '{}';
            $mappingGovernance = json_decode($mappingGovernanceRaw, true);
            if (! is_array($mappingGovernance)) {
                $mappingGovernance = [];
            }

            $mappingPermissions = [
                'create' => collect(data_get($mappingGovernance, 'permissions.create', []))->map(fn ($id) => (int) $id)->filter()->values()->all(),
                'edit' => collect(data_get($mappingGovernance, 'permissions.edit', []))->map(fn ($id) => (int) $id)->filter()->values()->all(),
                'delete' => collect(data_get($mappingGovernance, 'permissions.delete', []))->map(fn ($id) => (int) $id)->filter()->values()->all(),
            ];
            $mappingApprovalMode = (string) data_get($mappingGovernance, 'approval.mode', 'none');
            if (! in_array($mappingApprovalMode, ['none', 'single', 'multi'], true)) {
                $mappingApprovalMode = 'none';
            }
            $mappingApproverRows = collect(data_get($mappingGovernance, 'approval.approvers', []))
                ->map(function (array $row): array {
                    return ['user_id' => (int) ($row['user_id'] ?? 0)];
                })
                ->filter(fn (array $row): bool => $row['user_id'] > 0)
                ->values()
                ->all();
            $controlledAccountOwners = collect(data_get($mappingGovernance, 'controlled_account_owner_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
            $sensitivityThreshold = (float) data_get($mappingGovernance, 'sensitivity.amount_threshold', 0);
            $sensitivityRules = [
                'high_amount' => (bool) data_get($mappingGovernance, 'sensitivity.high_amount', false),
                'critical_accounts_only' => (bool) data_get($mappingGovernance, 'sensitivity.critical_accounts_only', false),
                'reversal_events' => (bool) data_get($mappingGovernance, 'sensitivity.reversal_events', false),
            ];

            $users = User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            return view('loan.system.setup.accounting_setup', [
                'title' => 'Accounting Setup',
                'subtitle' => 'Define the rules that govern revenue recognition, ledger posting, liquidity protection, taxation, approvals, and period closure.',
                'backUrl' => route('loan.system.setup'),
                'booksUrl' => route('loan.accounting.books'),
                'approvalEnabled' => $approvalEnabled,
                'coaApproverWorkflow' => $workflow,
                'availableApprovers' => $users,
                'formActionUrl' => route('loan.system.form_setup.page.save', ['page' => $page]),
                'mappingPermissions' => $mappingPermissions,
                'mappingApprovalMode' => $mappingApprovalMode,
                'mappingApproverRows' => $mappingApproverRows,
                'controlledAccountOwnerIds' => $controlledAccountOwners,
                'sensitivityThreshold' => $sensitivityThreshold,
                'sensitivityRules' => $sensitivityRules,
            ]);
        }

        return $this->renderForm(
            $cfg['kind'],
            $cfg['title'],
            $cfg['subtitle'],
            'loan.system.form_setup.page.save',
            array_merge($cfg['options'], [
                'formActionUrl' => route('loan.system.form_setup.page.save', ['page' => $page]),
            ])
        );
    }

    public function setupPageSave(Request $request, string $page): RedirectResponse
    {
        if ($page === 'loan-settings') {
            return $this->saveLoanSettingsPage($request);
        }

        if ($page === 'accounting-forms') {
            $enabled = $request->boolean('coa_approval_required');
            $mappingApprovalMode = (string) $request->input('mapping_approval_mode', 'none');
            $validated = $request->validate([
                'coa_approval_required' => ['nullable', 'in:0,1'],
                'approvers' => [$enabled ? 'required' : 'nullable', 'array', 'max:8'],
                'approvers.*.user_id' => [$enabled ? 'required' : 'nullable', 'integer', 'exists:users,id'],
                'mapping_permissions' => ['nullable', 'array'],
                'mapping_permissions.create' => ['nullable', 'array'],
                'mapping_permissions.create.*' => ['integer', 'exists:users,id'],
                'mapping_permissions.edit' => ['nullable', 'array'],
                'mapping_permissions.edit.*' => ['integer', 'exists:users,id'],
                'mapping_permissions.delete' => ['nullable', 'array'],
                'mapping_permissions.delete.*' => ['integer', 'exists:users,id'],
                'mapping_approval_mode' => ['required', Rule::in(['none', 'single', 'multi'])],
                'mapping_approvers' => [$mappingApprovalMode === 'none' ? 'nullable' : 'required', 'array', 'max:8'],
                'mapping_approvers.*.user_id' => [$mappingApprovalMode === 'none' ? 'nullable' : 'required', 'integer', 'exists:users,id'],
                'controlled_account_owner_ids' => ['nullable', 'array'],
                'controlled_account_owner_ids.*' => ['integer', 'exists:users,id'],
                'sensitivity_high_amount' => ['nullable', 'in:0,1'],
                'sensitivity_critical_accounts_only' => ['nullable', 'in:0,1'],
                'sensitivity_reversal_events' => ['nullable', 'in:0,1'],
                'sensitivity_amount_threshold' => ['nullable', 'numeric', 'min:0'],
            ]);

            $rows = collect($validated['approvers'] ?? [])
                ->map(function (array $row, int $index): array {
                    return [
                        'sequence' => $index + 1,
                        'user_id' => (int) ($row['user_id'] ?? 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['user_id'] > 0)
                ->values();

            if ($enabled && $rows->isEmpty()) {
                throw ValidationException::withMessages([
                    'approvers' => 'Add at least one approver when approval control is enabled.',
                ]);
            }
            if ($rows->pluck('user_id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'approvers' => 'Each approver can only appear once in the sequence.',
                ]);
            }

            $mappingApprovers = collect($validated['mapping_approvers'] ?? [])
                ->map(function (array $row, int $index): array {
                    return [
                        'sequence' => $index + 1,
                        'user_id' => (int) ($row['user_id'] ?? 0),
                    ];
                })
                ->filter(fn (array $row): bool => $row['user_id'] > 0)
                ->values();

            if ($mappingApprovalMode === 'single' && $mappingApprovers->count() !== 1) {
                throw ValidationException::withMessages([
                    'mapping_approvers' => 'Single approver mode requires exactly one approver.',
                ]);
            }
            if ($mappingApprovalMode === 'multi' && $mappingApprovers->count() < 2) {
                throw ValidationException::withMessages([
                    'mapping_approvers' => 'Multi-level approval requires at least two approvers.',
                ]);
            }
            if ($mappingApprovers->pluck('user_id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'mapping_approvers' => 'Each mapping approver can only appear once in the sequence.',
                ]);
            }

            LoanSystemSetting::setValue(
                'coa_approval_required',
                $enabled ? '1' : '0',
                'Require approval before newly created accounts become active',
                'accounting'
            );
            LoanSystemSetting::setValue(
                'coa_approval_workflow',
                json_encode($rows->all()),
                'Chart of accounts approval workflow',
                'accounting'
            );
            LoanSystemSetting::setValue(
                'accounting_mapping_governance',
                json_encode([
                    'permissions' => [
                        'create' => array_values(array_unique(array_map('intval', data_get($validated, 'mapping_permissions.create', [])))),
                        'edit' => array_values(array_unique(array_map('intval', data_get($validated, 'mapping_permissions.edit', [])))),
                        'delete' => array_values(array_unique(array_map('intval', data_get($validated, 'mapping_permissions.delete', [])))),
                    ],
                    'approval' => [
                        'mode' => $mappingApprovalMode,
                        'approvers' => $mappingApprovers->values()->all(),
                    ],
                    'controlled_account_owner_ids' => array_values(array_unique(array_map('intval', $validated['controlled_account_owner_ids'] ?? []))),
                    'sensitivity' => [
                        'high_amount' => $request->boolean('sensitivity_high_amount'),
                        'critical_accounts_only' => $request->boolean('sensitivity_critical_accounts_only'),
                        'reversal_events' => $request->boolean('sensitivity_reversal_events'),
                        'amount_threshold' => (float) ($validated['sensitivity_amount_threshold'] ?? 0),
                    ],
                ]),
                'Automated mapping governance controls',
                'accounting'
            );

            return redirect()
                ->route('loan.system.form_setup.page', ['page' => $page])
                ->with('status', 'Accounting governance controls saved.');
        }

        $cfg = $this->setupPageConfig($page);

        return $this->saveForm(
            $request,
            $cfg['kind'],
            'loan.system.form_setup.page',
            $cfg['success'],
            ['page' => $page]
        );
    }

    /**
     * @return array{kind: string, title: string, subtitle: string, success: string, options: array<string, mixed>}
     */
    private function setupPageConfig(string $page): array
    {
        $hub = route('loan.system.setup');

        $map = [
            'access' => [
                'kind' => LoanFormFieldDefinition::KIND_SYSTEM_ACCESS,
                'title' => 'System access & user roles',
                'subtitle' => 'Design fields for access, OTP, and role metadata.',
                'success' => 'Access setup fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'showPrefillColumn' => false,
                    'introText' => 'Define what your team captures when documenting user access, roles, and login policies. These definitions can be wired to live screens later.',
                ],
            ],
            'loan-products' => [
                'kind' => LoanFormFieldDefinition::KIND_LOAN_PRODUCTS,
                'title' => 'Loan products form',
                'subtitle' => 'Fields for product definitions, rates, and terms.',
                'success' => 'Loan product fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'showPrefillColumn' => false,
                    'introText' => 'Shape the data you want recorded for each loan product (names, pricing, limits). Aligns with LoanBook product setup when you connect it.',
                ],
            ],
            'leave-settings' => [
                'kind' => LoanFormFieldDefinition::KIND_LEAVE_WORKFLOW,
                'title' => 'Leave workflow setup',
                'subtitle' => 'Approval chains, SLAs, and leave rules.',
                'success' => 'Leave workflow fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'showPrefillColumn' => false,
                    'introText' => 'Capture how approvals flow (steps, roles, attachments). Operational leave screens can read these definitions when integrated.',
                ],
            ],
            'client-biodata' => [
                'kind' => LoanFormFieldDefinition::KIND_CLIENT_BIODATA,
                'title' => 'Client bio-data form',
                'subtitle' => 'Registration and KYC field layout.',
                'success' => 'Client bio-data fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'showPrefillColumn' => true,
                    'introText' => 'Design mandatory and optional client registration fields. Use “Prior data” on custom rows if you later pre-fill from an existing client profile.',
                ],
            ],
            'group-lending' => [
                'kind' => LoanFormFieldDefinition::KIND_GROUP_LENDING,
                'title' => 'Group lending form',
                'subtitle' => 'Client groups and group-level details.',
                'success' => 'Group lending fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'settingsUrl' => route('loan.clients.default_groups'),
                    'settingsLabel' => 'Group directory',
                    'showPrefillColumn' => false,
                    'introText' => 'Define fields for group onboarding and structure. Open Group directory to manage existing groups.',
                ],
            ],
            'accounting-forms' => [
                'kind' => LoanFormFieldDefinition::KIND_ACCOUNTING_FORMS,
                'title' => 'Accounting & requisition forms',
                'subtitle' => 'Petty cash, requisitions, and supporting data.',
                'success' => 'Accounting form fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'settingsUrl' => route('loan.accounting.books'),
                    'settingsLabel' => 'Accounting books',
                    'showPrefillColumn' => false,
                    'introText' => 'Capture the fields staff should fill on requisitions and related accounting forms. Open Accounting books for day-to-day entries.',
                ],
            ],
            'staff-leaves' => [
                'kind' => LoanFormFieldDefinition::KIND_STAFF_LEAVE_APPLICATION,
                'title' => 'Staff leave application form',
                'subtitle' => 'Leave requests, dates, and handover.',
                'success' => 'Staff leave form fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'settingsUrl' => route('loan.employees.leaves'),
                    'settingsLabel' => 'Leave records',
                    'showPrefillColumn' => false,
                    'introText' => 'Design the leave application capture form. Open Leave records to view and approve requests.',
                ],
            ],
            'staff-structure' => [
                'kind' => LoanFormFieldDefinition::KIND_STAFF_STRUCTURE,
                'title' => 'Staff structure form',
                'subtitle' => 'Hierarchy, roles, and staff details.',
                'success' => 'Staff structure fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'settingsUrl' => route('loan.employees.groups'),
                    'settingsLabel' => 'Staff directory',
                    'showPrefillColumn' => false,
                    'introText' => 'Define fields for org structure and staff profiles. Open Staff directory for groups and assignments.',
                ],
            ],
            'staff-performance' => [
                'kind' => LoanFormFieldDefinition::KIND_STAFF_PERFORMANCE,
                'title' => 'Staff performance form',
                'subtitle' => 'KPIs, targets, and review periods.',
                'success' => 'Performance form fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'settingsUrl' => route('loan.analytics.performance'),
                    'settingsLabel' => 'Performance records',
                    'showPrefillColumn' => false,
                    'introText' => 'Shape how performance indicators are recorded. Open Performance records for existing KPI entries.',
                ],
            ],
            'loan-settings' => [
                'kind' => LoanFormFieldDefinition::KIND_LOAN_POLICY,
                'title' => 'Loan policy & settings form',
                'subtitle' => 'Checkoffs, reschedule rules, and policy text.',
                'success' => 'Loan policy fields saved.',
                'options' => [
                    'backUrl' => $hub,
                    'showPrefillColumn' => false,
                    'introText' => 'Capture policy parameters and narrative fields your portfolio team should record (checkoffs, penalties, write-offs, etc.).',
                ],
            ],
        ];

        if (! isset($map[$page])) {
            abort(404);
        }

        return $map[$page];
    }

    public function clientForm(): View
    {
        return $this->renderForm(
            LoanFormFieldDefinition::KIND_CLIENT_LOAN,
            'Client loan form setup',
            'Design fields captured on the client loan application.',
            'loan.system.form_setup.client.save',
            [
                'alternateSetupUrl' => route('loan.system.form_setup.staff'),
                'alternateSetupLabel' => 'Setup staff loan form',
            ]
        );
    }

    public function clientFormSave(Request $request): RedirectResponse
    {
        return $this->saveForm(
            $request,
            LoanFormFieldDefinition::KIND_CLIENT_LOAN,
            'loan.system.form_setup.client',
            'Loan form fields saved.'
        );
    }

    public function staffForm(): View
    {
        return $this->renderForm(
            LoanFormFieldDefinition::KIND_STAFF_LOAN,
            'Staff loan form setup',
            'Design fields captured on internal staff loan requests.',
            'loan.system.form_setup.staff.save',
            [
                'alternateSetupUrl' => route('loan.system.form_setup.client'),
                'alternateSetupLabel' => 'Setup client loan form',
            ]
        );
    }

    public function staffFormSave(Request $request): RedirectResponse
    {
        return $this->saveForm(
            $request,
            LoanFormFieldDefinition::KIND_STAFF_LOAN,
            'loan.system.form_setup.staff',
            'Loan form fields saved.'
        );
    }

    public function salaryAdvanceForm(): View
    {
        return $this->renderForm(
            LoanFormFieldDefinition::KIND_SALARY_ADVANCE,
            'Salary advance form setup',
            'Create form structure to capture information for storage.',
            'loan.system.form_setup.salary_advance.save',
            [
                'backUrl' => route('loan.accounting.advances.index'),
                'backLabel' => 'Back',
                'settingsUrl' => route('loan.system.setup'),
                'showPrefillColumn' => false,
                'introText' => 'Create form structure to capture information for storage.',
            ]
        );
    }

    public function salaryAdvanceFormSave(Request $request): RedirectResponse
    {
        return $this->saveForm(
            $request,
            LoanFormFieldDefinition::KIND_SALARY_ADVANCE,
            'loan.system.form_setup.salary_advance',
            'Salary advance form saved.'
        );
    }

    private function renderLoanSettingsPage(): View
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION);

        $fields = LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $fieldsPayload = $fields->map(fn (LoanFormFieldDefinition $f) => [
            'id' => $f->id,
            'label' => $f->label,
            'field_key' => $f->field_key,
            'data_type' => $f->data_type,
            'is_required' => (bool) ($f->is_required ?? false),
            'select_options' => (string) ($f->select_options ?? ''),
            'prefill_from_previous' => (bool) $f->prefill_from_previous,
            'visible_to' => (string) ($f->visible_to ?? ''),
            'field_status' => (string) ($f->field_status ?? ($f->is_core ? 'active' : 'draft')),
            'product_id' => $f->product_id !== null ? (string) $f->product_id : '',
            'is_core' => (bool) $f->is_core,
        ])->values()->all();

        $productsPendingLoanFormSetup = Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')
            ? LoanProduct::query()
                ->whereNull('loan_form_setup_completed_at')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $productsPendingForJs = $productsPendingLoanFormSetup
            ->map(fn (LoanProduct $p): array => ['id' => (int) $p->id, 'name' => (string) $p->name])
            ->values()
            ->all();

        return view('loan.system.form_setup.loan_settings', [
            'title' => 'Loan settings',
            'subtitle' => 'Configure product rules, approval controls, affordability checks, disbursement safety, and lending risk limits.',
            'backUrl' => route('loan.system.setup'),
            'products' => LoanProduct::query()->orderBy('name')->get(['id', 'name']),
            'productsPendingLoanFormSetup' => $productsPendingLoanFormSetup,
            'productsPendingForJs' => $productsPendingForJs,
            'activeProductsCount' => LoanProduct::query()->where('is_active', true)->count(),
            'fieldsPayload' => $fieldsPayload,
            'dataTypeLabels' => LoanFormFieldDefinition::dataTypeLabels(),
            'eligibilityRules' => $this->normalizeEligibilityRules($this->jsonSetting('loan_settings_eligibility_rules', [])),
            'graduationRules' => $this->normalizeGraduationRules($this->jsonSetting('loan_settings_graduation_rules', [])),
            'requiredApprovals' => $this->normalizeApprovalRules($this->jsonSetting('loan_settings_required_approvals', [])),
            'additionalProductSettings' => $this->normalizeAdditionalProductSettings($this->jsonSetting('loan_settings_additional_product_settings', [])),
        ]);
    }

    private function exportLoanSettingsPage(): Response
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION);

        $fields = LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LoanFormFieldDefinition $f): array => [
                'label' => (string) $f->label,
                'field_key' => (string) $f->field_key,
                'data_type' => (string) $f->data_type,
                'is_required' => (bool) ($f->is_required ?? false),
                'select_options' => (string) ($f->select_options ?? ''),
                'visible_to' => (string) ($f->visible_to ?? ''),
                'field_status' => (string) ($f->field_status ?? 'active'),
            ])
            ->values()
            ->all();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'loan_form_setup' => $fields,
            'eligibility_rules' => $this->normalizeEligibilityRules($this->jsonSetting('loan_settings_eligibility_rules', [])),
            'graduation_logic' => $this->normalizeGraduationRules($this->jsonSetting('loan_settings_graduation_rules', [])),
            'required_approvals' => $this->normalizeApprovalRules($this->jsonSetting('loan_settings_required_approvals', [])),
            'additional_product_settings' => $this->normalizeAdditionalProductSettings($this->jsonSetting('loan_settings_additional_product_settings', [])),
        ];

        return response(
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="loan-policy-export.json"',
            ]
        );
    }

    private function saveLoanSettingsPage(Request $request): RedirectResponse
    {
        $section = (string) $request->input('section', 'loan_form_setup');

        if ($section === 'loan_form_setup') {
            return $this->saveForm(
                $request,
                LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION,
                'loan.system.form_setup.page',
                'Loan form setup saved.',
                ['page' => 'loan-settings', 'tab' => 'product-rules']
            );
        }

        if ($section === 'eligibility_rules') {
            $validated = $request->validate([
                'minimum_age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'maximum_age' => ['nullable', 'integer', 'min:0', 'max:150'],
                'allowed_client_types' => ['nullable', 'string', 'max:5000'],
                'required_documents' => ['nullable', 'string', 'max:5000'],
                'allowed_sectors' => ['nullable', 'string', 'max:5000'],
                'blocked_sectors' => ['nullable', 'string', 'max:5000'],
                'minimum_repayment_history' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'minimum_client_score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'guarantor_required' => ['nullable', 'in:0,1'],
                'collateral_required' => ['nullable', 'in:0,1'],
                'block_with_arrears' => ['nullable', 'in:0,1'],
                'block_written_off_history' => ['nullable', 'in:0,1'],
                'active_loan_limit' => ['nullable', 'integer', 'min:0', 'max:100'],
            ]);
            $payload = [
                'minimum_age' => isset($validated['minimum_age']) ? (int) $validated['minimum_age'] : null,
                'maximum_age' => isset($validated['maximum_age']) ? (int) $validated['maximum_age'] : null,
                'allowed_client_types' => $this->csvToList((string) ($validated['allowed_client_types'] ?? '')),
                'required_documents' => $this->csvToList((string) ($validated['required_documents'] ?? '')),
                'allowed_sectors' => $this->csvToList((string) ($validated['allowed_sectors'] ?? '')),
                'blocked_sectors' => $this->csvToList((string) ($validated['blocked_sectors'] ?? '')),
                'minimum_repayment_history' => isset($validated['minimum_repayment_history']) ? (int) $validated['minimum_repayment_history'] : null,
                'minimum_client_score' => isset($validated['minimum_client_score']) ? (float) $validated['minimum_client_score'] : null,
                'guarantor_required' => $request->boolean('guarantor_required'),
                'collateral_required' => $request->boolean('collateral_required'),
                'block_with_arrears' => $request->boolean('block_with_arrears'),
                'block_written_off_history' => $request->boolean('block_written_off_history'),
                'active_loan_limit' => isset($validated['active_loan_limit']) ? (int) $validated['active_loan_limit'] : null,
            ];
            LoanSystemSetting::setValue(
                'loan_settings_eligibility_rules',
                json_encode($payload),
                'Loan settings eligibility rules',
                'loan_settings'
            );

            return redirect()
                ->route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => 'eligibility-rules'])
                ->with('status', 'Eligibility rules saved.');
        }

        if ($section === 'graduation_logic') {
            $validated = $request->validate([
                'first_loan_max_limit' => ['nullable', 'numeric', 'min:0'],
                'second_loan_max_limit' => ['nullable', 'numeric', 'min:0'],
                'subsequent_increase_pct' => ['nullable', 'numeric', 'min:0', 'max:1000'],
                'increase_after_full_payment_only' => ['nullable', 'in:0,1'],
                'block_if_arrears_exist' => ['nullable', 'in:0,1'],
                'block_if_late_payment_exists' => ['nullable', 'in:0,1'],
                'block_if_written_off_history_exists' => ['nullable', 'in:0,1'],
                'reduce_limit_after_default_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ]);
            $payload = [
                'first_loan_max_limit' => isset($validated['first_loan_max_limit']) ? (float) $validated['first_loan_max_limit'] : null,
                'second_loan_max_limit' => isset($validated['second_loan_max_limit']) ? (float) $validated['second_loan_max_limit'] : null,
                'subsequent_increase_pct' => isset($validated['subsequent_increase_pct']) ? (float) $validated['subsequent_increase_pct'] : null,
                'increase_after_full_payment_only' => $request->boolean('increase_after_full_payment_only'),
                'block_if_arrears_exist' => $request->boolean('block_if_arrears_exist'),
                'block_if_late_payment_exists' => $request->boolean('block_if_late_payment_exists'),
                'block_if_written_off_history_exists' => $request->boolean('block_if_written_off_history_exists'),
                'reduce_limit_after_default_pct' => isset($validated['reduce_limit_after_default_pct']) ? (float) $validated['reduce_limit_after_default_pct'] : null,
            ];
            LoanSystemSetting::setValue(
                'loan_settings_graduation_rules',
                json_encode($payload),
                'Loan settings graduation rules',
                'loan_settings'
            );

            return redirect()
                ->route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => 'graduation-logic'])
                ->with('status', 'Graduation logic saved.');
        }

        if ($section === 'required_approvals') {
            $validated = $request->validate([
                'approval_rows' => ['nullable', 'array'],
                'approval_rows.*.amount_from' => ['nullable', 'numeric', 'min:0'],
                'approval_rows.*.amount_to' => ['nullable', 'numeric', 'min:0'],
                'approval_rows.*.approver' => ['nullable', 'string', 'max:120'],
                'approval_rows.*.risk_level' => ['nullable', 'string', 'max:80'],
                'approval_rows.*.disbursement_approval' => ['nullable', 'in:0,1'],
                'arrears_tolerance_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'penalty_on_arrears' => ['nullable', 'in:0,1'],
                'interest_recalculation' => ['nullable', 'in:0,1'],
                'allow_top_up' => ['nullable', 'in:0,1'],
                'allow_early_repayment' => ['nullable', 'in:0,1'],
                'auto_approval_low_risk' => ['nullable', 'in:0,1'],
            ]);
            $approvalRows = collect($validated['approval_rows'] ?? [])
                ->map(function (array $row): array {
                    return [
                        'amount_from' => isset($row['amount_from']) ? (float) $row['amount_from'] : null,
                        'amount_to' => isset($row['amount_to']) && $row['amount_to'] !== '' ? (float) $row['amount_to'] : null,
                        'approver' => trim((string) ($row['approver'] ?? '')),
                        'risk_level' => trim((string) ($row['risk_level'] ?? '')),
                        'disbursement_approval' => isset($row['disbursement_approval']) && (string) $row['disbursement_approval'] === '1',
                    ];
                })
                ->values()
                ->all();
            LoanSystemSetting::setValue(
                'loan_settings_required_approvals',
                json_encode($approvalRows),
                'Loan settings required approvals',
                'loan_settings'
            );
            LoanSystemSetting::setValue(
                'loan_settings_additional_product_settings',
                json_encode([
                    'arrears_tolerance_days' => isset($validated['arrears_tolerance_days']) ? (int) $validated['arrears_tolerance_days'] : null,
                    'penalty_on_arrears' => $request->boolean('penalty_on_arrears'),
                    'interest_recalculation' => $request->boolean('interest_recalculation'),
                    'allow_top_up' => $request->boolean('allow_top_up'),
                    'allow_early_repayment' => $request->boolean('allow_early_repayment'),
                    'auto_approval_low_risk' => $request->boolean('auto_approval_low_risk'),
                ]),
                'Loan settings additional product settings',
                'loan_settings'
            );

            return redirect()
                ->route('loan.system.form_setup.page', ['page' => 'loan-settings', 'tab' => 'required-approvals'])
                ->with('status', 'Required approvals saved.');
        }

        return redirect()
            ->route('loan.system.form_setup.page', ['page' => 'loan-settings'])
            ->with('status', 'No changes saved.');
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonSetting(string $key, array $fallback): array
    {
        $raw = LoanSystemSetting::getValue($key, json_encode($fallback));
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : $fallback;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function normalizeEligibilityRules(mixed $raw): array
    {
        $arr = is_array($raw) ? $raw : [];

        return [
            'minimum_age' => data_get($arr, 'minimum_age'),
            'maximum_age' => data_get($arr, 'maximum_age'),
            'allowed_client_types' => is_array(data_get($arr, 'allowed_client_types')) ? data_get($arr, 'allowed_client_types') : [],
            'required_documents' => is_array(data_get($arr, 'required_documents')) ? data_get($arr, 'required_documents') : [],
            'allowed_sectors' => is_array(data_get($arr, 'allowed_sectors')) ? data_get($arr, 'allowed_sectors') : [],
            'blocked_sectors' => is_array(data_get($arr, 'blocked_sectors')) ? data_get($arr, 'blocked_sectors') : [],
            'minimum_repayment_history' => data_get($arr, 'minimum_repayment_history'),
            'minimum_client_score' => data_get($arr, 'minimum_client_score'),
            'guarantor_required' => (bool) data_get($arr, 'guarantor_required', false),
            'collateral_required' => (bool) data_get($arr, 'collateral_required', false),
            'block_with_arrears' => (bool) data_get($arr, 'block_with_arrears', false),
            'block_written_off_history' => (bool) data_get($arr, 'block_written_off_history', false),
            'active_loan_limit' => data_get($arr, 'active_loan_limit'),
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function normalizeGraduationRules(mixed $raw): array
    {
        $arr = is_array($raw) ? $raw : [];

        return [
            'first_loan_max_limit' => data_get($arr, 'first_loan_max_limit'),
            'second_loan_max_limit' => data_get($arr, 'second_loan_max_limit'),
            'subsequent_increase_pct' => data_get($arr, 'subsequent_increase_pct'),
            'increase_after_full_payment_only' => (bool) data_get($arr, 'increase_after_full_payment_only', false),
            'block_if_arrears_exist' => (bool) data_get($arr, 'block_if_arrears_exist', false),
            'block_if_late_payment_exists' => (bool) data_get($arr, 'block_if_late_payment_exists', false),
            'block_if_written_off_history_exists' => (bool) data_get($arr, 'block_if_written_off_history_exists', false),
            'reduce_limit_after_default_pct' => data_get($arr, 'reduce_limit_after_default_pct'),
        ];
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array<string, mixed>>
     */
    private function normalizeApprovalRules(mixed $raw): array
    {
        $arr = is_array($raw) ? $raw : [];
        if ($arr === []) {
            return [
                ['amount_from' => 0, 'amount_to' => 50000, 'approver' => 'Branch Manager', 'risk_level' => 'Low risk', 'disbursement_approval' => true],
                ['amount_from' => 50001, 'amount_to' => 200000, 'approver' => 'Regional Manager', 'risk_level' => 'Medium risk', 'disbursement_approval' => true],
                ['amount_from' => 200001, 'amount_to' => 500000, 'approver' => 'Credit Manager', 'risk_level' => 'High risk', 'disbursement_approval' => true],
                ['amount_from' => 500001, 'amount_to' => null, 'approver' => 'Director', 'risk_level' => 'High risk', 'disbursement_approval' => true],
            ];
        }

        return collect($arr)->map(function ($row): array {
            $r = is_array($row) ? $row : [];

            return [
                'amount_from' => data_get($r, 'amount_from'),
                'amount_to' => data_get($r, 'amount_to'),
                'approver' => (string) data_get($r, 'approver', ''),
                'risk_level' => (string) data_get($r, 'risk_level', ''),
                'disbursement_approval' => (bool) data_get($r, 'disbursement_approval', false),
            ];
        })->values()->all();
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function normalizeAdditionalProductSettings(mixed $raw): array
    {
        $arr = is_array($raw) ? $raw : [];

        return [
            'arrears_tolerance_days' => data_get($arr, 'arrears_tolerance_days'),
            'penalty_on_arrears' => (bool) data_get($arr, 'penalty_on_arrears', false),
            'interest_recalculation' => (bool) data_get($arr, 'interest_recalculation', false),
            'allow_top_up' => (bool) data_get($arr, 'allow_top_up', false),
            'allow_early_repayment' => (bool) data_get($arr, 'allow_early_repayment', false),
            'auto_approval_low_risk' => (bool) data_get($arr, 'auto_approval_low_risk', false),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function csvToList(string $raw): array
    {
        return collect(explode(',', $raw))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function renderForm(string $kind, string $title, string $subtitle, string $saveRoute, array $options = []): View
    {
        $defaults = [
            'backUrl' => route('loan.system.setup'),
            'backLabel' => 'System setup',
            'settingsUrl' => null,
            'settingsLabel' => 'Settings',
            'alternateSetupUrl' => null,
            'alternateSetupLabel' => null,
            'showPrefillColumn' => true,
            'introText' => 'Design your loan form to allow loan-template creation. Use the left checkbox on custom fields if you want the field to reuse data from an existing application when one is available.',
            'formActionUrl' => null,
        ];
        $options = array_merge($defaults, $options);

        LoanFormFieldDefinition::ensureDefaults($kind);

        $fields = LoanFormFieldDefinition::query()
            ->where('form_kind', $kind)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $fieldsPayload = $fields->map(fn (LoanFormFieldDefinition $f) => [
            'id' => $f->id,
            'label' => $f->label,
            'data_type' => $f->data_type,
            'select_options' => (string) ($f->select_options ?? ''),
            'prefill_from_previous' => (bool) $f->prefill_from_previous,
            'is_core' => (bool) $f->is_core,
        ])->values()->all();

        return view('loan.system.form_setup.edit', [
            'title' => $title,
            'subtitle' => $subtitle,
            'formKind' => $kind,
            'fields' => $fields,
            'fieldsPayload' => $fieldsPayload,
            'dataTypeLabels' => LoanFormFieldDefinition::dataTypeLabels(),
            'saveRoute' => $saveRoute,
            'backUrl' => $options['backUrl'],
            'backLabel' => $options['backLabel'],
            'settingsUrl' => $options['settingsUrl'],
            'settingsLabel' => $options['settingsLabel'],
            'alternateSetupUrl' => $options['alternateSetupUrl'],
            'alternateSetupLabel' => $options['alternateSetupLabel'],
            'showPrefillColumn' => (bool) $options['showPrefillColumn'],
            'introText' => $options['introText'],
            'formActionUrl' => $options['formActionUrl'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $redirectRouteParams
     */
    private function saveForm(Request $request, string $kind, string $redirectRoute, string $successMessage, array $redirectRouteParams = []): RedirectResponse
    {
        $types = array_keys(LoanFormFieldDefinition::dataTypeLabels());

        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.data_type' => ['required', 'string', Rule::in($types)],
            'fields.*.is_required' => ['nullable', 'in:0,1'],
            'fields.*.select_options' => ['nullable', 'string', 'max:10000'],
            'fields.*.prefill_from_previous' => ['nullable', 'in:0,1'],
            'fields.*.visible_to' => ['nullable', 'string', 'max:255'],
            'fields.*.field_status' => ['nullable', Rule::in(['active', 'draft', 'requires_approval'])],
            'complete_loan_form_setup_product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
        ]);

        if ($kind === LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION) {
            $this->assertPendingLoanFormProduct($request);
        }

        foreach ($validated['fields'] as $i => $row) {
            if ($row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
                && trim((string) ($row['select_options'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "fields.$i.select_options" => 'Add comma-separated options for a dropdown field.',
                ]);
            }
        }

        $loanSettingsSaveReport = null;
        if ($kind === LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION) {
            $loanSettingsSaveReport = $this->buildLoanSettingsSaveReport($validated['fields'], $kind);
            if ($loanSettingsSaveReport['no_changes']) {
                if ($request->filled('complete_loan_form_setup_product_id')) {
                    $this->finalizeLoanFormSetupForProduct($request);

                    return redirect()
                        ->route($redirectRoute, $redirectRouteParams)
                        ->with('swal_flash', $this->loanFormSetupMarkedCompleteSwalPayload());
                }

                return redirect()
                    ->route($redirectRoute, $redirectRouteParams)
                    ->with('swal_flash', $this->loanSettingsSaveSwalPayload($loanSettingsSaveReport));
            }
        }

        DB::transaction(function () use ($validated, $kind): void {
            $rows = $validated['fields'];
            $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($v) => (int) $v)->all();

            LoanFormFieldDefinition::query()
                ->where('form_kind', $kind)
                ->where('is_core', false)
                ->whereNotIn('id', $submittedIds)
                ->delete();

            foreach ($rows as $index => $row) {
                $selectOptions = $row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
                    ? ($row['select_options'] ?? null)
                    : null;

                $prefill = isset($row['prefill_from_previous']) && (string) $row['prefill_from_previous'] === '1';
                $required = isset($row['is_required']) && (string) $row['is_required'] === '1';
                $fieldStatus = trim((string) ($row['field_status'] ?? 'active'));

                $id = isset($row['id']) ? (int) $row['id'] : null;

                if ($id) {
                    $field = LoanFormFieldDefinition::query()
                        ->where('form_kind', $kind)
                        ->where('id', $id)
                        ->firstOrFail();

                    if ($field->is_core) {
                        $fieldStatus = 'active';
                    }

                    $field->update([
                        'product_id' => isset($row['product_id']) && $row['product_id'] !== '' ? (int) $row['product_id'] : null,
                        'label' => $row['label'],
                        'data_type' => $row['data_type'],
                        'is_required' => $required,
                        'select_options' => $selectOptions,
                        'prefill_from_previous' => $field->is_core ? false : $prefill,
                        'visible_to' => trim((string) ($row['visible_to'] ?? '')),
                        'field_status' => $fieldStatus,
                        'sort_order' => $index,
                    ]);
                } else {
                    LoanFormFieldDefinition::query()->create([
                        'form_kind' => $kind,
                        'product_id' => isset($row['product_id']) && $row['product_id'] !== '' ? (int) $row['product_id'] : null,
                        'field_key' => LoanFormFieldDefinition::generateFieldKey($kind, $row['label']),
                        'label' => $row['label'],
                        'data_type' => $row['data_type'],
                        'is_required' => $required,
                        'select_options' => $selectOptions,
                        'prefill_from_previous' => $prefill,
                        'visible_to' => trim((string) ($row['visible_to'] ?? '')),
                        'is_core' => false,
                        'field_status' => $fieldStatus,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        if ($kind === LoanFormFieldDefinition::KIND_LOAN_SETTINGS_APPLICATION && $request->filled('complete_loan_form_setup_product_id')) {
            $this->finalizeLoanFormSetupForProduct($request);
        }

        if ($loanSettingsSaveReport !== null) {
            return redirect()
                ->route($redirectRoute, $redirectRouteParams)
                ->with('swal_flash', $this->loanSettingsSaveSwalPayload($loanSettingsSaveReport));
        }

        return redirect()
            ->route($redirectRoute, $redirectRouteParams)
            ->with('status', $successMessage);
    }

    /**
     * @param  array{active_fields:int,deleted_fields:int,created_fields:int,updated_fields:int,no_changes:bool}  $report
     * @return array{icon:string,title:string,html:string,confirmButtonColor?:string}
     */
    private function loanSettingsSaveSwalPayload(array $report): array
    {
        if ($report['no_changes']) {
            return [
                'icon' => 'info',
                'title' => 'No changes',
                'html' => '<p class="text-sm text-slate-600 text-left">Nothing was updated. Your loan form setup is unchanged.</p>',
                'confirmButtonColor' => '#64748b',
            ];
        }

        $lines = [];
        $lines[] = '<li><span class="font-semibold">Fields included (active):</span> '.e((string) $report['active_fields']).'</li>';
        $lines[] = '<li><span class="font-semibold">Custom fields removed:</span> '.e((string) $report['deleted_fields']).'</li>';
        if ((int) $report['created_fields'] > 0) {
            $lines[] = '<li><span class="font-semibold">New custom fields added:</span> '.e((string) $report['created_fields']).'</li>';
        }
        if ((int) $report['updated_fields'] > 0) {
            $lines[] = '<li><span class="font-semibold">Existing rows updated:</span> '.e((string) $report['updated_fields']).'</li>';
        }

        $html = '<p class="text-sm text-slate-600 text-left">Loan form setup has been saved.</p>'
            .'<ul class="mt-2 list-disc pl-5 text-left text-sm text-slate-700 space-y-1">'
            .implode('', $lines)
            .'</ul>';

        return [
            'icon' => 'success',
            'title' => 'Saved successfully',
            'html' => $html,
            'confirmButtonColor' => '#2563eb',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $validatedRows
     * @return array{active_fields:int,deleted_fields:int,created_fields:int,updated_fields:int,no_changes:bool}
     */
    private function buildLoanSettingsSaveReport(array $validatedRows, string $kind): array
    {
        $existing = LoanFormFieldDefinition::query()
            ->where('form_kind', $kind)
            ->get()
            ->keyBy('id');

        $submittedIds = collect($validatedRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->all();

        $deletedCount = $existing
            ->filter(fn (LoanFormFieldDefinition $f) => ! $f->is_core && ! in_array($f->id, $submittedIds, true))
            ->count();

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($validatedRows as $index => $row) {
            $id = isset($row['id']) ? (int) $row['id'] : null;
            if (! $id) {
                $createdCount++;

                continue;
            }

            $field = $existing->get($id);
            if (! $field instanceof LoanFormFieldDefinition) {
                continue;
            }

            if ($this->loanSettingsFieldUpdateWouldChange($field, $row, $index)) {
                $updatedCount++;
            }
        }

        $activeSelectedCount = collect($validatedRows)
            ->filter(function (array $row): bool {
                return trim((string) ($row['field_status'] ?? 'active')) === 'active';
            })
            ->count();

        $noChanges = $deletedCount === 0 && $createdCount === 0 && $updatedCount === 0;

        return [
            'active_fields' => $activeSelectedCount,
            'deleted_fields' => $deletedCount,
            'created_fields' => $createdCount,
            'updated_fields' => $updatedCount,
            'no_changes' => $noChanges,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function loanSettingsFieldUpdateWouldChange(LoanFormFieldDefinition $field, array $row, int $index): bool
    {
        $selectOptions = $row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
            ? ($row['select_options'] ?? null)
            : null;

        $prefill = isset($row['prefill_from_previous']) && (string) $row['prefill_from_previous'] === '1';
        $required = isset($row['is_required']) && (string) $row['is_required'] === '1';
        $fieldStatus = trim((string) ($row['field_status'] ?? 'active'));
        if ($field->is_core) {
            $fieldStatus = 'active';
            $prefill = false;
        }

        $newProductId = isset($row['product_id']) && $row['product_id'] !== '' ? (int) $row['product_id'] : null;

        $norm = fn (?string $s): string => trim((string) ($s ?? ''));

        return (int) ($field->product_id ?? 0) !== (int) ($newProductId ?? 0)
            || (string) $field->label !== (string) $row['label']
            || (string) $field->data_type !== (string) $row['data_type']
            || (bool) ($field->is_required ?? false) !== $required
            || $norm($field->select_options) !== $norm($selectOptions)
            || (bool) $field->prefill_from_previous !== $prefill
            || $norm($field->visible_to) !== $norm((string) ($row['visible_to'] ?? ''))
            || $norm($field->field_status) !== $fieldStatus
            || (int) $field->sort_order !== $index;
    }

    private function assertPendingLoanFormProduct(Request $request): void
    {
        if (! $request->filled('complete_loan_form_setup_product_id')) {
            return;
        }
        if (! Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
            return;
        }

        $id = (int) $request->input('complete_loan_form_setup_product_id');
        $product = LoanProduct::query()->find($id);
        if (! $product) {
            throw ValidationException::withMessages([
                'complete_loan_form_setup_product_id' => 'Invalid loan product.',
            ]);
        }
        if ($product->loan_form_setup_completed_at !== null) {
            throw ValidationException::withMessages([
                'complete_loan_form_setup_product_id' => 'This product is already set up. Use the product selector to adjust fields.',
            ]);
        }
    }

    private function finalizeLoanFormSetupForProduct(Request $request): void
    {
        if (! $request->filled('complete_loan_form_setup_product_id')) {
            return;
        }
        if (! Schema::hasColumn('loan_products', 'loan_form_setup_completed_at')) {
            return;
        }

        $id = (int) $request->input('complete_loan_form_setup_product_id');
        LoanProduct::query()
            ->whereKey($id)
            ->whereNull('loan_form_setup_completed_at')
            ->update(['loan_form_setup_completed_at' => now()]);
    }

    /**
     * @return array{icon:string,title:string,html:string,confirmButtonColor?:string}
     */
    private function loanFormSetupMarkedCompleteSwalPayload(): array
    {
        return [
            'icon' => 'success',
            'title' => 'Product form setup complete',
            'html' => '<p class="text-sm text-slate-600 text-left">This loan product is now available in the product selector. You can add or change product-specific fields there anytime.</p>',
            'confirmButtonColor' => '#2563eb',
        ];
    }
}
