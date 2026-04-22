<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoanFormFieldDefinition extends Model
{
    public const KIND_CLIENT_LOAN = 'client_loan';

    public const KIND_STAFF_LOAN = 'staff_loan';

    public const KIND_SALARY_ADVANCE = 'salary_advance';

    public const KIND_SYSTEM_ACCESS = 'system_access';

    public const KIND_LOAN_PRODUCTS = 'loan_products';

    public const KIND_LEAVE_WORKFLOW = 'leave_workflow';

    public const KIND_CLIENT_BIODATA = 'client_biodata';

    public const KIND_GROUP_LENDING = 'group_lending';

    public const KIND_ACCOUNTING_FORMS = 'accounting_forms';

    public const KIND_STAFF_LEAVE_APPLICATION = 'staff_leave_application';

    public const KIND_STAFF_STRUCTURE = 'staff_structure';

    public const KIND_STAFF_PERFORMANCE = 'staff_performance';

    public const KIND_LOAN_POLICY = 'loan_policy';

    public const TYPE_ALPHANUMERIC = 'alphanumeric';

    public const TYPE_NUMBER = 'number';

    public const TYPE_IMAGE = 'image';

    public const TYPE_SELECT = 'select';

    public const TYPE_LONG_TEXT = 'long_text';

    protected $fillable = [
        'form_kind',
        'field_key',
        'label',
        'data_type',
        'select_options',
        'prefill_from_previous',
        'is_core',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'prefill_from_previous' => 'boolean',
            'is_core' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function dataTypeLabels(): array
    {
        return [
            self::TYPE_ALPHANUMERIC => 'Alphanumeric text',
            self::TYPE_NUMBER => 'Number value',
            self::TYPE_IMAGE => 'Image file',
            self::TYPE_SELECT => 'Select list / dropdown',
            self::TYPE_LONG_TEXT => 'Long text',
        ];
    }

    public static function ensureDefaults(string $formKind): void
    {
        $rows = match ($formKind) {
            self::KIND_CLIENT_LOAN => self::defaultClientFields(),
            self::KIND_STAFF_LOAN => self::defaultStaffFields(),
            self::KIND_SALARY_ADVANCE => self::defaultSalaryAdvanceFields(),
            self::KIND_SYSTEM_ACCESS => self::defaultSystemAccessFields(),
            self::KIND_LOAN_PRODUCTS => self::defaultLoanProductsFields(),
            self::KIND_LEAVE_WORKFLOW => self::defaultLeaveWorkflowFields(),
            self::KIND_CLIENT_BIODATA => self::defaultClientBiodataFields(),
            self::KIND_GROUP_LENDING => self::defaultGroupLendingFields(),
            self::KIND_ACCOUNTING_FORMS => self::defaultAccountingFormsFields(),
            self::KIND_STAFF_LEAVE_APPLICATION => self::defaultStaffLeaveApplicationFields(),
            self::KIND_STAFF_STRUCTURE => self::defaultStaffStructureFields(),
            self::KIND_STAFF_PERFORMANCE => self::defaultStaffPerformanceFields(),
            self::KIND_LOAN_POLICY => self::defaultLoanPolicyFields(),
            default => null,
        };

        if ($rows !== null) {
            self::seedIfEmpty($formKind, $rows);
            self::seedMissingCoreFields($formKind, $rows);
        }
    }

    /**
     * @param  list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>  $rows
     */
    private static function seedIfEmpty(string $formKind, array $rows): void
    {
        if (self::query()->where('form_kind', $formKind)->exists()) {
            return;
        }

        foreach ($rows as $row) {
            $key = self::generateFieldKey($formKind, $row['label']);
            self::query()->create([
                'form_kind' => $formKind,
                'field_key' => $key,
                'label' => $row['label'],
                'data_type' => $row['data_type'],
                'select_options' => $row['select_options'] ?? null,
                'prefill_from_previous' => $row['prefill'] ?? false,
                'is_core' => $row['is_core'],
                'sort_order' => $row['sort_order'],
            ]);
        }
    }

    /**
     * Ensure new core fields are added for existing setups.
     *
     * @param  list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>  $rows
     */
    private static function seedMissingCoreFields(string $formKind, array $rows): void
    {
        foreach ($rows as $row) {
            if (! ($row['is_core'] ?? false)) {
                continue;
            }

            $exists = self::query()
                ->where('form_kind', $formKind)
                ->whereRaw('LOWER(label) = ?', [strtolower((string) $row['label'])])
                ->exists();
            if ($exists) {
                continue;
            }

            self::query()->create([
                'form_kind' => $formKind,
                'field_key' => self::generateFieldKey($formKind, $row['label']),
                'label' => $row['label'],
                'data_type' => $row['data_type'],
                'select_options' => $row['select_options'] ?? null,
                'prefill_from_previous' => false,
                'is_core' => true,
                'sort_order' => $row['sort_order'],
            ]);
        }
    }

    public static function generateFieldKey(string $formKind, string $label): string
    {
        $base = Str::slug($label, '_');
        if ($base === '') {
            $base = 'field';
        }
        $key = $base;
        $n = 0;
        while (self::query()->where('form_kind', $formKind)->where('field_key', $key)->exists()) {
            $n++;
            $key = $base.'_'.$n;
        }

        return substr($key, 0, 120);
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultClientFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Loan Product', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Client Idno', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Loan Officer', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Amount', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Duration', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Guarantor Name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Guarantor Contact', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Guarantor Business Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Residential Type', 'data_type' => self::TYPE_SELECT, 'select_options' => 'permanent borrower,permanent guarantor,temporary', 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Business Name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Business Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Asset List', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Loanform Front Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Loanform Pag2 Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Loanform Pag3 Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Loanform Pag4 Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Loan Form Sno', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Guarantor2 Name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Guarantor2 Contact', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultStaffFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Staff Number', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Requested Amount', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Repayment Duration (months)', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Purpose', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Supporting Document', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultSalaryAdvanceFields(): array
    {
        return [
            ['label' => 'Amount', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => 0],
            ['label' => 'Reason', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => true, 'sort_order' => 1],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultSystemAccessFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Login / user reference', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Role or access level', 'data_type' => self::TYPE_SELECT, 'select_options' => 'Admin,Manager,Staff,Read only', 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'OTP or 2FA notes', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Module permissions summary', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultLoanProductsFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Product name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Interest rate (%)', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Default term (months)', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Fees and charges', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultLeaveWorkflowFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Workflow step name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Approver role', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Escalation after (days)', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Attachment required', 'data_type' => self::TYPE_SELECT, 'select_options' => 'Yes,No', 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultClientBiodataFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Client Contact', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Idno', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Gender', 'data_type' => self::TYPE_SELECT, 'select_options' => 'Male,Female,Other', 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Kin Contact', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Next Of Kin', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Client Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Loan Officer', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Id Back Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Id Front Photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => true, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultGroupLendingFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Group name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Meeting day / frequency', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Chairperson', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Treasurer contact', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Group photo', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultAccountingFormsFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Request title', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Amount', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Cost center / budget line', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Supporting attachment', 'data_type' => self::TYPE_IMAGE, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Narration', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultStaffLeaveApplicationFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Leave type', 'data_type' => self::TYPE_SELECT, 'select_options' => 'Annual,Sick,Maternity,Paternity,Other', 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Start date', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Number of days', 'data_type' => self::TYPE_NUMBER, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Reason', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Handover notes', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultStaffStructureFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Department', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Job title', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Reports to', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Extension / desk', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultStaffPerformanceFields(): array
    {
        $i = 0;

        return [
            ['label' => 'KPI or indicator name', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Target', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Review period', 'data_type' => self::TYPE_SELECT, 'select_options' => 'Monthly,Quarterly,Annual', 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Notes', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }

    /**
     * @return list<array{label:string,data_type:string,select_options?:string|null,is_core:bool,sort_order:int,prefill?:bool}>
     */
    private static function defaultLoanPolicyFields(): array
    {
        $i = 0;

        return [
            ['label' => 'Policy title', 'data_type' => self::TYPE_ALPHANUMERIC, 'is_core' => true, 'sort_order' => $i++],
            ['label' => 'Checkoff day (month)', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Max reschedule / extensions', 'data_type' => self::TYPE_NUMBER, 'is_core' => false, 'sort_order' => $i++],
            ['label' => 'Penalty or fee rule notes', 'data_type' => self::TYPE_LONG_TEXT, 'is_core' => false, 'sort_order' => $i++],
        ];
    }
}
