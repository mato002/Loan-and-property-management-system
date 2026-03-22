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
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanBookGlPostingService
{
    public const RULE_LOAN_LEDGER = 'loan_ledger';

    public const RULE_LOAN_OVERPAYMENTS = 'loan_overpayments';

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
        $cashId = $this->resolveCashAccountId((string) $payment->channel);

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
        $loanDr = $rule->credit_account_id;
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
        $ruleCr = $rule->credit_account_id;

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
        $ruleCr = $rule->credit_account_id;

        if (! $ruleCr) {
            throw new \RuntimeException('Set the credit account on the posting rule (loan portfolio / receivable).');
        }

        $cashSide = $ruleDr ?? $cashId;
        if (! $cashSide) {
            throw new \RuntimeException('Map a cash or transactional wallet account on the chart, or set the debit account on the Loan Ledger rule (cash/bank).');
        }

        return [(int) $ruleCr, (int) $cashSide];
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

    private function resolveCashAccountIdFromMethod(string $method): ?int
    {
        return $this->resolveCashAccountId($method);
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

        return $entry;
    }
}
