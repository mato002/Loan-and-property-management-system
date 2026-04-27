<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\AccountingChartAccount;
use App\Models\AccountingControlAudit;
use App\Models\AccountingJournalApprovalQueue;
use App\Models\AccountingCompanyExpense;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingJournalTemplate;
use App\Models\AccountingPayrollPeriod;
use App\Models\AccountingPettyCashEntry;
use App\Models\AccountingPostingRule;
use App\Models\AccountingBudgetLine;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\AccountingUtilityPayment;
use App\Models\AccountingWalletSlotSetting;
use App\Models\LoanBookDisbursement;
use App\Models\LoanFormFieldDefinition;
use App\Models\LoanSystemSetting;
use App\Models\Employee;
use App\Models\LoanBookPayment;
use App\Services\AccountingChartBalanceService;
use App\Services\AccountingChartCodeGeneratorService;
use App\Services\AccountingControlledApprovalService;
use App\Services\AccountingOverdraftGuardService;
use App\Support\TabularExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\JsonResponse;

class LoanAccountingController extends Controller
{
    private function assignRequisitionReference(AccountingRequisition $row): void
    {
        $row->update([
            'reference' => 'REQ-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    /* ---------- Books hub ---------- */

    public function books(): View
    {
        $bankBalance = (float) AccountingJournalLine::query()
            ->whereHas('account', function ($q) {
                $q->where('is_cash_account', true)->where('name', 'like', '%bank%');
            })
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $agedReceivables = (float) AccountingJournalLine::query()
            ->whereHas('account', function ($q) {
                $q->where('account_type', AccountingChartAccount::TYPE_ASSET)
                    ->where(function ($q2) {
                        $q2->where('name', 'like', '%receivable%')
                            ->orWhere('name', 'like', '%debtor%');
                    });
            })
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');

        $agedPayables = (float) AccountingJournalLine::query()
            ->whereHas('account', function ($q) {
                $q->where('account_type', AccountingChartAccount::TYPE_LIABILITY)
                    ->where(function ($q2) {
                        $q2->where('name', 'like', '%payable%')
                            ->orWhere('name', 'like', '%creditor%');
                    });
            })
            ->selectRaw('COALESCE(SUM(credit - debit), 0) as balance')
            ->value('balance');

        $unpostedProcessedPayments = (int) LoanBookPayment::query()
            ->processedQueue()
            ->whereNull('accounting_journal_entry_id')
            ->count();
        $unpostedDisbursements = (int) LoanBookDisbursement::query()
            ->whereNull('accounting_journal_entry_id')
            ->count();
        $unpostedLoanGlItems = $unpostedProcessedPayments + $unpostedDisbursements;

        $booksHubMetrics = [
            'bank_balance' => $bankBalance,
            'aged_receivables' => max(0, $agedReceivables),
            'aged_payables' => max(0, $agedPayables),
            'new_entries_30d' => (int) AccountingJournalEntry::query()
                ->where('entry_date', '>=', now()->subDays(30)->toDateString())
                ->count(),
            'pending_payroll_runs' => (int) AccountingPayrollPeriod::query()
                ->where('status', AccountingPayrollPeriod::STATUS_DRAFT)
                ->count(),
            'budget_lines_count' => (int) AccountingBudgetLine::query()->count(),
            'expense_records_30d' => (int) AccountingCompanyExpense::query()
                ->where('expense_date', '>=', now()->subDays(30)->toDateString())
                ->count(),
            'unposted_processed_payments' => $unpostedProcessedPayments,
            'unposted_disbursements' => $unpostedDisbursements,
            'unposted_loan_gl_items' => $unpostedLoanGlItems,
        ];

        return view('loan.accounting.books', compact('booksHubMetrics'));
    }

    /* ---------- Chart of accounts ---------- */

    /** @return array<string, string> */
    private function walletSlotLabels(): array
    {
        return [
            'savings_account' => 'Savings Account',
            'transactional_account' => 'Transactional Account',
            'investment_account' => 'Investment Account',
            'investors_roi_account' => 'Investors ROI Account',
            'cash_account' => 'Cash Account',
            'withdrawals_suspense_account' => 'Withdrawals Suspense Account',
        ];
    }

    public function chartWalletSlotsUpdate(Request $request): RedirectResponse
    {
        $keys = array_keys($this->walletSlotLabels());
        $rules = [];
        foreach ($keys as $key) {
            $rules['slots.'.$key] = ['nullable', 'integer', 'exists:accounting_chart_accounts,id'];
        }
        $validated = $request->validate($rules);

        $slots = $validated['slots'] ?? [];
        foreach ($keys as $key) {
            AccountingWalletSlotSetting::query()->updateOrCreate(
                ['slot_key' => $key],
                ['accounting_chart_account_id' => $slots[$key] ?? null]
            );
        }

        return redirect()->route('loan.accounting.books.chart_rules')->with('status', 'Wallet account mappings updated.');
    }

    public function chartPostingRuleUpdate(Request $request, AccountingPostingRule $accounting_posting_rule): RedirectResponse
    {
        $governanceRaw = LoanSystemSetting::getValue('accounting_mapping_governance', '{}') ?? '{}';
        $governance = json_decode($governanceRaw, true);
        if (! is_array($governance)) {
            $governance = [];
        }
        $allowedEditors = collect(data_get($governance, 'permissions.edit', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $currentUserId = (int) ($request->user()->id ?? 0);
        if ($allowedEditors->isNotEmpty() && ! $allowedEditors->contains($currentUserId)) {
            abort(403, 'You are not allowed to edit automated mapping rules.');
        }

        if (! $accounting_posting_rule->is_editable) {
            abort(403);
        }

        $validated = $request->validate([
            'debit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
            'credit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
        ]);

        if ($accounting_posting_rule->rule_key === \App\Services\LoanBookGlPostingService::RULE_LOAN_LEDGER
            && empty($validated['credit_account_id'])) {
            return back()->withErrors([
                'credit_account_id' => 'Loan Ledger requires a credit account (loan portfolio / receivable).',
            ]);
        }

        $accounting_posting_rule->update([
            'debit_account_id' => $validated['debit_account_id'] ?? null,
            'credit_account_id' => $validated['credit_account_id'] ?? null,
        ]);

        return redirect()->route('loan.accounting.books.chart_rules')->with('status', 'Accounting rule updated.');
    }

    public function chartPostingRuleStore(Request $request): RedirectResponse
    {
        $governanceRaw = LoanSystemSetting::getValue('accounting_mapping_governance', '{}') ?? '{}';
        $governance = json_decode($governanceRaw, true);
        if (! is_array($governance)) {
            $governance = [];
        }
        $allowedCreators = collect(data_get($governance, 'permissions.create', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $currentUserId = (int) ($request->user()->id ?? 0);
        if ($allowedCreators->isNotEmpty() && ! $allowedCreators->contains($currentUserId)) {
            abort(403, 'You are not allowed to create automated mapping rules.');
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'debit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
            'credit_account_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
        ]);

        $baseKey = Str::slug((string) $validated['label'], '_');
        if ($baseKey === '') {
            $baseKey = 'business_event';
        }
        $ruleKey = $baseKey;
        $suffix = 2;
        while (AccountingPostingRule::query()->where('rule_key', $ruleKey)->exists()) {
            $ruleKey = $baseKey.'_'.$suffix;
            $suffix++;
        }

        $nextSortOrder = (int) AccountingPostingRule::query()->max('sort_order') + 1;

        AccountingPostingRule::query()->create([
            'rule_key' => $ruleKey,
            'label' => (string) $validated['label'],
            'debit_account_id' => $validated['debit_account_id'] ?? null,
            'credit_account_id' => $validated['credit_account_id'] ?? null,
            'is_editable' => true,
            'sort_order' => $nextSortOrder,
        ]);

        return redirect()->route('loan.accounting.books.chart_rules', ['tab' => 'rules'])->with('status', 'Mapping rule added.');
    }

    public function chartPostingRuleDestroy(Request $request, AccountingPostingRule $accounting_posting_rule): RedirectResponse
    {
        $governanceRaw = LoanSystemSetting::getValue('accounting_mapping_governance', '{}') ?? '{}';
        $governance = json_decode($governanceRaw, true);
        if (! is_array($governance)) {
            $governance = [];
        }
        $allowedDeleters = collect(data_get($governance, 'permissions.delete', []))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values();
        $currentUserId = (int) ($request->user()->id ?? 0);
        if ($allowedDeleters->isNotEmpty() && ! $allowedDeleters->contains($currentUserId)) {
            abort(403, 'You are not allowed to delete automated mapping rules.');
        }

        if (! $accounting_posting_rule->is_editable) {
            return back()->withErrors([
                'mapping_rule' => 'This mapping rule is protected and cannot be deleted.',
            ]);
        }

        $accounting_posting_rule->delete();

        return redirect()->route('loan.accounting.books.chart_rules', ['tab' => 'rules'])->with('status', 'Mapping rule deleted.');
    }

    public function chartCreate(): View
    {
        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $headerAccounts = $hasAccountClass
            ? AccountingChartAccount::query()
                ->where('is_active', true)
                ->whereIn('account_class', [AccountingChartAccount::CLASS_HEADER, AccountingChartAccount::CLASS_PARENT])
                ->orderBy('code')
                ->get()
            : collect();

        return view('loan.accounting.chart.create', compact('headerAccounts'));
    }

    public function chartNextCode(Request $request): JsonResponse
    {
        $request->validate([
            'account_type' => ['required', 'in:asset,liability,equity,income,expense'],
            'account_class' => ['required', Rule::in([
                AccountingChartAccount::CLASS_HEADER,
                AccountingChartAccount::CLASS_PARENT,
                AccountingChartAccount::CLASS_DETAIL,
            ])],
            'parent_id' => ['nullable', 'integer', 'exists:accounting_chart_accounts,id'],
        ]);

        $code = app(AccountingChartCodeGeneratorService::class)->preview(
            (string) $request->string('account_type'),
            (string) $request->string('account_class'),
            $request->integer('parent_id') ?: null
        );

        return response()->json(['code' => $code]);
    }

    public function chartStore(Request $request): RedirectResponse
    {
        $validated = $this->validateChartPayload($request);
        $created = DB::transaction(function () use ($request, $validated) {
            $generatedCode = app(AccountingChartCodeGeneratorService::class)->reserve(
                (string) $validated['account_type'],
                (string) ($validated['account_class'] ?? AccountingChartAccount::CLASS_DETAIL),
                isset($validated['parent_id']) ? (int) $validated['parent_id'] : null
            );

            $payload = $this->buildChartPayload($request, $validated, null, $generatedCode);
            $payload['created_by'] = $request->user()->id;

            if ($this->coaApprovalEnabled()) {
                $payload['is_active'] = false;
                $payload['approval_status'] = 'pending';
                $payload['approval_current_step'] = 1;
                $payload['approval_submitted_at'] = now();
                $payload['approved_by'] = null;
                $payload['approved_at'] = null;
                $payload['rejected_by'] = null;
                $payload['rejected_at'] = null;
                $payload['rejection_reason'] = null;
                $payload['approval_history'] = [[
                    'action' => 'submitted',
                    'by_user_id' => $request->user()->id,
                    'at' => now()->toDateTimeString(),
                    'note' => 'Account submitted for approval.',
                ]];
            } else {
                $payload['approval_status'] = 'active';
                $payload['approval_current_step'] = null;
                $payload['approval_submitted_at'] = null;
            }

            $account = AccountingChartAccount::create($payload);
            $this->syncControlledApprovers($account, collect($request->input('controlled_approver_ids', [])));
            $this->logControlAudit('chart_account', $account->id, 'created', $request->user()->id, null, $payload, 'Chart account created.');

            return $account;
        });

        $redirectTo = $request->string('redirect_to')->toString();
        if ($redirectTo !== '') {
            return redirect($redirectTo)->with('status', $this->coaApprovalEnabled() ? 'Account submitted for approval.' : 'Account created.');
        }

        return redirect()->route('loan.accounting.books.chart_rules')->with('status', $this->coaApprovalEnabled() ? 'Account submitted for approval.' : 'Account created.');
    }

    public function chartEdit(AccountingChartAccount $accounting_chart_account): RedirectResponse
    {
        return redirect()->route('loan.accounting.books.chart_rules', ['edit_account' => $accounting_chart_account->id]);
    }

    public function chartUpdate(Request $request, AccountingChartAccount $accounting_chart_account): RedirectResponse
    {
        $validated = $this->validateChartPayload($request, $accounting_chart_account);
        $payload = $this->buildChartPayload($request, $validated, $accounting_chart_account, (string) $accounting_chart_account->code);

        if ($this->coaApprovalEnabled() && (string) ($accounting_chart_account->approval_status ?? '') === 'rejected') {
            $history = collect($accounting_chart_account->approval_history ?? [])->values()->all();
            $history[] = [
                'action' => 'resubmitted',
                'by_user_id' => $request->user()->id,
                'at' => now()->toDateTimeString(),
                'note' => 'Rejected account updated and resubmitted.',
            ];
            $payload['is_active'] = false;
            $payload['approval_status'] = 'pending';
            $payload['approval_current_step'] = 1;
            $payload['approval_submitted_at'] = now();
            $payload['approved_by'] = null;
            $payload['approved_at'] = null;
            $payload['rejected_by'] = null;
            $payload['rejected_at'] = null;
            $payload['rejection_reason'] = null;
            $payload['approval_history'] = $history;
        }

        $oldValues = $accounting_chart_account->only(array_keys($payload));
        $accounting_chart_account->update($payload);
        $this->syncControlledApprovers($accounting_chart_account, collect($request->input('controlled_approver_ids', [])));
        $this->logControlAudit('chart_account', $accounting_chart_account->id, 'updated', $request->user()->id, $oldValues, $payload, 'Chart account updated.');

        $redirectTo = $request->string('redirect_to')->toString();
        if ($redirectTo !== '') {
            return redirect($redirectTo)->with('status', 'Account updated.');
        }

        return redirect()->route('loan.accounting.books.chart_rules')->with('status', 'Account updated.');
    }

    private function coaApprovalEnabled(): bool
    {
        return LoanSystemSetting::getValue('coa_approval_required', '0') === '1';
    }

    public function chartDestroy(Request $request, AccountingChartAccount $accounting_chart_account): RedirectResponse
    {
        $hasJournalHistory = AccountingJournalEntry::query()
            ->whereHas('lines', fn ($q) => $q->where('accounting_chart_account_id', $accounting_chart_account->id))
            ->exists();

        if ($hasJournalHistory) {
            return redirect()->back()->withErrors(['delete' => 'This account has journal history and cannot be deleted.']);
        }

        try {
            DB::transaction(function () use ($accounting_chart_account): void {
                // Keep chart config clean before deleting an account.
                AccountingPostingRule::query()
                    ->where('debit_account_id', $accounting_chart_account->id)
                    ->update(['debit_account_id' => null]);
                AccountingPostingRule::query()
                    ->where('credit_account_id', $accounting_chart_account->id)
                    ->update(['credit_account_id' => null]);
                AccountingWalletSlotSetting::query()
                    ->where('accounting_chart_account_id', $accounting_chart_account->id)
                    ->update(['accounting_chart_account_id' => null]);
                AccountingChartAccount::query()
                    ->where('parent_id', $accounting_chart_account->id)
                    ->update(['parent_id' => null]);

                $accounting_chart_account->delete();
            });
        } catch (QueryException $e) {
            return redirect()->back()->withErrors([
                'delete' => 'Account cannot be deleted because it is linked to other records. Remove those links first, then try again.',
            ]);
        }

        $redirectTo = $request->string('redirect_to')->toString();
        if ($redirectTo !== '') {
            return redirect($redirectTo)->with('status', 'Account removed.');
        }

        return redirect()->route('loan.accounting.books.chart_rules')->with('status', 'Account removed.');
    }

    /** @return array<string, mixed> */
    private function validateChartPayload(Request $request, ?AccountingChartAccount $existing = null): array
    {
        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $hasParentId = Schema::hasColumn('accounting_chart_accounts', 'parent_id');
        $rules = [
            'code' => ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'in:asset,liability,equity,income,expense'],
            'income_statement_category' => ['nullable', Rule::in(AccountingChartAccount::INCOME_STATEMENT_CATEGORIES)],
            'account_class' => $hasAccountClass
                ? ['required', Rule::in([AccountingChartAccount::CLASS_HEADER, AccountingChartAccount::CLASS_PARENT, AccountingChartAccount::CLASS_DETAIL])]
                : ['nullable'],
            'parent_id' => $hasParentId
                ? ['nullable', 'integer', 'exists:accounting_chart_accounts,id']
                : ['nullable'],
            'current_balance' => ['nullable', 'numeric'],
            'min_balance_floor' => ['nullable', 'numeric', 'min:0'],
            'allow_overdraft' => ['sometimes', 'boolean'],
            'overdraft_limit' => ['nullable', 'numeric', 'min:0'],
            'is_cash_account' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_controlled_account' => ['sometimes', 'boolean'],
            'control_requires_approval' => ['sometimes', 'boolean'],
            'control_approval_type' => ['nullable', Rule::in(['any', 'all', 'role'])],
            'control_approval_role' => ['nullable', 'string', 'max:80'],
            'control_always_require_approval' => ['sometimes', 'boolean'],
            'control_threshold_enabled' => ['sometimes', 'boolean'],
            'control_threshold_amount' => ['nullable', 'numeric', 'min:0'],
            'control_applies_to' => ['nullable', Rule::in(['debit', 'credit', 'both'])],
            'control_reason_note' => ['nullable', 'string', 'max:500'],
            'controlled_approver_ids' => ['nullable', 'array', 'max:20'],
            'controlled_approver_ids.*' => ['integer', 'exists:users,id'],
            'floor_enabled' => ['sometimes', 'boolean'],
            'floor_action' => ['nullable', Rule::in(['block', 'require_approval'])],
        ];

        $validated = $request->validate($rules);

        if ($hasAccountClass && $hasParentId && ! empty($validated['parent_id'])) {
            $parent = AccountingChartAccount::query()->find((int) $validated['parent_id']);
            if (! $parent || (! $parent->isHeader() && ! $parent->isParent())) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Parent account must be a Header or Parent account.',
                ]);
            }
            if ($existing && (int) $validated['parent_id'] === (int) $existing->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Account cannot be parent of itself.',
                ]);
            }
            if ($existing && $parent->isDescendantOf((int) $existing->id)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Circular account hierarchy is not allowed.',
                ]);
            }
        }

        $selectedType = (string) ($validated['account_type'] ?? '');
        $selectedCategory = (string) ($validated['income_statement_category'] ?? '');
        if (in_array($selectedType, [AccountingChartAccount::TYPE_INCOME, AccountingChartAccount::TYPE_EXPENSE], true) && $selectedCategory === '') {
            throw ValidationException::withMessages([
                'income_statement_category' => 'Income statement category is required for income and expense accounts.',
            ]);
        }
        if (! in_array($selectedType, [AccountingChartAccount::TYPE_INCOME, AccountingChartAccount::TYPE_EXPENSE], true)) {
            $validated['income_statement_category'] = null;
        }

        return $validated;
    }

    /** @param  array<string, mixed>  $validated
     *  @return array<string, mixed>
     */
    private function buildChartPayload(Request $request, array $validated, ?AccountingChartAccount $existing = null, ?string $resolvedCode = null): array
    {
        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $hasParentId = Schema::hasColumn('accounting_chart_accounts', 'parent_id');
        $parent = null;
        if ($hasParentId && ! empty($validated['parent_id'])) {
            $parent = AccountingChartAccount::query()->find((int) $validated['parent_id']);
        }

        $accountType = $parent ? (string) $parent->account_type : (string) $validated['account_type'];

        if ($hasAccountClass && $existing && $existing->isHeader() && ($validated['account_class'] ?? '') === AccountingChartAccount::CLASS_DETAIL) {
            if ($existing->children()->exists()) {
                throw ValidationException::withMessages([
                    'account_class' => 'Convert or reassign child accounts first before changing this header to detail.',
                ]);
            }
        }

        $payload = [
            'code' => $resolvedCode ?: (string) ($existing?->code ?? ''),
            'name' => $validated['name'],
            'account_type' => $accountType,
            'income_statement_category' => in_array($accountType, [AccountingChartAccount::TYPE_INCOME, AccountingChartAccount::TYPE_EXPENSE], true)
                ? ($validated['income_statement_category'] ?? null)
                : null,
            'current_balance' => (float) ($validated['current_balance'] ?? 0),
            'min_balance_floor' => (float) ($validated['min_balance_floor'] ?? 0),
            'allow_overdraft' => $request->boolean('allow_overdraft'),
            'overdraft_limit' => $validated['overdraft_limit'] ?? null,
            'is_cash_account' => $request->boolean('is_cash_account'),
            'is_active' => $request->has('is_active')
                ? $request->boolean('is_active')
                : ($existing?->is_active ?? true),
            'is_controlled_account' => $request->boolean('is_controlled_account'),
            'control_requires_approval' => $request->boolean('control_requires_approval', true),
            'control_approval_type' => (string) ($validated['control_approval_type'] ?? 'any'),
            'control_approval_role' => $validated['control_approval_role'] ?? null,
            'control_always_require_approval' => $request->boolean('control_always_require_approval'),
            'control_threshold_enabled' => $request->boolean('control_threshold_enabled'),
            'control_threshold_amount' => $validated['control_threshold_amount'] ?? null,
            'control_applies_to' => (string) ($validated['control_applies_to'] ?? 'both'),
            'control_reason_note' => $validated['control_reason_note'] ?? null,
            'floor_enabled' => $request->boolean('floor_enabled'),
            'floor_action' => (string) ($validated['floor_action'] ?? 'block'),
        ];

        if ($hasAccountClass) {
            $payload['account_class'] = $validated['account_class'] ?? AccountingChartAccount::CLASS_DETAIL;
        }
        if ($hasParentId) {
            $payload['parent_id'] = $validated['parent_id'] ?? null;
        }
        if (! $payload['allow_overdraft']) {
            $payload['overdraft_limit'] = null;
        }
        if (! $payload['is_controlled_account']) {
            $payload['control_requires_approval'] = false;
            $payload['control_approval_role'] = null;
            $payload['control_threshold_enabled'] = false;
            $payload['control_threshold_amount'] = null;
            $payload['control_reason_note'] = null;
            $payload['control_always_require_approval'] = false;
            $payload['control_applies_to'] = 'both';
        }
        if (! $payload['control_threshold_enabled']) {
            $payload['control_threshold_amount'] = null;
        }
        if (! $payload['floor_enabled']) {
            $payload['floor_action'] = 'block';
        }

        return $payload;
    }

    private function syncControlledApprovers(AccountingChartAccount $account, Collection $approverIds): void
    {
        if (! $account->is_controlled_account) {
            $account->controlledApprovers()->sync([]);

            return;
        }

        $ids = $approverIds
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $account->controlledApprovers()->sync($ids);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function logControlAudit(string $entityType, ?int $entityId, string $action, ?int $actorId, ?array $oldValues, ?array $newValues, ?string $context = null): void
    {
        AccountingControlAudit::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor_user_id' => $actorId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'context' => $context,
        ]);
    }

    /* ---------- Journal entries ---------- */

    public function journalIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $reference = request()->string('reference')->toString();
        $status = strtolower(request()->string('status')->toString());
        $createdBy = request()->integer('created_by');
        $accountId = request()->integer('account_id');
        $export = request()->string('export')->toString();

        $q = AccountingJournalEntry::query()
            ->with('createdByUser')
            ->withCount('lines');

        if ($from !== '') {
            $q->where('entry_date', '>=', $from);
        }
        if ($to !== '') {
            $q->where('entry_date', '<=', $to);
        }
        if ($reference !== '') {
            $q->where('reference', 'like', '%'.$reference.'%');
        }
        if (in_array($status, [
            AccountingJournalEntry::STATUS_DRAFT,
            AccountingJournalEntry::STATUS_POSTED,
            AccountingJournalEntry::STATUS_REVERSED,
            AccountingJournalEntry::STATUS_PENDING_CONTROLLED_APPROVAL,
            AccountingJournalEntry::STATUS_REJECTED,
        ], true)) {
            $q->where('status', $status);
        }
        if ($createdBy) {
            $q->where('created_by', $createdBy);
        }
        if ($accountId > 0) {
            $q->whereHas('lines', function ($lineQuery) use ($accountId) {
                $lineQuery->where('accounting_chart_account_id', $accountId);
            });
        }

        $q->orderByDesc('entry_date')->orderByDesc('id');

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('journal-entries', [
                'Entry Date', 'Reference', 'Description', 'Status', 'Lines', 'Created By',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingJournalEntry $e) {
                    return [
                        optional($e->entry_date)->format('Y-m-d'),
                        (string) ($e->reference ?? ''),
                        (string) ($e->description ?? ''),
                        (string) ($e->status ?? AccountingJournalEntry::STATUS_POSTED),
                        (int) ($e->lines_count ?? 0),
                        (string) ($e->createdByUser?->name ?? ''),
                    ];
                });
            }, $export);
        }

        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $accounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->when($hasAccountClass, fn ($query) => $query->where('account_class', AccountingChartAccount::CLASS_DETAIL))
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $entries = $q->paginate($perPage)->withQueryString();

        return view('loan.accounting.journal.index', compact('entries', 'from', 'to', 'reference', 'status', 'createdBy', 'accountId', 'accounts', 'perPage'));
    }

    public function journalCreate(): View
    {
        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $accounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->when($hasAccountClass, fn ($q) => $q->where('account_class', AccountingChartAccount::CLASS_DETAIL))
            ->orderBy('code')
            ->get();

        $pendingApprovals = AccountingJournalApprovalQueue::query()
            ->where('status', AccountingJournalApprovalQueue::STATUS_PENDING)
            ->count();
        $draftCount = AccountingJournalEntry::query()->where('status', AccountingJournalEntry::STATUS_DRAFT)->count();
        $blockedCount = AccountingJournalEntry::query()->where('status', AccountingJournalEntry::STATUS_REJECTED)->count();
        $recentActivities = AccountingJournalEntry::query()
            ->with('createdByUser')
            ->whereNotNull('created_by')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $templates = collect();
        if (Schema::hasTable('accounting_journal_templates')) {
            $templates = AccountingJournalTemplate::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('scope', 'system')
                        ->orWhere('created_by', auth()->id());
                })
                ->orderBy('scope')
                ->orderBy('name')
                ->get();
        }

        return view('loan.accounting.journal.create', compact(
            'accounts',
            'pendingApprovals',
            'draftCount',
            'blockedCount',
            'recentActivities',
            'templates',
        ));
    }

    public function journalEdit(AccountingJournalEntry $accounting_journal_entry): View
    {
        abort_unless(in_array((string) $accounting_journal_entry->status, [
            AccountingJournalEntry::STATUS_DRAFT,
            AccountingJournalEntry::STATUS_REJECTED,
        ], true), 403);

        return $this->journalCreate()->with('draftEntry', $accounting_journal_entry->load('lines'));
    }

    public function journalStore(Request $request): RedirectResponse
    {
        $action = (string) $request->input('action', 'post');
        if (! in_array($action, ['post', 'submit_for_approval', 'save_draft'], true)) {
            $action = 'post';
        }
        $draftId = (int) $request->input('journal_entry_id', 0);

        $compactLines = collect($request->input('lines', []))
            ->filter(fn (array $l) => ! empty($l['accounting_chart_account_id']))
            ->values()
            ->all();
        $request->merge(['lines' => $compactLines]);

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.accounting_chart_account_id' => ['required', 'exists:accounting_chart_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = collect($validated['lines']);
        $lineAccountIds = $lines->pluck('accounting_chart_account_id')->map(fn ($id) => (int) $id)->unique()->values();
        $lineAccounts = AccountingChartAccount::query()->whereIn('id', $lineAccountIds)->get()->keyBy('id');
        foreach ($lineAccountIds as $accountId) {
            $account = $lineAccounts->get($accountId);
            if ($account && $account->isHeader()) {
                return back()->withErrors(['lines' => 'Journal entries can only post to Detail accounts.'])->withInput();
            }
        }
        $lineDeltas = [];
        foreach ($lines as $line) {
            $accountId = (int) $line['accounting_chart_account_id'];
            $delta = round((float) ($line['debit'] ?? 0) - (float) ($line['credit'] ?? 0), 2);
            $lineDeltas[$accountId] = round((float) ($lineDeltas[$accountId] ?? 0) + $delta, 2);
        }

        foreach ($lines as $i => $line) {
            $d = round((float) ($line['debit'] ?? 0), 2);
            $c = round((float) ($line['credit'] ?? 0), 2);
            if ($d > 0 && $c > 0) {
                // allow a single-line "self-balanced" entry (or any line) only when debit equals credit
                if ($d !== $c) {
                    return back()->withErrors(['lines.'.$i => 'Each line must be either a debit or a credit (or have equal debit and credit).'])->withInput();
                }
            }
            if ($d <= 0 && $c <= 0) {
                return back()->withErrors(['lines.'.$i => 'Each line needs a debit or credit amount.'])->withInput();
            }
        }

        $totalDebit = round($lines->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $totalCredit = round($lines->sum(fn ($l) => (float) ($l['credit'] ?? 0)), 2);
        if ($action !== 'save_draft' && ($totalDebit !== $totalCredit || $totalDebit <= 0)) {
            return back()->withErrors(['lines' => 'Total debits must equal total credits and be greater than zero.'])->withInput();
        }

        $entry = null;
        DB::transaction(function () use ($validated, $request, $lines, $lineAccountIds, $lineAccounts, $lineDeltas, $action, $draftId, &$entry) {
            $existingDraft = null;
            if ($draftId > 0) {
                $existingDraft = AccountingJournalEntry::query()->find($draftId);
                if (! $existingDraft || ! in_array((string) $existingDraft->status, [
                    AccountingJournalEntry::STATUS_DRAFT,
                    AccountingJournalEntry::STATUS_REJECTED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'journal' => 'Only draft or rejected entries can be edited.',
                    ]);
                }
            }

            if ($action === 'save_draft') {
                $payload = [
                    'entry_date' => $validated['entry_date'],
                    'reference' => $validated['reference'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'created_by' => $request->user()->id,
                    'status' => AccountingJournalEntry::STATUS_DRAFT,
                    'approved_by' => null,
                    'approved_at' => null,
                ];
                if ($existingDraft) {
                    $existingDraft->update($payload);
                    $existingDraft->lines()->delete();
                    $entry = $existingDraft;
                } else {
                    $entry = AccountingJournalEntry::create($payload);
                }

                foreach ($lines as $line) {
                    AccountingJournalLine::create([
                        'accounting_journal_entry_id' => $entry->id,
                        'accounting_chart_account_id' => $line['accounting_chart_account_id'],
                        'debit' => round((float) ($line['debit'] ?? 0), 2),
                        'credit' => round((float) ($line['credit'] ?? 0), 2),
                        'memo' => $line['memo'] ?? null,
                    ]);
                }

                return;
            }

            $decision = app(AccountingControlledApprovalService::class)->evaluate($lines, $lineAccounts);
            if ((bool) $decision['blocked']) {
                throw ValidationException::withMessages([
                    'lines' => (string) ($decision['blocked_message'] ?: 'The transaction is blocked by account controls.'),
                ]);
            }

            if ($action === 'post' && (bool) $decision['requires_approval']) {
                throw ValidationException::withMessages([
                    'lines' => 'This journal requires approval. Use "Submit for approval".',
                ]);
            }
            if ($action === 'submit_for_approval' && ! (bool) $decision['requires_approval']) {
                throw ValidationException::withMessages([
                    'lines' => 'This journal does not require approval. Use "Post transaction".',
                ]);
            }

            if ($action === 'post') {
                app(AccountingOverdraftGuardService::class)->assertCanApplyDeltas($lineDeltas);
            }

            $entryPayload = [
                'entry_date' => $validated['entry_date'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'status' => $action === 'submit_for_approval'
                    ? AccountingJournalEntry::STATUS_PENDING_CONTROLLED_APPROVAL
                    : AccountingJournalEntry::STATUS_POSTED,
                'approved_by' => $action === 'submit_for_approval' ? null : $request->user()->id,
                'approved_at' => $action === 'submit_for_approval' ? null : now(),
            ];
            if ($existingDraft) {
                $existingDraft->update($entryPayload);
                $existingDraft->lines()->delete();
                $entry = $existingDraft;
            } else {
                $entry = AccountingJournalEntry::create($entryPayload);
            }
            foreach ($lines as $line) {
                AccountingJournalLine::create([
                    'accounting_journal_entry_id' => $entry->id,
                    'accounting_chart_account_id' => $line['accounting_chart_account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'memo' => $line['memo'] ?? null,
                ]);
            }
            if ($action === 'submit_for_approval') {
                AccountingJournalApprovalQueue::query()->create([
                    'accounting_journal_entry_id' => $entry->id,
                    'triggered_by_user_id' => $request->user()->id,
                    'status' => AccountingJournalApprovalQueue::STATUS_PENDING,
                    'reason_code' => (string) ($decision['reason_code'] ?? 'controlled_account'),
                    'required_approval_type' => (string) ($decision['approval_type'] ?? 'any'),
                    'required_role' => $decision['required_role'] ? (string) $decision['required_role'] : null,
                    'required_approver_ids' => $decision['required_approver_ids'] ?? [],
                    'approval_progress' => [],
                    'reason_detail' => (string) ($decision['reason_detail'] ?? ''),
                ]);
                $this->logControlAudit('journal_entry', (int) $entry->id, 'queued_for_approval', (int) $request->user()->id, null, [
                    'reason_code' => (string) ($decision['reason_code'] ?? 'controlled_account'),
                    'reason_detail' => (string) ($decision['reason_detail'] ?? ''),
                ], 'Journal queued for controlled account approval.');
            } else {
                app(AccountingChartBalanceService::class)->syncAccountsAndAncestors($lineAccountIds->all());
            }
        });

        if ($action === 'save_draft') {
            return redirect()
                ->route('loan.accounting.journal.edit', $entry)
                ->with('status', 'Draft saved.');
        }
        if ((string) ($entry->status ?? '') === AccountingJournalEntry::STATUS_PENDING_CONTROLLED_APPROVAL) {
            return redirect()
                ->route('loan.accounting.journal.index')
                ->with('status', 'This journal includes a controlled account and has been sent for approval.');
        }

        return redirect()
            ->route('loan.accounting.journal.show', $entry)
            ->with('status', 'Journal entry posted.');
    }

    public function journalTemplateStore(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('accounting_journal_templates')) {
            return back()->withErrors([
                'template' => 'Journal templates table is missing. Run migrations first.',
            ]);
        }

        $rawTemplateLines = $request->input('template_lines', []);
        if (is_string($rawTemplateLines)) {
            $decoded = json_decode($rawTemplateLines, true);
            $rawTemplateLines = is_array($decoded) ? $decoded : [];
        }
        $request->merge(['template_lines' => $rawTemplateLines]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'scope' => ['required', Rule::in(['personal', 'system'])],
            'reference_prefix' => ['nullable', 'string', 'max:30'],
            'default_action' => ['required', Rule::in(['post', 'submit_for_approval', 'save_draft'])],
            'template_lines' => ['required', 'array', 'min:1'],
            'template_lines.*.accounting_chart_account_id' => ['required', 'exists:accounting_chart_accounts,id'],
            'template_lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'template_lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'template_lines.*.memo' => ['nullable', 'string', 'max:500'],
        ]);

        AccountingJournalTemplate::query()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'scope' => $validated['scope'],
            'created_by' => $request->user()->id,
            'is_active' => true,
            'reference_prefix' => $validated['reference_prefix'] ?? null,
            'default_action' => $validated['default_action'],
            'template_lines' => collect($validated['template_lines'])
                ->map(fn (array $line): array => [
                    'accounting_chart_account_id' => (int) $line['accounting_chart_account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'memo' => $line['memo'] ?? null,
                ])->values()->all(),
        ]);

        return redirect()->route('loan.accounting.journal.create')->with('status', 'Template saved.');
    }

    public function journalTemplateUpdate(Request $request, AccountingJournalTemplate $accounting_journal_template): RedirectResponse
    {
        abort_unless(
            (int) ($accounting_journal_template->created_by ?? 0) === (int) $request->user()->id || (bool) $request->user()->is_super_admin,
            403
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference_prefix' => ['nullable', 'string', 'max:30'],
            'template_lines' => ['required', 'array', 'min:1'],
            'template_lines.*.accounting_chart_account_id' => ['required', 'exists:accounting_chart_accounts,id'],
            'template_lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'template_lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'template_lines.*.memo' => ['nullable', 'string', 'max:500'],
        ]);

        $accounting_journal_template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'reference_prefix' => $validated['reference_prefix'] ?? null,
            'template_lines' => collect($validated['template_lines'])
                ->map(fn (array $line): array => [
                    'accounting_chart_account_id' => (int) $line['accounting_chart_account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'memo' => $line['memo'] ?? null,
                ])->values()->all(),
        ]);

        return redirect()->route('loan.accounting.journal.create')->with('status', 'Template updated.');
    }

    public function journalTemplateDestroy(Request $request, AccountingJournalTemplate $accounting_journal_template): RedirectResponse
    {
        abort_unless(
            (int) ($accounting_journal_template->created_by ?? 0) === (int) $request->user()->id || (bool) $request->user()->is_super_admin,
            403
        );

        $accounting_journal_template->delete();

        return redirect()->route('loan.accounting.journal.create')->with('status', 'Template deleted.');
    }

    public function journalShow(AccountingJournalEntry $accounting_journal_entry): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $accounting_journal_entry->load(['lines.account', 'createdByUser', 'approvedByUser', 'reversedFrom']);

        $export = request()->string('export')->toString();
        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('journal-entry-'.$accounting_journal_entry->id, [
                'Account Code', 'Account Name', 'Debit', 'Credit', 'Memo',
            ], function () use ($accounting_journal_entry) {
                return $accounting_journal_entry->lines->map(function (AccountingJournalLine $line) {
                    return [
                        (string) ($line->account?->code ?? ''),
                        (string) ($line->account?->name ?? ''),
                        (string) ($line->debit ?? ''),
                        (string) ($line->credit ?? ''),
                        (string) ($line->memo ?? ''),
                    ];
                });
            }, $export);
        }

        $reversalBlockers = $this->buildReversalBlockersPreview($accounting_journal_entry);

        return view('loan.accounting.journal.show', [
            'entry' => $accounting_journal_entry,
            'reversalBlockers' => $reversalBlockers,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildReversalBlockersPreview(AccountingJournalEntry $entry): array
    {
        if (($entry->status ?? AccountingJournalEntry::STATUS_POSTED) !== AccountingJournalEntry::STATUS_POSTED) {
            return [];
        }

        $reversalDeltas = [];
        foreach ($entry->lines as $line) {
            $accountId = (int) $line->accounting_chart_account_id;
            $delta = round((float) ($line->credit ?? 0) - (float) ($line->debit ?? 0), 2);
            $reversalDeltas[$accountId] = round((float) ($reversalDeltas[$accountId] ?? 0) + $delta, 2);
        }

        if (empty($reversalDeltas)) {
            return [];
        }

        $accounts = AccountingChartAccount::query()
            ->whereIn('id', array_keys($reversalDeltas))
            ->get()
            ->keyBy('id');

        $blockers = [];
        foreach ($reversalDeltas as $accountId => $delta) {
            $account = $accounts->get((int) $accountId);
            if (! $account) {
                continue;
            }

            $current = (float) ($account->current_balance ?? 0);
            $projected = round($current + (float) $delta, 2);
            if ($projected >= 0) {
                continue;
            }

            if (! (bool) $account->allow_overdraft) {
                $blockers[] = [
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'reason' => 'insufficient_funds',
                    'current_balance' => $current,
                    'projected_balance' => $projected,
                    'delta' => (float) $delta,
                ];
                continue;
            }

            $limit = $account->overdraft_limit;
            if ($limit !== null && $projected < (0 - (float) $limit)) {
                $blockers[] = [
                    'code' => (string) $account->code,
                    'name' => (string) $account->name,
                    'reason' => 'overdraft_limit',
                    'current_balance' => $current,
                    'projected_balance' => $projected,
                    'delta' => (float) $delta,
                    'overdraft_limit' => (float) $limit,
                ];
            }
        }

        return $blockers;
    }

    public function journalDestroy(AccountingJournalEntry $accounting_journal_entry): RedirectResponse
    {
        if (($accounting_journal_entry->status ?? AccountingJournalEntry::STATUS_POSTED) !== AccountingJournalEntry::STATUS_DRAFT) {
            return redirect()
                ->route('loan.accounting.journal.show', $accounting_journal_entry)
                ->withErrors(['journal' => 'Only draft entries can be deleted. Reverse posted entries instead.']);
        }

        $accounting_journal_entry->delete();

        return redirect()->route('loan.accounting.journal.index')->with('status', 'Journal entry deleted.');
    }
    
    public function journalBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one entry.']);
        }
        if ($action === 'delete') {
            $deleted = AccountingJournalEntry::query()
                ->whereIn('id', $ids)
                ->where('status', AccountingJournalEntry::STATUS_DRAFT)
                ->delete();

            if ($deleted === 0) {
                return back()->withErrors(['bulk' => 'Only draft entries can be deleted in bulk.']);
            }

            return back()->with('status', 'Selected draft journal entries deleted.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function journalReverse(Request $request, AccountingJournalEntry $accounting_journal_entry): RedirectResponse
    {
        $accounting_journal_entry->load('lines');

        if (($accounting_journal_entry->status ?? AccountingJournalEntry::STATUS_POSTED) !== AccountingJournalEntry::STATUS_POSTED) {
            return redirect()
                ->route('loan.accounting.journal.show', $accounting_journal_entry)
                ->withErrors(['journal' => 'Only posted entries can be reversed.']);
        }

        if ($accounting_journal_entry->reversals()->exists()) {
            return redirect()
                ->route('loan.accounting.journal.show', $accounting_journal_entry)
                ->withErrors(['journal' => 'This entry has already been reversed.']);
        }

        $reversalEntry = null;
        DB::transaction(function () use ($request, $accounting_journal_entry, &$reversalEntry) {
            $reversalDeltas = [];
            foreach ($accounting_journal_entry->lines as $line) {
                $accountId = (int) $line->accounting_chart_account_id;
                $delta = round((float) ($line->credit ?? 0) - (float) ($line->debit ?? 0), 2);
                $reversalDeltas[$accountId] = round((float) ($reversalDeltas[$accountId] ?? 0) + $delta, 2);
            }
            app(AccountingOverdraftGuardService::class)->assertCanApplyDeltas($reversalDeltas);

            $reversalEntry = AccountingJournalEntry::create([
                'entry_date' => now()->toDateString(),
                'reference' => 'REV-'.$accounting_journal_entry->id,
                'description' => 'Reversal of journal #'.$accounting_journal_entry->id.' ('.$accounting_journal_entry->reference.')',
                'created_by' => $request->user()->id,
                'status' => AccountingJournalEntry::STATUS_POSTED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'reversed_from_id' => $accounting_journal_entry->id,
            ]);

            foreach ($accounting_journal_entry->lines as $line) {
                $targetAccount = AccountingChartAccount::query()->find((int) $line->accounting_chart_account_id);
                if ($targetAccount && $targetAccount->isHeader()) {
                    throw new \RuntimeException('Reversal cannot post to Header accounts. Convert mapping to Detail accounts first.');
                }
                AccountingJournalLine::create([
                    'accounting_journal_entry_id' => $reversalEntry->id,
                    'accounting_chart_account_id' => $line->accounting_chart_account_id,
                    'debit' => round((float) ($line->credit ?? 0), 2),
                    'credit' => round((float) ($line->debit ?? 0), 2),
                    'memo' => $line->memo ? ('Reversal: '.$line->memo) : 'Reversal line',
                ]);
            }

            $accounting_journal_entry->update([
                'status' => AccountingJournalEntry::STATUS_REVERSED,
            ]);

            $affectedIds = $accounting_journal_entry->lines
                ->pluck('accounting_chart_account_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
            app(AccountingChartBalanceService::class)->syncAccountsAndAncestors($affectedIds);
        });

        return redirect()
            ->route('loan.accounting.journal.show', $reversalEntry)
            ->with('status', 'Reversal journal posted successfully.');
    }

    /* ---------- Ledger ---------- */

    public function ledger(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $export = $request->string('export')->toString();
        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();

        $accountId = $request->integer('account_id');
        $historyLimit = max(1, min(200, $request->integer('limit') ?: 50));
        $isRecentHistory = $request->boolean('recent');
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $lines = collect();
        $account = null;
        $opening = 0.0;
        $closing = 0.0;

        if ($accountId) {
            $account = AccountingChartAccount::query()->find($accountId);
            if ($account) {
                if ($isRecentHistory) {
                    $latest = AccountingJournalLine::query()
                        ->where('accounting_chart_account_id', $account->id)
                        ->whereHas('entry')
                        ->with(['entry'])
                        ->get()
                        ->sortByDesc(function (AccountingJournalLine $line) {
                            return [
                                optional($line->entry?->entry_date)->format('Y-m-d'),
                                (int) ($line->entry?->id ?? 0),
                                (int) $line->id,
                            ];
                        })
                        ->take($historyLimit)
                        ->values();

                    $lines = $latest
                        ->sortBy(function (AccountingJournalLine $line) {
                            return [
                                optional($line->entry?->entry_date)->format('Y-m-d'),
                                (int) ($line->entry?->id ?? 0),
                                (int) $line->id,
                            ];
                        })
                        ->values();

                    if ($lines->isNotEmpty()) {
                        $from = optional($lines->first()->entry?->entry_date)->format('Y-m-d') ?: $from;
                        $to = optional($lines->last()->entry?->entry_date)->format('Y-m-d') ?: $to;
                    }

                    $opening = 0.0;
                    $closing = (float) $lines->sum('debit') - (float) $lines->sum('credit');
                } else {
                    $base = AccountingJournalLine::query()
                        ->where('accounting_chart_account_id', $account->id)
                        ->whereHas('entry', function ($q) use ($from) {
                            $q->where('entry_date', '<', $from);
                        });
                    $opening = (float) $base->sum('debit') - (float) $base->sum('credit');

                    $lines = AccountingJournalLine::query()
                        ->where('accounting_chart_account_id', $account->id)
                        ->whereHas('entry', function ($q) use ($from, $to) {
                            $q->whereBetween('entry_date', [$from, $to]);
                        })
                        ->with(['entry'])
                        ->get()
                        ->sortBy(function (AccountingJournalLine $line) {
                            return [
                                $line->entry->entry_date->format('Y-m-d'),
                                $line->entry->id,
                                $line->id,
                            ];
                        })
                        ->values();
                }

                $period = (float) $lines->sum('debit') - (float) $lines->sum('credit');
                $closing = $opening + $period;
            }
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true) && $account) {
            return TabularExport::stream('ledger-'.$account->code.'-'.$from.'-to-'.$to, [
                'Account Code', 'Account Name', 'From', 'To', 'Opening', 'Closing',
                'Entry Date', 'Journal ID', 'Reference', 'Debit', 'Credit', 'Memo',
            ], function () use ($account, $from, $to, $opening, $closing, $lines) {
                $headerRow = [[
                    (string) ($account->code ?? ''),
                    (string) ($account->name ?? ''),
                    (string) $from,
                    (string) $to,
                    (string) number_format((float) $opening, 2, '.', ''),
                    (string) number_format((float) $closing, 2, '.', ''),
                    '', '', '', '', '', '',
                ]];

                $detail = $lines->map(function (AccountingJournalLine $line) {
                    return [
                        '', '', '', '', '', '',
                        optional($line->entry?->entry_date)->format('Y-m-d'),
                        (string) ($line->entry?->id ?? ''),
                        (string) ($line->entry?->reference ?? ''),
                        (string) ($line->debit ?? ''),
                        (string) ($line->credit ?? ''),
                        (string) ($line->memo ?? ''),
                    ];
                });

                return collect($headerRow)->concat($detail);
            }, $export);
        }

        return view('loan.accounting.ledger', compact('accounts', 'account', 'lines', 'from', 'to', 'opening', 'closing', 'isRecentHistory', 'historyLimit'));
    }

    /* ---------- Requisitions ---------- */

    public function requisitionsIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $month = request()->string('month')->toString(); // YYYY-MM
        $export = request()->string('export')->toString();

        $baseQuery = AccountingRequisition::query()
            ->with(['requestedByUser', 'approvedByUser']);

        if ($status !== '' && in_array($status, [
            AccountingRequisition::STATUS_PENDING,
            AccountingRequisition::STATUS_APPROVED,
            AccountingRequisition::STATUS_REJECTED,
            AccountingRequisition::STATUS_PAID,
        ], true)) {
            $baseQuery->where('status', $status);
        }

        if ($month !== '') {
            try {
                $m = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $baseQuery->whereBetween('created_at', [$m, $m->copy()->endOfMonth()]);
            } catch (\Throwable $e) {
                // ignore invalid month filter
            }
        }

        $ordered = (clone $baseQuery)->orderByDesc('created_at');

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('requisitions', [
                'Reference', 'Title', 'Purpose', 'Amount', 'Currency', 'Status', 'Requested By', 'Approved By', 'Created At',
            ], function () use ($ordered) {
                return $ordered->get()->map(function (AccountingRequisition $r) {
                    return [
                        (string) ($r->reference ?? ''),
                        (string) ($r->title ?? ''),
                        (string) ($r->purpose ?? ''),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->requestedByUser?->name ?? ''),
                        (string) ($r->approvedByUser?->name ?? ''),
                        optional($r->created_at)->toDateTimeString(),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $ordered->paginate($perPage)->withQueryString();

        $availableMonths = AccountingRequisition::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->pluck('ym')
            ->values();

        $statusOptions = [
            '' => 'All',
            AccountingRequisition::STATUS_PENDING => 'Pending',
            AccountingRequisition::STATUS_APPROVED => 'Approved',
            AccountingRequisition::STATUS_REJECTED => 'Rejected',
            AccountingRequisition::STATUS_PAID => 'Paid',
        ];

        return view('loan.accounting.requisitions.index', compact('rows', 'availableMonths', 'statusOptions', 'status', 'month', 'perPage'));
    }

    public function requisitionsCreate(): View
    {
        $fields = $this->accountingFormsDefinitions();
        $mapped = $this->mappedAccountingRequisitionFields($fields);
        $custom = $this->accountingRequisitionCustomFields($fields, $mapped);

        return view('loan.accounting.requisitions.create', [
            'accountingRequisitionMappedFields' => $mapped,
            'accountingRequisitionCustomFields' => $custom,
        ]);
    }

    public function requisitionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->accountingRequisitionDynamicValidationRules(),
        ]);
        $validated['form_meta'] = $this->resolveAccountingRequisitionFormMeta($request);

        $row = AccountingRequisition::create([
            'reference' => null,
            'title' => $validated['title'],
            'purpose' => $validated['purpose'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'KES',
            'status' => AccountingRequisition::STATUS_PENDING,
            'requested_by' => $request->user()->id,
            'form_meta' => $validated['form_meta'],
            'notes' => $validated['notes'] ?? null,
        ]);
        $this->assignRequisitionReference($row);

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition '.$row->reference.' submitted.');
    }

    public function requisitionsEdit(AccountingRequisition $accounting_requisition): View
    {
        $fields = $this->accountingFormsDefinitions();
        $mapped = $this->mappedAccountingRequisitionFields($fields);
        $custom = $this->accountingRequisitionCustomFields($fields, $mapped);

        return view('loan.accounting.requisitions.edit', [
            'row' => $accounting_requisition,
            'accountingRequisitionMappedFields' => $mapped,
            'accountingRequisitionCustomFields' => $custom,
        ]);
    }

    public function requisitionsUpdate(Request $request, AccountingRequisition $accounting_requisition): RedirectResponse
    {
        if (in_array($accounting_requisition->status, [AccountingRequisition::STATUS_PAID, AccountingRequisition::STATUS_REJECTED], true)) {
            return redirect()->route('loan.accounting.requisitions.index')->withErrors(['status' => 'This requisition cannot be edited.']);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->accountingRequisitionDynamicValidationRules(),
        ]);
        $validated['form_meta'] = $this->resolveAccountingRequisitionFormMeta($request, $accounting_requisition);

        $accounting_requisition->update([
            'title' => $validated['title'],
            'purpose' => $validated['purpose'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'KES',
            'form_meta' => $validated['form_meta'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition updated.');
    }

    public function requisitionsDestroy(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        if ($accounting_requisition->status === AccountingRequisition::STATUS_PAID) {
            return redirect()->back()->withErrors(['delete' => 'Paid requisitions cannot be deleted.']);
        }
        $accounting_requisition->delete();

        return redirect()->route('loan.accounting.requisitions.index')->with('status', 'Requisition removed.');
    }
    
    public function requisitionsBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one requisition.']);
        }
        if ($action === 'delete') {
            // Do not delete PAID requisitions
            AccountingRequisition::query()
                ->whereIn('id', $ids)
                ->where('status', '!=', AccountingRequisition::STATUS_PAID)
                ->delete();
            return back()->with('status', 'Selected requisitions removed (excluding paid).');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function requisitionsApprove(Request $request, AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_PENDING, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Requisition approved.');
    }

    public function requisitionsReject(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_PENDING, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_REJECTED,
        ]);

        return redirect()->back()->with('status', 'Requisition rejected.');
    }

    public function requisitionsPay(AccountingRequisition $accounting_requisition): RedirectResponse
    {
        abort_unless($accounting_requisition->status === AccountingRequisition::STATUS_APPROVED, 403);
        $accounting_requisition->update([
            'status' => AccountingRequisition::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Marked as paid.');
    }

    /* ---------- Utility payments ---------- */

    public function utilitiesIndex(): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $type = request()->string('utility_type')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $provider = request()->string('provider')->toString();
        $method = request()->string('payment_method')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingUtilityPayment::query()
            ->with('recordedByUser')
            ->orderByDesc('paid_on')
            ->orderByDesc('id');

        if ($type !== '') {
            $q->where('utility_type', $type);
        }
        if ($provider !== '') {
            $q->where('provider', 'like', '%'.$provider.'%');
        }
        if ($method !== '') {
            $q->where('payment_method', $method);
        }
        if ($from !== '') {
            $q->where('paid_on', '>=', $from);
        }
        if ($to !== '') {
            $q->where('paid_on', '<=', $to);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('utility-payments', [
                'Type', 'Provider', 'Bill Ref', 'Paid On', 'Amount', 'Currency', 'Method', 'Reference', 'Recorded By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingUtilityPayment $r) {
                    return [
                        (string) ($r->utility_type ?? ''),
                        (string) ($r->provider ?? ''),
                        (string) ($r->bill_account_ref ?? ''),
                        optional($r->paid_on)->format('Y-m-d'),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->payment_method ?? ''),
                        (string) ($r->reference ?? ''),
                        (string) ($r->recordedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $q->paginate($perPage)->withQueryString();

        $utilityTypes = AccountingUtilityPayment::query()
            ->select('utility_type')
            ->distinct()
            ->orderBy('utility_type')
            ->pluck('utility_type')
            ->values();

        $paymentMethods = AccountingUtilityPayment::query()
            ->select('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->values();

        return view('loan.accounting.utilities.index', compact('rows', 'type', 'from', 'to', 'provider', 'method', 'utilityTypes', 'paymentMethods', 'perPage'));
    }

    public function utilitiesCreate(): View
    {
        return view('loan.accounting.utilities.create');
    }

    public function utilitiesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'utility_type' => ['required', 'string', 'max:64'],
            'provider' => ['nullable', 'string', 'max:255'],
            'bill_account_ref' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        AccountingUtilityPayment::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'KES',
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Utility payment recorded.');
    }

    public function utilitiesEdit(AccountingUtilityPayment $accounting_utility_payment): View
    {
        return view('loan.accounting.utilities.edit', ['row' => $accounting_utility_payment]);
    }

    public function utilitiesUpdate(Request $request, AccountingUtilityPayment $accounting_utility_payment): RedirectResponse
    {
        $validated = $request->validate([
            'utility_type' => ['required', 'string', 'max:64'],
            'provider' => ['nullable', 'string', 'max:255'],
            'bill_account_ref' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'paid_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accounting_utility_payment->update($validated);

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Utility payment updated.');
    }

    public function utilitiesDestroy(AccountingUtilityPayment $accounting_utility_payment): RedirectResponse
    {
        $accounting_utility_payment->delete();

        return redirect()->route('loan.accounting.utilities.index')->with('status', 'Record removed.');
    }
    
    public function utilitiesBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one utility payment.']);
        }
        if ($action === 'delete') {
            AccountingUtilityPayment::query()->whereIn('id', $ids)->delete();
            return back()->with('status', 'Selected utility payments removed.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /* ---------- Petty cash ---------- */

    public function pettyIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $kind = request()->string('kind')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $search = request()->string('q')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingPettyCashEntry::query()
            ->with('recordedByUser')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ;

        if ($kind !== '' && in_array($kind, [AccountingPettyCashEntry::KIND_RECEIPT, AccountingPettyCashEntry::KIND_DISBURSEMENT], true)) {
            $q->where('kind', $kind);
        }
        if ($from !== '') {
            $q->where('entry_date', '>=', $from);
        }
        if ($to !== '') {
            $q->where('entry_date', '<=', $to);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('payee_or_source', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('petty-cash', [
                'Entry Date', 'Kind', 'Amount', 'Payee/Source', 'Description', 'Recorded By',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingPettyCashEntry $r) {
                    return [
                        optional($r->entry_date)->format('Y-m-d'),
                        (string) ($r->kind ?? ''),
                        (string) $r->amount,
                        (string) ($r->payee_or_source ?? ''),
                        (string) ($r->description ?? ''),
                        (string) ($r->recordedByUser?->name ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 25)));
        $rows = $q->paginate($perPage)->withQueryString();

        $balance = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_RECEIPT)
            ->sum('amount')
            - (float) AccountingPettyCashEntry::query()
                ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
                ->sum('amount');

        return view('loan.accounting.petty.index', compact('rows', 'balance', 'kind', 'from', 'to', 'search', 'perPage'));
    }

    public function pettyCreate(): View
    {
        return view('loan.accounting.petty.create');
    }

    public function pettyStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'kind' => ['required', 'in:receipt,disbursement'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payee_or_source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        AccountingPettyCashEntry::create([
            ...$validated,
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Petty cash line saved.');
    }

    public function pettyEdit(AccountingPettyCashEntry $accounting_petty_cash_entry): View
    {
        return view('loan.accounting.petty.edit', ['row' => $accounting_petty_cash_entry]);
    }

    public function pettyUpdate(Request $request, AccountingPettyCashEntry $accounting_petty_cash_entry): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'kind' => ['required', 'in:receipt,disbursement'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payee_or_source' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $accounting_petty_cash_entry->update($validated);

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Entry updated.');
    }

    public function pettyDestroy(AccountingPettyCashEntry $accounting_petty_cash_entry): RedirectResponse
    {
        $accounting_petty_cash_entry->delete();

        return redirect()->route('loan.accounting.petty.index')->with('status', 'Entry removed.');
    }
    
    public function pettyBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one petty cash entry.']);
        }
        if ($action === 'delete') {
            AccountingPettyCashEntry::query()->whereIn('id', $ids)->delete();
            return back()->with('status', 'Selected petty cash entries removed.');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    /* ---------- Salary advances ---------- */

    public function advancesIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $employeeId = request()->integer('employee_id');
        $export = request()->string('export')->toString();

        $q = AccountingSalaryAdvance::query()
            ->with(['employee', 'approvedByUser'])
            ->orderByDesc('requested_on')
            ->orderByDesc('id');

        if ($status !== '' && in_array($status, [
            AccountingSalaryAdvance::STATUS_PENDING,
            AccountingSalaryAdvance::STATUS_APPROVED,
            AccountingSalaryAdvance::STATUS_REJECTED,
            AccountingSalaryAdvance::STATUS_SETTLED,
        ], true)) {
            $q->where('status', $status);
        }
        if ($from !== '') {
            $q->where('requested_on', '>=', $from);
        }
        if ($to !== '') {
            $q->where('requested_on', '<=', $to);
        }
        if ($employeeId) {
            $q->where('employee_id', $employeeId);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('salary-advances', [
                'Employee', 'Employee No', 'Requested On', 'Amount', 'Currency', 'Status', 'Approved By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingSalaryAdvance $r) {
                    return [
                        (string) ($r->employee?->full_name ?? ''),
                        (string) ($r->employee?->employee_number ?? ''),
                        optional($r->requested_on)->format('Y-m-d'),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->approvedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $perPage = max(10, min(200, (int) request()->input('per_page', 20)));
        $rows = $q->paginate($perPage)->withQueryString();

        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'employee_number']);

        return view('loan.accounting.advances.index', compact('rows', 'status', 'from', 'to', 'employeeId', 'employees', 'perPage'));
    }

    public function advancesCreate(): View
    {
        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get();
        $fields = $this->salaryAdvanceFormDefinitions();
        $mapped = $this->mappedSalaryAdvanceFields($fields);
        $custom = $this->salaryAdvanceCustomFields($fields, $mapped);

        return view('loan.accounting.advances.create', [
            'employees' => $employees,
            'salaryAdvanceMappedFields' => $mapped,
            'salaryAdvanceCustomFields' => $custom,
        ]);
    }

    public function advancesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'requested_on' => ['required', 'date'],
            'reason_for_request' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->salaryAdvanceDynamicValidationRules(),
        ]);

        $validated['form_meta'] = $this->resolveSalaryAdvanceFormMeta($request);

        AccountingSalaryAdvance::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'KES',
            'status' => AccountingSalaryAdvance::STATUS_PENDING,
        ]);

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Salary advance request recorded.');
    }

    public function advancesEdit(AccountingSalaryAdvance $accounting_salary_advance): View
    {
        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get();
        $fields = $this->salaryAdvanceFormDefinitions();
        $mapped = $this->mappedSalaryAdvanceFields($fields);
        $custom = $this->salaryAdvanceCustomFields($fields, $mapped);

        return view('loan.accounting.advances.edit', [
            'row' => $accounting_salary_advance,
            'employees' => $employees,
            'salaryAdvanceMappedFields' => $mapped,
            'salaryAdvanceCustomFields' => $custom,
        ]);
    }

    public function advancesUpdate(Request $request, AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        if (in_array($accounting_salary_advance->status, [AccountingSalaryAdvance::STATUS_SETTLED], true)) {
            return redirect()->route('loan.accounting.advances.index')->withErrors(['status' => 'Settled advances cannot be edited.']);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'requested_on' => ['required', 'date'],
            'reason_for_request' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->salaryAdvanceDynamicValidationRules(),
        ]);

        $validated['form_meta'] = $this->resolveSalaryAdvanceFormMeta($request, $accounting_salary_advance);

        $accounting_salary_advance->update($validated);

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Advance updated.');
    }

    public function advancesDestroy(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        if ($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_SETTLED) {
            return redirect()->back()->withErrors(['delete' => 'Settled advances cannot be deleted.']);
        }
        $accounting_salary_advance->delete();

        return redirect()->route('loan.accounting.advances.index')->with('status', 'Advance removed.');
    }
    
    public function advancesBulk(Request $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $ids = collect($request->input('ids', []))->map(fn ($v) => (int) $v)->filter()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one advance.']);
        }
        if ($action === 'delete') {
            AccountingSalaryAdvance::query()
                ->whereIn('id', $ids)
                ->where('status', '!=', AccountingSalaryAdvance::STATUS_SETTLED)
                ->delete();
            return back()->with('status', 'Selected advances removed (excluding settled).');
        }
        return back()->withErrors(['bulk' => 'Unsupported bulk action.']);
    }

    public function advancesApprove(Request $request, AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_PENDING, 403);
        $validated = $request->validate([
            'approved_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_amount' => $validated['approved_amount'] ?? $accounting_salary_advance->amount,
            'approved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Advance approved.');
    }

    public function advancesReject(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_PENDING, 403);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_REJECTED,
        ]);

        return redirect()->back()->with('status', 'Advance rejected.');
    }

    public function advancesSettle(AccountingSalaryAdvance $accounting_salary_advance): RedirectResponse
    {
        abort_unless($accounting_salary_advance->status === AccountingSalaryAdvance::STATUS_APPROVED, 403);
        $accounting_salary_advance->update([
            'status' => AccountingSalaryAdvance::STATUS_SETTLED,
            'settled_on' => now()->toDateString(),
        ]);

        return redirect()->back()->with('status', 'Advance marked settled.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanFormFieldDefinition>
     */
    private function accountingFormsDefinitions()
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_ACCOUNTING_FORMS);

        return LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_ACCOUNTING_FORMS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanFormFieldDefinition>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function mappedAccountingRequisitionFields(Collection $fields): array
    {
        $mapped = [];
        foreach ($fields as $field) {
            $key = trim((string) $field->field_key);
            $label = trim((string) $field->label);
            $hay = strtolower($key.' '.$label);
            if (! isset($mapped['title']) && (str_contains($hay, 'request title') || str_contains($hay, 'title'))) {
                $mapped['title'] = $this->normalizedDefinitionField($field);
                continue;
            }
            if (! isset($mapped['amount']) && str_contains($hay, 'amount')) {
                $mapped['amount'] = $this->normalizedDefinitionField($field);
                continue;
            }
            if (! isset($mapped['purpose']) && (str_contains($hay, 'narration') || str_contains($hay, 'purpose') || str_contains($hay, 'details'))) {
                $mapped['purpose'] = $this->normalizedDefinitionField($field);
            }
        }

        if (! isset($mapped['amount'])) {
            $amountCandidate = $fields->first(fn (LoanFormFieldDefinition $f) => $f->data_type === LoanFormFieldDefinition::TYPE_NUMBER);
            if ($amountCandidate) {
                $mapped['amount'] = $this->normalizedDefinitionField($amountCandidate);
            }
        }

        if (! isset($mapped['title'])) {
            $titleCandidate = $fields->first();
            if ($titleCandidate) {
                $mapped['title'] = $this->normalizedDefinitionField($titleCandidate);
            }
        }

        if (! isset($mapped['purpose'])) {
            $purposeCandidate = $fields->first(fn (LoanFormFieldDefinition $f) => (string) $f->field_key !== (string) ($mapped['title']['key'] ?? '') && (string) $f->field_key !== (string) ($mapped['amount']['key'] ?? ''));
            if ($purposeCandidate) {
                $mapped['purpose'] = $this->normalizedDefinitionField($purposeCandidate);
            }
        }

        return $mapped;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanFormFieldDefinition>  $fields
     * @param  array<string, array<string, mixed>>  $mapped
     * @return list<array<string, mixed>>
     */
    private function accountingRequisitionCustomFields(Collection $fields, array $mapped): array
    {
        $mappedKeys = collect($mapped)->pluck('key')->filter()->map(fn ($v) => (string) $v)->all();

        return $fields
            ->filter(fn (LoanFormFieldDefinition $f): bool => ! in_array((string) $f->field_key, $mappedKeys, true))
            ->map(fn (LoanFormFieldDefinition $f): array => $this->normalizedDefinitionField($f))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountingRequisitionDynamicValidationRules(): array
    {
        if (! $this->accountingRequisitionFormMetaSupported()) {
            return [];
        }

        $rules = [];
        $fields = $this->accountingFormsDefinitions();
        $mapped = $this->mappedAccountingRequisitionFields($fields);
        foreach ($this->accountingRequisitionCustomFields($fields, $mapped) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $rules["form_files.$key"] = ['nullable', 'file', 'image', 'max:4096'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_NUMBER) {
                $rules["form_meta.$key"] = ['nullable', 'numeric'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_SELECT) {
                $options = collect((array) ($field['select_options'] ?? []))
                    ->map(fn (string $v): string => trim($v))
                    ->filter()
                    ->values()
                    ->all();
                $rules["form_meta.$key"] = $options !== []
                    ? ['nullable', 'string', Rule::in($options)]
                    : ['nullable', 'string', 'max:255'];
                continue;
            }
            $rules["form_meta.$key"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAccountingRequisitionFormMeta(Request $request, ?AccountingRequisition $existing = null): array
    {
        if (! $this->accountingRequisitionFormMetaSupported()) {
            return [];
        }

        $existingMeta = (array) ($existing?->form_meta ?? []);
        $meta = [];
        $fields = $this->accountingFormsDefinitions();
        $mapped = $this->mappedAccountingRequisitionFields($fields);
        foreach ($this->accountingRequisitionCustomFields($fields, $mapped) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $inputKey = "form_files.$key";
                if ($request->hasFile($inputKey)) {
                    $file = $request->file($inputKey);
                    if ($file) {
                        $newPath = $file->store('requisitions/form-meta', 'public');
                        $meta[$key] = $newPath;
                        $oldPath = (string) ($existingMeta[$key] ?? '');
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        continue;
                    }
                }
                if (array_key_exists($key, $existingMeta)) {
                    $meta[$key] = $existingMeta[$key];
                }
                continue;
            }

            $value = $request->input("form_meta.$key");
            if (is_array($value)) {
                $value = '';
            }
            $meta[$key] = trim((string) ($value ?? ''));
        }

        return $meta;
    }

    private function accountingRequisitionFormMetaSupported(): bool
    {
        return Schema::hasColumn('accounting_requisitions', 'form_meta');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedDefinitionField(LoanFormFieldDefinition $field): array
    {
        return [
            'key' => (string) $field->field_key,
            'label' => (string) $field->label,
            'data_type' => (string) $field->data_type,
            'select_options' => $this->splitSelectOptions((string) ($field->select_options ?? '')),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, LoanFormFieldDefinition>
     */
    private function salaryAdvanceFormDefinitions()
    {
        LoanFormFieldDefinition::ensureDefaults(LoanFormFieldDefinition::KIND_SALARY_ADVANCE);

        return LoanFormFieldDefinition::query()
            ->where('form_kind', LoanFormFieldDefinition::KIND_SALARY_ADVANCE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanFormFieldDefinition>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function mappedSalaryAdvanceFields(Collection $fields): array
    {
        $mapped = [];
        foreach ($fields as $field) {
            $key = trim((string) $field->field_key);
            $label = trim((string) $field->label);
            $hay = strtolower($key.' '.$label);
            if (! isset($mapped['amount']) && str_contains($hay, 'amount')) {
                $mapped['amount'] = [
                    'key' => $key,
                    'label' => $label,
                    'data_type' => (string) $field->data_type,
                    'select_options' => $this->splitSelectOptions((string) ($field->select_options ?? '')),
                ];
                continue;
            }
            if (! isset($mapped['reason_for_request']) && (str_contains($hay, 'reason') || str_contains($hay, 'purpose'))) {
                $mapped['reason_for_request'] = [
                    'key' => $key,
                    'label' => $label,
                    'data_type' => (string) $field->data_type,
                    'select_options' => $this->splitSelectOptions((string) ($field->select_options ?? '')),
                ];
            }
        }

        if (! isset($mapped['amount'])) {
            $amountCandidate = $fields->first(fn (LoanFormFieldDefinition $f) => $f->data_type === LoanFormFieldDefinition::TYPE_NUMBER);
            if ($amountCandidate) {
                $mapped['amount'] = [
                    'key' => (string) $amountCandidate->field_key,
                    'label' => (string) $amountCandidate->label,
                    'data_type' => (string) $amountCandidate->data_type,
                    'select_options' => $this->splitSelectOptions((string) ($amountCandidate->select_options ?? '')),
                ];
            }
        }

        if (! isset($mapped['reason_for_request'])) {
            $reasonCandidate = $fields->first(fn (LoanFormFieldDefinition $f) => (string) $f->field_key !== (string) ($mapped['amount']['key'] ?? ''));
            if ($reasonCandidate) {
                $mapped['reason_for_request'] = [
                    'key' => (string) $reasonCandidate->field_key,
                    'label' => (string) $reasonCandidate->label,
                    'data_type' => (string) $reasonCandidate->data_type,
                    'select_options' => $this->splitSelectOptions((string) ($reasonCandidate->select_options ?? '')),
                ];
            }
        }

        return $mapped;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanFormFieldDefinition>  $fields
     * @param  array<string, array<string, mixed>>  $mapped
     * @return list<array<string, mixed>>
     */
    private function salaryAdvanceCustomFields(Collection $fields, array $mapped): array
    {
        $mappedKeys = collect($mapped)->pluck('key')->filter()->map(fn ($v) => (string) $v)->all();

        return $fields
            ->filter(fn (LoanFormFieldDefinition $f): bool => ! in_array((string) $f->field_key, $mappedKeys, true))
            ->map(fn (LoanFormFieldDefinition $f): array => [
                'key' => (string) $f->field_key,
                'label' => (string) $f->label,
                'data_type' => (string) $f->data_type,
                'select_options' => $this->splitSelectOptions((string) ($f->select_options ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function salaryAdvanceDynamicValidationRules(): array
    {
        if (! $this->salaryAdvanceFormMetaSupported()) {
            return [];
        }

        $rules = [];
        $fields = $this->salaryAdvanceFormDefinitions();
        $mapped = $this->mappedSalaryAdvanceFields($fields);
        foreach ($this->salaryAdvanceCustomFields($fields, $mapped) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $rules["form_files.$key"] = ['nullable', 'file', 'image', 'max:4096'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_NUMBER) {
                $rules["form_meta.$key"] = ['nullable', 'numeric'];
                continue;
            }
            if ($type === LoanFormFieldDefinition::TYPE_SELECT) {
                $options = collect((array) ($field['select_options'] ?? []))
                    ->map(fn (string $v): string => trim($v))
                    ->filter()
                    ->values()
                    ->all();
                $rules["form_meta.$key"] = $options !== []
                    ? ['nullable', 'string', Rule::in($options)]
                    : ['nullable', 'string', 'max:255'];
                continue;
            }
            $rules["form_meta.$key"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSalaryAdvanceFormMeta(Request $request, ?AccountingSalaryAdvance $existing = null): array
    {
        if (! $this->salaryAdvanceFormMetaSupported()) {
            return [];
        }

        $existingMeta = (array) ($existing?->form_meta ?? []);
        $meta = [];

        $fields = $this->salaryAdvanceFormDefinitions();
        $mapped = $this->mappedSalaryAdvanceFields($fields);
        foreach ($this->salaryAdvanceCustomFields($fields, $mapped) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $type = (string) ($field['data_type'] ?? LoanFormFieldDefinition::TYPE_ALPHANUMERIC);
            if ($type === LoanFormFieldDefinition::TYPE_IMAGE) {
                $inputKey = "form_files.$key";
                if ($request->hasFile($inputKey)) {
                    $file = $request->file($inputKey);
                    if ($file) {
                        $newPath = $file->store('salary-advances/form-meta', 'public');
                        $meta[$key] = $newPath;
                        $oldPath = (string) ($existingMeta[$key] ?? '');
                        if ($oldPath !== '' && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        continue;
                    }
                }
                if (array_key_exists($key, $existingMeta)) {
                    $meta[$key] = $existingMeta[$key];
                }
                continue;
            }

            $value = $request->input("form_meta.$key");
            if (is_array($value)) {
                $value = '';
            }
            $meta[$key] = trim((string) ($value ?? ''));
        }

        return $meta;
    }

    private function salaryAdvanceFormMetaSupported(): bool
    {
        return Schema::hasColumn('accounting_salary_advances', 'form_meta');
    }

    /**
     * @return list<string>
     */
    private function splitSelectOptions(string $options): array
    {
        return collect(explode(',', $options))
            ->map(fn (string $opt): string => trim($opt))
            ->filter()
            ->values()
            ->all();
    }

    /* ---------- Reports ---------- */

    public function expenseSummary(Request $request): View
    {
        $month = max(1, min(12, (int) $request->integer('month', now()->month)));
        $year = max(2000, min(2100, (int) $request->integer('year', now()->year)));
        $usingPresetMonth = $request->query->has('month') || $request->query->has('year');

        $presetStart = Carbon::create($year, $month, 1)->startOfMonth()->startOfDay();
        $presetEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        $fromRaw = (string) $request->query('from', $presetStart->toDateString());
        $toRaw = (string) $request->query('to', $presetEnd->toDateString());
        try {
            $fromDate = Carbon::parse($fromRaw)->startOfDay();
        } catch (\Throwable) {
            $fromDate = $presetStart->copy();
        }
        try {
            $toDate = Carbon::parse($toRaw)->endOfDay();
        } catch (\Throwable) {
            $toDate = $presetEnd->copy();
        }
        if ($usingPresetMonth) {
            $fromDate = $presetStart->copy();
            $toDate = $presetEnd->copy();
        }
        if ($toDate->lt($fromDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $utilities = (float) AccountingUtilityPayment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $pettyOut = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');

        $requisitionsPaid = (float) AccountingRequisition::query()
            ->where('status', AccountingRequisition::STATUS_PAID)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('amount');

        $advances = (float) AccountingSalaryAdvance::query()
            ->whereIn('status', [AccountingSalaryAdvance::STATUS_APPROVED, AccountingSalaryAdvance::STATUS_SETTLED])
            ->whereBetween('requested_on', [$from, $to])
            ->sum('amount');

        $expenseAccountIds = AccountingChartAccount::query()
            ->where('account_type', AccountingChartAccount::TYPE_EXPENSE)
            ->pluck('id');

        $jel = AccountingJournalLine::query()
            ->whereIn('accounting_chart_account_id', $expenseAccountIds)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->whereBetween('entry_date', [$from, $to]);
            });
        $journalExpense = (float) (clone $jel)->sum('debit') - (float) (clone $jel)->sum('credit');

        $total = $utilities + $pettyOut + $requisitionsPaid + $advances + $journalExpense;

        $utilitiesDaily = AccountingUtilityPayment::query()
            ->selectRaw('DATE(paid_on) as day_date, COALESCE(SUM(amount),0) as amount_total')
            ->whereBetween('paid_on', [$from, $to])
            ->groupBy('day_date')
            ->pluck('amount_total', 'day_date');
        $pettyDaily = AccountingPettyCashEntry::query()
            ->selectRaw('DATE(entry_date) as day_date, COALESCE(SUM(amount),0) as amount_total')
            ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
            ->whereBetween('entry_date', [$from, $to])
            ->groupBy('day_date')
            ->pluck('amount_total', 'day_date');
        $requisitionsDaily = AccountingRequisition::query()
            ->selectRaw('DATE(paid_at) as day_date, COALESCE(SUM(amount),0) as amount_total')
            ->where('status', AccountingRequisition::STATUS_PAID)
            ->whereBetween('paid_at', [$fromDate->toDateTimeString(), $toDate->toDateTimeString()])
            ->groupBy('day_date')
            ->pluck('amount_total', 'day_date');
        $advancesDaily = AccountingSalaryAdvance::query()
            ->selectRaw('DATE(requested_on) as day_date, COALESCE(SUM(amount),0) as amount_total')
            ->whereIn('status', [AccountingSalaryAdvance::STATUS_APPROVED, AccountingSalaryAdvance::STATUS_SETTLED])
            ->whereBetween('requested_on', [$from, $to])
            ->groupBy('day_date')
            ->pluck('amount_total', 'day_date');
        $journalDailyRows = AccountingJournalLine::query()
            ->selectRaw('DATE(accounting_journal_entries.entry_date) as day_date, COALESCE(SUM(accounting_journal_lines.debit),0) as dr_total, COALESCE(SUM(accounting_journal_lines.credit),0) as cr_total')
            ->join('accounting_journal_entries', 'accounting_journal_entries.id', '=', 'accounting_journal_lines.accounting_journal_entry_id')
            ->whereIn('accounting_chart_account_id', $expenseAccountIds)
            ->whereBetween('accounting_journal_entries.entry_date', [$from, $to])
            ->groupBy('day_date')
            ->get();
        $journalDaily = $journalDailyRows
            ->mapWithKeys(fn ($row) => [
                (string) $row->day_date => ((float) ($row->dr_total ?? 0) - (float) ($row->cr_total ?? 0)),
            ]);

        $calendarStart = $fromDate->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $toDate->copy()->endOfWeek(Carbon::SUNDAY);
        $weeks = [];
        $cursor = $calendarStart->copy();
        while ($cursor->lte($calendarEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $cursor->copy();
                $dayKey = $day->toDateString();
                $inRange = $day->betweenIncluded($fromDate, $toDate);
                $utilityAmount = (float) ($utilitiesDaily[$dayKey] ?? 0);
                $pettyAmount = (float) ($pettyDaily[$dayKey] ?? 0);
                $requisitionAmount = (float) ($requisitionsDaily[$dayKey] ?? 0);
                $advanceAmount = (float) ($advancesDaily[$dayKey] ?? 0);
                $journalAmount = (float) ($journalDaily[$dayKey] ?? 0);
                $dailyTotal = $utilityAmount + $pettyAmount + $requisitionAmount + $advanceAmount + $journalAmount;
                $week[] = [
                    'date' => $day,
                    'in_range' => $inRange,
                    'breakdown' => [
                        'Utilities' => $utilityAmount,
                        'Petty cash' => $pettyAmount,
                        'Requisitions' => $requisitionAmount,
                        'Salary advances' => $advanceAmount,
                        'Journal expense' => $journalAmount,
                    ],
                    'total' => $dailyTotal,
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('loan.accounting.expense-summary', compact(
            'from',
            'to',
            'utilities',
            'pettyOut',
            'requisitionsPaid',
            'advances',
            'journalExpense',
            'total',
            'weeks',
            'month',
            'year'
        ));
    }

    public function cashflow(Request $request): View
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $cashIds = AccountingChartAccount::query()->where('is_cash_account', true)->pluck('id');
        $jcl = AccountingJournalLine::query()
            ->whereIn('accounting_chart_account_id', $cashIds)
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->whereBetween('entry_date', [$from, $to]);
            });
        $journalCashNet = (float) (clone $jcl)->sum('debit') - (float) (clone $jcl)->sum('credit');

        $pettyIn = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_RECEIPT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');
        $pettyOut = (float) AccountingPettyCashEntry::query()
            ->where('kind', AccountingPettyCashEntry::KIND_DISBURSEMENT)
            ->whereBetween('entry_date', [$from, $to])
            ->sum('amount');
        $pettyNet = $pettyIn - $pettyOut;

        $utilitiesOut = (float) AccountingUtilityPayment::query()
            ->whereBetween('paid_on', [$from, $to])
            ->sum('amount');

        $reqPaid = (float) AccountingRequisition::query()
            ->where('status', AccountingRequisition::STATUS_PAID)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->sum('amount');

        $advancesOut = (float) AccountingSalaryAdvance::query()
            ->where('status', AccountingSalaryAdvance::STATUS_APPROVED)
            ->whereBetween('requested_on', [$from, $to])
            ->sum('amount');

        $operatingNet = $pettyNet - $utilitiesOut - $reqPaid - $advancesOut;
        $combinedEstimate = $journalCashNet + $operatingNet;

        return view('loan.accounting.cashflow', compact(
            'from',
            'to',
            'journalCashNet',
            'pettyIn',
            'pettyOut',
            'pettyNet',
            'utilitiesOut',
            'reqPaid',
            'advancesOut',
            'operatingNet',
            'combinedEstimate'
        ));
    }
}
