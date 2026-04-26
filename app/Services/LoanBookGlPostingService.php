<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingRule;
use App\Models\AccountingWalletSlotSetting;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookPayment;
use App\Models\LoanBookLoan;
use App\Models\LoanSystemSetting;
use App\Models\User;
use App\Services\AccountingChartBalanceService;
use App\Services\AccountingOverdraftGuardService;
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

        $rule = $this->resolveRuleForPayment($payment);
        $cashId = $this->resolveCollectionAccountId((string) $payment->channel, $rule);

        if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
            [$drId, $crId] = $this->resolvePaymentDebitCredit($payment, $rule, $cashId);

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

        $allocation = $this->allocatePaymentByComponent($payment, $amount);
        if ($allocation === []) {
            throw new \RuntimeException('Unable to allocate payment to accounting components.');
        }

        $creditLines = [];
        foreach ($allocation as $component => $componentAmount) {
            if ($componentAmount <= 0.0) {
                continue;
            }
            $accountId = $this->resolveComponentAccountId($component, $rule);
            if (! $accountId) {
                $accountId = $this->resolveComponentFallbackAccountId($rule);
            }
            if (! $accountId) {
                throw new \RuntimeException('Set account mapping for payment component: '.$component.'.');
            }
            $creditLines[$accountId] = ($creditLines[$accountId] ?? 0.0) + $componentAmount;
        }
        if ($creditLines === []) {
            throw new \RuntimeException('No accounting credit components were produced from this payment.');
        }

        $loan = $payment->loan;
        $loanLabel = $loan ? $loan->loan_number : 'Unallocated';
        $ref = $payment->reference ?? 'PAY-'.$payment->id;
        $description = 'Loan pay-in '.$ref.' — '.$loanLabel
            .' ('.$payment->payment_kind.', '.$payment->channel.')';

        return $this->persistSplitEntry(
            $payment->transaction_at,
            $ref,
            $description,
            $user?->id,
            (int) $cashId,
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

        $rule = AccountingPostingRule::query()->where('rule_key', self::RULE_LOAN_LEDGER)->first();
        if (! $rule) {
            throw new \RuntimeException('Configure the "Loan Ledger" posting rule under Chart of accounts.');
        }

        $cashId = $this->resolveCashAccountId((string) $entry->channel);
        [$drId, $crId] = $this->resolveInflowDebitCredit($rule, $cashId);

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

        $rule = AccountingPostingRule::query()->where('rule_key', self::RULE_LOAN_LEDGER)->first();
        if (! $rule) {
            throw new \RuntimeException('Configure the "Loan Ledger" posting rule under Chart of accounts before recording disbursements.');
        }

        $cashId = $this->resolveCashAccountIdFromMethod((string) $disbursement->method);
        $loanDr = $this->resolveCreditAccountId($rule);
        $cashCr = $rule->debit_account_id ?? $cashId;

        if (! $loanDr) {
            throw new \RuntimeException('Set the credit account on the Loan Ledger rule (loan portfolio / receivable).');
        }
        if (! $cashCr) {
            throw new \RuntimeException('Set the debit account on the Loan Ledger rule to your main cash/bank account, or map wallet "Transactional" / "Cash" accounts.');
        }
        if ((int) $loanDr === (int) $cashCr) {
            throw new \RuntimeException('Loan Ledger debit and credit cannot be the same account.');
        }

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
        foreach (['accounting_journal_entries', 'accounting_journal_lines', 'accounting_chart_accounts', 'accounting_posting_rules'] as $t) {
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
        if ($payment->payment_kind === LoanBookPayment::KIND_OVERPAYMENT) {
            return ['overpayment' => $amount];
        }

        $loan = $payment->loan;
        if (! $loan) {
            return ['principal' => $amount];
        }

        $remaining = $amount;
        $principal = max(0.0, (float) ($loan->principal_outstanding ?? 0.0));
        $interest = max(0.0, (float) ($loan->interest_outstanding ?? 0.0));
        $fees = max(0.0, (float) ($loan->fees_outstanding ?? 0.0));
        if ($principal <= 0.0 && $interest <= 0.0 && $fees <= 0.0) {
            $principal = max(0.0, (float) ($loan->balance ?? 0.0));
        }

        $allocated = [
            'principal' => 0.0,
            'interest' => 0.0,
            'fees' => 0.0,
            'penalty' => 0.0,
            'overpayment' => 0.0,
        ];

        foreach ($this->repaymentOrder() as $bucket) {
            if ($remaining <= 0.0) {
                break;
            }
            if ($bucket === 'principal') {
                $apply = min($remaining, $principal);
                $principal = round($principal - $apply, 2);
                $allocated['principal'] += $apply;
                $remaining -= $apply;
                continue;
            }
            if ($bucket === 'interest') {
                $apply = min($remaining, $interest);
                $interest = round($interest - $apply, 2);
                $allocated['interest'] += $apply;
                $remaining -= $apply;
                continue;
            }
            if ($bucket === 'fees') {
                $apply = min($remaining, $fees);
                $fees = round($fees - $apply, 2);
                $allocated['fees'] += $apply;
                $remaining -= $apply;
                continue;
            }
            if ($bucket === 'penalty') {
                $apply = min($remaining, $fees);
                $fees = round($fees - $apply, 2);
                $allocated['penalty'] += $apply;
                $remaining -= $apply;
            }
        }

        if ($remaining > 0.0) {
            $allocated['overpayment'] += $remaining;
        }

        return array_filter($allocated, static fn (float $v): bool => round($v, 2) > 0.0);
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
        $raw = (string) (LoanSystemSetting::getValue(self::SETTING_REPAYMENT_ORDER, 'principal,interest,fees,penalty') ?? '');
        $parts = array_values(array_filter(array_map(
            static fn (string $p) => strtolower(trim($p)),
            explode(',', $raw)
        )));
        $valid = ['principal', 'interest', 'fees', 'penalty'];
        $order = array_values(array_intersect($parts, $valid));
        foreach ($valid as $v) {
            if (! in_array($v, $order, true)) {
                $order[] = $v;
            }
        }

        return $order;
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
}
