<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanFormFieldDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanFormSetupController extends Controller
{
    public const FORM_SETUP_PAGE_PATTERN = 'access|loan-products|leave-settings|client-biodata|group-lending|accounting-forms|staff-leaves|staff-structure|staff-performance|loan-settings';

    public function setupPage(string $page): View
    {
        $cfg = $this->setupPageConfig($page);

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
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.data_type' => ['required', 'string', Rule::in($types)],
            'fields.*.select_options' => ['nullable', 'string', 'max:10000'],
            'fields.*.prefill_from_previous' => ['nullable', 'in:0,1'],
        ]);

        foreach ($validated['fields'] as $i => $row) {
            if ($row['data_type'] === LoanFormFieldDefinition::TYPE_SELECT
                && trim((string) ($row['select_options'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    "fields.$i.select_options" => 'Add comma-separated options for a dropdown field.',
                ]);
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

                $id = isset($row['id']) ? (int) $row['id'] : null;

                if ($id) {
                    $field = LoanFormFieldDefinition::query()
                        ->where('form_kind', $kind)
                        ->where('id', $id)
                        ->firstOrFail();

                    $field->update([
                        'label' => $row['label'],
                        'data_type' => $row['data_type'],
                        'select_options' => $selectOptions,
                        'prefill_from_previous' => $field->is_core ? false : $prefill,
                        'sort_order' => $index,
                    ]);
                } else {
                    LoanFormFieldDefinition::query()->create([
                        'form_kind' => $kind,
                        'field_key' => LoanFormFieldDefinition::generateFieldKey($kind, $row['label']),
                        'label' => $row['label'],
                        'data_type' => $row['data_type'],
                        'select_options' => $selectOptions,
                        'prefill_from_previous' => $prefill,
                        'is_core' => false,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        return redirect()
            ->route($redirectRoute, $redirectRouteParams)
            ->with('status', $successMessage);
    }
}
