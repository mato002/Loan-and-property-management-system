<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingRule;
use App\Models\AccountingWalletSlotSetting;
use App\Models\ClientWalletTransaction;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanSystemSetting;
use App\Models\User;
use App\Services\LoanBook\LoanRepaymentAllocationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanBookGlPostingService
{
    public const RULE_LOAN_LEDGER = 'loan_ledger';

    public const RULE_LOAN_OVERPAYMENTS = 'loan_overpayments';

    private const SETTING_REPAYMENT_ORDER = 'loan_repayment_allocation_order';

    private const SETTING_ACCOUNT_PRINCIPAL = 'loan_account_code_principal';

    private const SETTING_ACCOUNT_INTEREST = 'loan_account_code_interest_income';

    private const SETTING_ACCOUNT_FEE = 'loan_account_code_fee_income';

    private const SETTING_ACCOUNT_PENALTY = 'loan_account_code_penalty_income';

    private const SETTING_ACCOUNT_OVERPAYMENT = 'loan_account_code_overpayment_liability';

    private const SETTING_ACCOUNT_COLLECTION = 'loan_account_code_collection';

    /**
     * Post a processed loan pay-in to the general ledger.
     * Convention for {@see self::RULE_LOAN_LEDGER}: debit = cash/bank, credit = loan portfolio / receivable.
     * C2B reversals (negative amounts) post the opposite movement using the same accounts.
     *
     * @throws \RuntimeException
     */
    public function postLoanPayment(LoanBookPayment $payment, ?User $user = null): AccountingJournalEntry
    {
        $this->assertAccountingSchema();

        if ($payment->accounting_journal_entry_id) {
            throw new \RuntimeException('This payment is already linked to a journal entry.');
        }

        $amount = round(abs((float) $payment->amount), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be non-zero to post to the general ledger.');
        }

        $eventRegistry = app(AccountingEventRegistryService::class);

        if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
            $reversalMap = $eventRegistry->resolveEventAccountIdsOrFail('ReversalPosted');
            $drId = (int) $reversalMap['debit_account_id'];
            $crId = (int) $reversalMap['credit_account_id'];

            $loan = $payment->loan;
            $loanLabel = $loan ? $loan->loan_number : 'Unallocated';
            $ref = $payment->reference ?? 'PAY-'.$payment->id;
            $description = 'Loan pay-in '.$ref.' — '.$loanLabel
                .' ('.$payment->payment_kind.', '.$payment->channel.')';

            return $this->persistEntry(
                $payment->transaction_at,
                $ref,
                $description,
                $user?->id,
                $drId,
                $crId,
                $amount
            );
        }

        if ($payment->payment_kind === LoanBookPayment::KIND_OVERPAYMENT) {
            $allocationResolver = app(LoanRepaymentAllocationService::class);
            $allocationResult = $allocationResolver->allocate($payment, $payment->loan, $amount);
            $allocationResolver->persistAllocation($payment, $allocationResult['allocations'], $allocationResult['order']);
            $overpaymentMap = $eventRegistry->resolveEventAccountIdsOrFail('LoanOverpayment');
            $loan = $payment->loan;
            $loanLabel = $loan ? $loan->loan_number : 'Unallocated';
            $ref = $payment->reference ?? 'PAY-'.$payment->id;
            $description = 'Loan pay-in '.$ref.' — '.$loanLabel
                .' ('.$payment->payment_kind.', '.$payment->channel.')';

            return $this->persistEntry(
                $payment->transaction_at,
                $ref,
                $description,
                $user?->id,
                (int) $overpaymentMap['debit_account_id'],
                (int) $overpaymentMap['credit_account_id'],
                $amount
            );
        }

        $allocationResolver = app(LoanRepaymentAllocationService::class);
        $allocationResult = $allocationResolver->allocate($payment, $payment->loan, $amount);
        $allocationResolver->persistAllocation($payment, $allocationResult['allocations'], $allocationResult['order']);
        $allocation = $allocationResult['allocations'];
        if (round((float) array_sum($allocation), 2) <= 0.0) {
            throw new \RuntimeException('Unable to allocate payment to accounting components.');
        }

        if ((bool) ($payment->funded_from_wallet ?? false)) {
            $debitLines = [];
            $creditLines = [];
            foreach ($allocation as $component => $componentAmount) {
                if ($componentAmount <= 0.0 || $component === 'overpayment') {
                    continue;
                }
                $componentMap = $eventRegistry->resolveEventAccountIdsOrFail($this->eventKeyForPaymentComponent((string) $component));
                $componentDebit = (int) $componentMap['debit_account_id'];
                $componentCredit = (int) $componentMap['credit_account_id'];
                $debitLines[$componentDebit] = ($debitLines[$componentDebit] ?? 0.0) + $componentAmount;
                $creditLines[$componentCredit] = ($creditLines[$componentCredit] ?? 0.0) + $componentAmount;
            }
            if ($debitLines === [] || $creditLines === []) {
                throw new \RuntimeException('Wallet-funded payment has no allocatable components to post.');
            }

            $loan = $payment->loan;
            $loanLabel = $loan ? $loan->loan_number : 'Unallocated';
            $ref = $payment->reference ?? 'PAY-'.$payment->id;
            $description = 'Wallet-funded loan pay-in '.$ref.' — '.$loanLabel
                .' ('.$payment->payment_kind.', '.$payment->channel.')';

            return $this->persistMultiLineEntry(
                $payment->transaction_at,
                $ref,
                $description,
                $user?->id,
                $debitLines,
                $creditLines
            );
        }

        $receiveMap = $eventRegistry->resolveEventAccountIdsOrFail('ClientPaymentReceived');
        $cashAccountId = (int) $receiveMap['debit_account_id'];
        $walletAccountId = (int) $receiveMap['credit_account_id'];
        $debitLines = [$cashAccountId => $amount];
        $creditLines = [$walletAccountId => $amount];
        foreach ($allocation as $component => $componentAmount) {
            if ($componentAmount <= 0.0) {
                continue;
            }
            if ($component === 'overpayment') {
                continue;
            }
            $componentMap = $eventRegistry->resolveEventAccountIdsOrFail($this->eventKeyForPaymentComponent($component));
            $componentDebit = (int) $componentMap['debit_account_id'];
            $componentCredit = (int) $componentMap['credit_account_id'];
            $debitLines[$componentDebit] = ($debitLines[$componentDebit] ?? 0.0) + $componentAmount;
            $creditLines[$componentCredit] = ($creditLines[$componentCredit] ?? 0.0) + $componentAmount;
        }

        $loan = $payment->loan;
        $loanLabel = $loan ? $loan->loan_number : 'Unallocated';
        $ref = $payment->reference ?? 'PAY-'.$payment->id;
        $description = 'Loan pay-in '.$ref.' — '.$loanLabel
            .' ('.$payment->payment_kind.', '.$payment->channel.')';

        return $this->persistMultiLineEntry(
            $payment->transaction_at,
            $ref,
            $description,
            $user?->id,
            $debitLines,
            $creditLines
        );
    }

    /**
     * Post a collection-sheet receipt as cash inflow (same accounts as a normal pay-in).
     * Use only when this receipt is not also recorded as a processed LoanBook payment, to avoid double-counting in the GL.
     *
     * @throws \RuntimeException
     */
    public function postCollectionEntry(LoanBookCollectionEntry $entry, ?User $user = null): AccountingJournalEntry
    {
        $this->assertAccountingSchema();

        if ($entry->accounting_journal_entry_id) {
            throw new \RuntimeException('This collection line is already linked to a journal entry.');
        }

        $amount = round(abs((float) $entry->amount), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be non-zero to post to the general ledger.');
        }

        $mapped = app(AccountingEventRegistryService::class)->resolveEventAccountIdsOrFail('ClientPaymentReceived');
        $drId = (int) $mapped['debit_account_id'];
        $crId = (int) $mapped['credit_account_id'];

        $loan = $entry->loan;
        $loanLabel = $loan ? $loan->loan_number : 'Loan #'.$entry->loan_book_loan_id;
        $ref = 'COLL-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT);
        $description = 'Collection sheet '.$ref.' — '.$loanLabel.' ('.$entry->channel.')';

        return $this->persistEntry(
            $entry->collected_on,
            $ref,
            $description,
            $user?->id,
            $drId,
            $crId,
            $amount
        );
    }

    /**
     * Post an approved client refund: debit client wallet liability, credit collection cash.
     *
     * @throws \RuntimeException
     */
    public function postRefundIssued(
        float $amount,
        string $reference,
        string $description,
        ?User $user = null,
        mixed $entryDate = null
    ): AccountingJournalEntry {
        $this->assertAccountingSchema();
        $value = round(abs($amount), 2);
        if ($value <= 0.0) {
            throw new \RuntimeException('Refund amount must be non-zero to post to the general ledger.');
        }

        $mapped = app(AccountingEventRegistryService::class)->resolveEventAccountIdsOrFail('RefundIssued');
        $walletDr = (int) $mapped['debit_account_id'];
        $cashCr = (int) $mapped['credit_account_id'];
        $date = $entryDate ? now()->parse((string) $entryDate) : now();

        return $this->persistEntry(
            $date,
            $reference,
            $description,
            $user?->id,
            $walletDr,
            $cashCr,
            $value
        );
    }

    /**
     * Post a manual client-wallet adjustment to the GL (WalletAdjustment event).
     * Wallet debit (reducing client balance): DR client wallet liability, CR adjustment offset.
     * Wallet credit (increasing client balance): DR adjustment offset, CR client wallet liability.
     *
     * @param  string  $walletTransactionType  {@see ClientWalletTransaction::TYPE_DEBIT} or {@see ClientWalletTransaction::TYPE_CREDIT}
     *
     * @throws \RuntimeException
     */
    public function postWalletAdjustment(
        float $amount,
        string $walletTransactionType,
        string $reference,
        string $description,
        ?int $actorUserId = null,
        mixed $entryDate = null
    ): AccountingJournalEntry {
        $this->assertAccountingSchema();
        $value = round(abs($amount), 2);
        if ($value <= 0.0) {
            throw new \RuntimeException('Wallet adjustment amount must be non-zero to post to the general ledger.');
        }

        $mapped = app(AccountingEventRegistryService::class)->resolveEventAccountIdsOrFail('WalletAdjustment');
        $walletLiabilityId = (int) $mapped['debit_account_id'];
        $adjustmentId = (int) $mapped['credit_account_id'];
        $date = $entryDate ? now()->parse((string) $entryDate) : now();

        if ($walletTransactionType === ClientWalletTransaction::TYPE_DEBIT) {
            return $this->persistEntry(
                $date,
                $reference,
                $description,
                $actorUserId,
                $walletLiabilityId,
                $adjustmentId,
                $value
            );
        }

        if ($walletTransactionType === ClientWalletTransaction::TYPE_CREDIT) {
            return $this->persistEntry(
                $date,
                $reference,
                $description,
                $actorUserId,
                $adjustmentId,
                $walletLiabilityId,
                $value
            );
        }

        throw new \RuntimeException('Unsupported wallet transaction type for GL adjustment: '.$walletTransactionType);
    }

    /**
     * Post a disbursement: debit loan receivable (portfolio), credit cash/bank.
     *
     * @throws \RuntimeException
     */
    public function postDisbursement(LoanBookDisbursement $disbursement, ?User $user): AccountingJournalEntry
    {
        $this->assertAccountingSchema();

        if ($disbursement->accounting_journal_entry_id) {
            throw new \RuntimeException('This disbursement is already linked to a journal entry.');
        }

        $amount = round(abs((float) $disbursement->amount), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be non-zero to post to the general ledger.');
        }

        $mapped = app(AccountingEventRegistryService::class)->resolveEventAccountIdsOrFail('LoanDisbursed');
        $loanDr = (int) $mapped['debit_account_id'];
        $cashCr = (int) $mapped['credit_account_id'];

        $loan = $disbursement->loan;
        $loanLabel = $loan ? $loan->loan_number : 'Loan #'.$disbursement->loan_book_loan_id;
        $description = 'Loan disbursement '.$disbursement->reference.' — '.$loanLabel.' ('.$disbursement->method.')';

        return $this->persistEntry(
            $disbursement->disbursed_at,
            $disbursement->reference,
            $description,
            $user?->id,
            (int) $loanDr,
            (int) $cashCr,
            $amount
        );
    }

    /**
     * Post an accrued loan penalty immediately:
     * debit principal receivable / loan book, credit penalty income.
     *
     * @throws \RuntimeException
     */
    public function postLoanPenaltyAccrual(
        LoanBookLoan $loan,
        float $amount,
        string $reference,
        string $description,
        ?User $user = null,
        mixed $entryDate = null
    ): AccountingJournalEntry {
        $this->assertAccountingSchema();
        $value = round(abs($amount), 2);
        if ($value <= 0.0) {
            throw new \RuntimeException('Penalty amount must be non-zero to post to the general ledger.');
        }

        $rule = AccountingPostingRule::query()->where('rule_key', self::RULE_LOAN_LEDGER)->first();
        if (! $rule) {
            throw new \RuntimeException('Configure the "Loan Ledger" posting rule under Chart of accounts.');
        }

        $principalId = $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_PRINCIPAL, '1200')
            ?: $this->resolveCreditAccountId($rule);
        if (! $principalId) {
            throw new \RuntimeException('Set principal/loan receivable account mapping before accruing penalties.');
        }

        $penaltyIncomeId = $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_PENALTY, '4003')
            ?: $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_FEE, '4007');
        if (! $penaltyIncomeId) {
            throw new \RuntimeException('Set penalty income account mapping before accruing penalties.');
        }

        $date = $entryDate ? now()->parse((string) $entryDate) : now();

        return $this->persistEntry(
            $date,
            $reference,
            $description,
            $user?->id,
            (int) $principalId,
            (int) $penaltyIncomeId,
            $value
        );
    }

    private function assertAccountingSchema(): void
    {
        foreach (['accounting_journal_entries', 'accounting_journal_lines', 'accounting_chart_accounts', 'accounting_posting_rules', 'loan_payment_allocations'] as $t) {
            if (! Schema::hasTable($t)) {
                throw new \RuntimeException('Accounting tables are missing. Run migrations first.');
            }
        }
    }

    private function resolveRuleForPayment(LoanBookPayment $payment): AccountingPostingRule
    {
        if ($payment->payment_kind === LoanBookPayment::KIND_OVERPAYMENT) {
            $r = AccountingPostingRule::query()->where('rule_key', self::RULE_LOAN_OVERPAYMENTS)->first();
            if ($r && $r->debit_account_id && $r->credit_account_id) {
                return $r;
            }
        }

        $ledger = AccountingPostingRule::query()->where('rule_key', self::RULE_LOAN_LEDGER)->first();
        if (! $ledger) {
            throw new \RuntimeException('Configure the "Loan Ledger" posting rule under Chart of accounts.');
        }

        return $ledger;
    }

    /**
     * @return array<string, float>
     */
    private function allocatePaymentByComponent(LoanBookPayment $payment, float $amount): array
    {
        $result = app(LoanRepaymentAllocationService::class)->allocate($payment, $payment->loan, $amount);

        return array_filter(
            $result['allocations'],
            static fn (float $v): bool => round($v, 2) > 0.0
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolvePaymentDebitCredit(LoanBookPayment $payment, AccountingPostingRule $rule, ?int $cashId): array
    {
        $isReversal = $payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL;

        if ($isReversal) {
            return $this->resolveReversalDebitCredit($rule, $cashId);
        }

        return $this->resolveInflowDebitCredit($rule, $cashId);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveInflowDebitCredit(AccountingPostingRule $rule, ?int $cashId): array
    {
        $ruleDr = $rule->debit_account_id;
        $ruleCr = $this->resolveCreditAccountId($rule);

        if (! $ruleCr) {
            throw new \RuntimeException('Set the credit account on the posting rule (loan portfolio / receivable).');
        }

        $cashSide = $ruleDr ?? $cashId;
        if (! $cashSide) {
            throw new \RuntimeException('Map a cash or transactional wallet account on the chart, or set the debit account on the Loan Ledger rule (cash/bank).');
        }

        return [(int) $cashSide, (int) $ruleCr];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveReversalDebitCredit(AccountingPostingRule $rule, ?int $cashId): array
    {
        $ruleDr = $rule->debit_account_id;
        $ruleCr = $this->resolveCreditAccountId($rule);

        if (! $ruleCr) {
            throw new \RuntimeException('Set the credit account on the posting rule (loan portfolio / receivable).');
        }

        $cashSide = $ruleDr ?? $cashId;
        if (! $cashSide) {
            throw new \RuntimeException('Map a cash or transactional wallet account on the chart, or set the debit account on the Loan Ledger rule (cash/bank).');
        }

        return [(int) $ruleCr, (int) $cashSide];
    }

    /**
     * @return list<'principal'|'interest'|'fees'|'penalty'>
     */
    private function repaymentOrder(): array
    {
        return app(LoanRepaymentAllocationService::class)->repaymentOrder();
    }

    private function resolveCashAccountId(string $channel): ?int
    {
        $ch = strtolower($channel);
        $slotKey = (str_contains($ch, 'cash') && ! str_contains($ch, 'mpesa')) ? 'cash_account' : 'transactional_account';

        $slot = AccountingWalletSlotSetting::query()->where('slot_key', $slotKey)->first();
        if ($slot?->accounting_chart_account_id) {
            return (int) $slot->accounting_chart_account_id;
        }

        $fallback = AccountingWalletSlotSetting::query()->where('slot_key', 'transactional_account')->first();
        if ($fallback?->accounting_chart_account_id) {
            return (int) $fallback->accounting_chart_account_id;
        }

        return AccountingChartAccount::query()
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->orderBy('code')
            ->value('id');
    }

    private function resolveCollectionAccountId(string $channel, AccountingPostingRule $rule): ?int
    {
        $preferredCode = trim((string) (LoanSystemSetting::getValue(self::SETTING_ACCOUNT_COLLECTION, '1004') ?? '1004'));
        if ($preferredCode !== '') {
            $id = (int) (AccountingChartAccount::query()->where('code', $preferredCode)->where('is_active', true)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if ($rule->debit_account_id) {
            return (int) $rule->debit_account_id;
        }

        return $this->resolveCashAccountId($channel);
    }

    private function resolveComponentAccountId(string $component, AccountingPostingRule $fallbackRule): ?int
    {
        return match ($component) {
            'principal' => $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_PRINCIPAL, '1200')
                ?: $this->resolveCreditAccountId($fallbackRule),
            'interest' => $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_INTEREST, '4002'),
            'fees' => $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_FEE, '4007'),
            'penalty' => $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_PENALTY, '4003')
                ?: $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_FEE, '4007'),
            'overpayment' => $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_OVERPAYMENT, '2003'),
            default => null,
        };
    }

    private function resolveComponentFallbackAccountId(AccountingPostingRule $fallbackRule): ?int
    {
        return $this->resolveAccountBySettingCode(self::SETTING_ACCOUNT_PRINCIPAL, '1200')
            ?: $this->resolveCreditAccountId($fallbackRule);
    }

    private function eventKeyForPaymentComponent(string $component): string
    {
        return match ($component) {
            'principal' => 'PrincipalAllocated',
            'interest' => 'InterestReceived',
            'fees' => 'FeeReceived',
            'penalty' => 'PenaltyReceived',
            default => throw new \RuntimeException('Unsupported payment component: '.$component),
        };
    }

    private function resolveAccountBySettingCode(string $settingKey, string $defaultCode): ?int
    {
        $code = trim((string) (LoanSystemSetting::getValue($settingKey, $defaultCode) ?? $defaultCode));
        if ($code === '') {
            return null;
        }

        $id = (int) (AccountingChartAccount::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private function resolveCashAccountIdFromMethod(string $method): ?int
    {
        return $this->resolveCashAccountId($method);
    }

    private function resolveCreditAccountId(AccountingPostingRule $rule): ?int
    {
        if ($rule->credit_account_id) {
            return (int) $rule->credit_account_id;
        }

        if ($rule->rule_key !== self::RULE_LOAN_LEDGER) {
            return null;
        }

        $fallback = AccountingChartAccount::query()
            ->where('is_active', true)
            ->where('account_type', 'asset')
            ->where(function ($q) {
                $q->where('name', 'like', '%receivable%')
                    ->orWhere('name', 'like', '%loan portfolio%')
                    ->orWhere('name', 'like', '%loan book%')
                    ->orWhere('name', 'like', '%loan asset%')
                    ->orWhere('code', 'like', '12%')
                    ->orWhere('code', 'like', '13%');
            })
            ->orderBy('code')
            ->value('id');

        if ($fallback) {
            AccountingPostingRule::query()
                ->where('id', $rule->id)
                ->update(['credit_account_id' => (int) $fallback]);

            return (int) $fallback;
        }

        return null;
    }

    private function persistEntry(
        CarbonInterface $entryDate,
        string $reference,
        string $description,
        ?int $userId,
        int $debitAccountId,
        int $creditAccountId,
        float $amount
    ): AccountingJournalEntry {
        if ($debitAccountId === $creditAccountId) {
            throw new \RuntimeException('Debit and credit accounts must differ.');
        }

        $debitAccount = AccountingChartAccount::query()->find($debitAccountId);
        $creditAccount = AccountingChartAccount::query()->find($creditAccountId);
        if (! $debitAccount || ! $creditAccount) {
            throw new \RuntimeException('Posting account is missing.');
        }
        if ($debitAccount->isHeader() || $creditAccount->isHeader()) {
            throw new \RuntimeException('Journal entries can only post to Detail accounts.');
        }
        app(AccountingOverdraftGuardService::class)->assertCanApplyDeltas([
            $debitAccountId => $amount,
            $creditAccountId => 0 - $amount,
        ]);

        $entry = AccountingJournalEntry::query()->create([
            'entry_date' => $entryDate->toDateString(),
            'reference' => Str::limit($reference, 64, ''),
            'description' => Str::limit($description, 2000, ''),
            'created_by' => $userId,
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => 0,
            'memo' => null,
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $creditAccountId,
            'debit' => 0,
            'credit' => $amount,
            'memo' => null,
        ]);

        app(AccountingChartBalanceService::class)
            ->syncAccountsAndAncestors([$debitAccountId, $creditAccountId]);

        return $entry;
    }

    /**
     * @param  array<int, float>  $creditLinesByAccount
     */
    private function persistSplitEntry(
        CarbonInterface $entryDate,
        string $reference,
        string $description,
        ?int $userId,
        int $debitAccountId,
        array $creditLinesByAccount
    ): AccountingJournalEntry {
        $creditLinesByAccount = array_filter($creditLinesByAccount, static fn ($v): bool => round((float) $v, 2) > 0.0);
        if ($creditLinesByAccount === []) {
            throw new \RuntimeException('No credit lines to post.');
        }

        $debitAccount = AccountingChartAccount::query()->find($debitAccountId);
        if (! $debitAccount) {
            throw new \RuntimeException('Debit posting account is missing.');
        }
        if ($debitAccount->isHeader()) {
            throw new \RuntimeException('Journal entries can only post to Detail accounts.');
        }

        $creditTotal = round(array_sum(array_map(static fn ($v): float => (float) $v, $creditLinesByAccount)), 2);
        if ($creditTotal <= 0.0) {
            throw new \RuntimeException('Credit total must be greater than zero.');
        }

        $deltas = [$debitAccountId => $creditTotal];
        foreach ($creditLinesByAccount as $accountId => $amount) {
            $account = AccountingChartAccount::query()->find((int) $accountId);
            if (! $account) {
                throw new \RuntimeException('Credit posting account is missing.');
            }
            if ($account->isHeader()) {
                throw new \RuntimeException('Journal entries can only post to Detail accounts.');
            }
            if ((int) $accountId === $debitAccountId) {
                throw new \RuntimeException('Debit and credit accounts must differ.');
            }
            $deltas[(int) $accountId] = ($deltas[(int) $accountId] ?? 0.0) - round((float) $amount, 2);
        }
        app(AccountingOverdraftGuardService::class)->assertCanApplyDeltas($deltas);

        $entry = AccountingJournalEntry::query()->create([
            'entry_date' => $entryDate->toDateString(),
            'reference' => Str::limit($reference, 64, ''),
            'description' => Str::limit($description, 2000, ''),
            'created_by' => $userId,
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $debitAccountId,
            'debit' => $creditTotal,
            'credit' => 0,
            'memo' => null,
        ]);

        $syncIds = [$debitAccountId];
        foreach ($creditLinesByAccount as $accountId => $amount) {
            AccountingJournalLine::query()->create([
                'accounting_journal_entry_id' => $entry->id,
                'accounting_chart_account_id' => (int) $accountId,
                'debit' => 0,
                'credit' => round((float) $amount, 2),
                'memo' => null,
            ]);
            $syncIds[] = (int) $accountId;
        }

        app(AccountingChartBalanceService::class)
            ->syncAccountsAndAncestors(array_values(array_unique($syncIds)));

        return $entry;
    }

    /**
     * @param  array<int, float>  $debitLinesByAccount
     * @param  array<int, float>  $creditLinesByAccount
     */
    private function persistMultiLineEntry(
        CarbonInterface $entryDate,
        string $reference,
        string $description,
        ?int $userId,
        array $debitLinesByAccount,
        array $creditLinesByAccount
    ): AccountingJournalEntry {
        $debitLinesByAccount = array_filter($debitLinesByAccount, static fn ($v): bool => round((float) $v, 2) > 0.0);
        $creditLinesByAccount = array_filter($creditLinesByAccount, static fn ($v): bool => round((float) $v, 2) > 0.0);
        if ($debitLinesByAccount === [] || $creditLinesByAccount === []) {
            throw new \RuntimeException('Posting lines are incomplete.');
        }

        $debitTotal = round(array_sum(array_map(static fn ($v): float => (float) $v, $debitLinesByAccount)), 2);
        $creditTotal = round(array_sum(array_map(static fn ($v): float => (float) $v, $creditLinesByAccount)), 2);
        if ($debitTotal <= 0.0 || $creditTotal <= 0.0 || $debitTotal !== $creditTotal) {
            throw new \RuntimeException('Posting lines are not balanced.');
        }

        $accountIds = array_values(array_unique(array_merge(array_keys($debitLinesByAccount), array_keys($creditLinesByAccount))));
        $accounts = AccountingChartAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');
        foreach ($accountIds as $accountId) {
            $account = $accounts->get((int) $accountId);
            if (! $account) {
                throw new \RuntimeException('Posting account is missing.');
            }
            if ($account->isHeader()) {
                throw new \RuntimeException('Journal entries can only post to Detail accounts.');
            }
        }

        $deltas = [];
        foreach ($debitLinesByAccount as $accountId => $amount) {
            $deltas[(int) $accountId] = ($deltas[(int) $accountId] ?? 0.0) + round((float) $amount, 2);
        }
        foreach ($creditLinesByAccount as $accountId => $amount) {
            $deltas[(int) $accountId] = ($deltas[(int) $accountId] ?? 0.0) - round((float) $amount, 2);
        }
        app(AccountingOverdraftGuardService::class)->assertCanApplyDeltas($deltas);

        $entry = AccountingJournalEntry::query()->create([
            'entry_date' => $entryDate->toDateString(),
            'reference' => Str::limit($reference, 64, ''),
            'description' => Str::limit($description, 2000, ''),
            'created_by' => $userId,
        ]);

        foreach ($debitLinesByAccount as $accountId => $amount) {
            AccountingJournalLine::query()->create([
                'accounting_journal_entry_id' => $entry->id,
                'accounting_chart_account_id' => (int) $accountId,
                'debit' => round((float) $amount, 2),
                'credit' => 0,
                'memo' => null,
            ]);
        }
        foreach ($creditLinesByAccount as $accountId => $amount) {
            AccountingJournalLine::query()->create([
                'accounting_journal_entry_id' => $entry->id,
                'accounting_chart_account_id' => (int) $accountId,
                'debit' => 0,
                'credit' => round((float) $amount, 2),
                'memo' => null,
            ]);
        }

        app(AccountingChartBalanceService::class)
            ->syncAccountsAndAncestors($accountIds);

        return $entry;
    }
}
