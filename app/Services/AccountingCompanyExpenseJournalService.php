<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingCompanyExpense;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Posts company expenses to the GL using the configured {@see ExpensePaid} event mapping.
 * Does not alter loan payment or other journal pipelines.
 */
class AccountingCompanyExpenseJournalService
{
    public function __construct(
        private AccountingEventRegistryService $events,
        private AccountingOverdraftGuardService $overdraftGuard,
        private AccountingChartBalanceService $balanceService,
    ) {}

    /**
     * @throws \RuntimeException
     */
    public function postJournalForExpense(AccountingCompanyExpense $expense, ?User $user): AccountingJournalEntry
    {
        $amount = round(abs((float) $expense->amount), 2);
        if ($amount <= 0.0) {
            throw new \RuntimeException('Expense amount must be greater than zero to post.');
        }

        $map = $this->events->resolveEventAccountIdsOrFail('ExpensePaid');
        $debitId = (int) $map['debit_account_id'];
        $creditId = (int) $map['credit_account_id'];
        if ($debitId === $creditId) {
            throw new \RuntimeException('ExpensePaid mapping must use two different accounts.');
        }

        $debitAccount = AccountingChartAccount::query()->find($debitId);
        $creditAccount = AccountingChartAccount::query()->find($creditId);
        if (! $debitAccount || ! $creditAccount) {
            throw new \RuntimeException('ExpensePaid mapping points to missing chart accounts.');
        }
        if ($debitAccount->isHeader() || $creditAccount->isHeader()) {
            throw new \RuntimeException('ExpensePaid mapping cannot use header accounts.');
        }

        $this->overdraftGuard->assertCanApplyDeltas([
            $debitId => $amount,
            $creditId => 0 - $amount,
        ]);

        $ref = 'CE-'.$expense->id;
        $description = 'Company expense: '.Str::limit((string) ($expense->title ?? ''), 180, '');

        $entry = AccountingJournalEntry::query()->create([
            'entry_date' => $expense->expense_date?->toDateString() ?? now()->toDateString(),
            'reference' => Str::limit($ref, 64, ''),
            'description' => Str::limit($description, 2000, ''),
            'created_by' => $user?->id,
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $debitId,
            'debit' => $amount,
            'credit' => 0,
            'memo' => Str::limit((string) ($expense->reference ?? ''), 500, ''),
        ]);

        AccountingJournalLine::query()->create([
            'accounting_journal_entry_id' => $entry->id,
            'accounting_chart_account_id' => $creditId,
            'debit' => 0,
            'credit' => $amount,
            'memo' => null,
        ]);

        $this->balanceService->syncAccountsAndAncestors([$debitId, $creditId]);

        return $entry;
    }

    /**
     * Remove an existing GL link for this expense (lines removed; balances recomputed).
     *
     * @param  list<int>  $accountIdsHint
     */
    public function deleteJournalForExpense(AccountingCompanyExpense $expense, array $accountIdsHint = []): void
    {
        $entryId = (int) ($expense->accounting_journal_entry_id ?? 0);
        if ($entryId <= 0) {
            return;
        }

        $lineAccountIds = AccountingJournalLine::query()
            ->where('accounting_journal_entry_id', $entryId)
            ->pluck('accounting_chart_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $syncIds = array_values(array_unique(array_merge($accountIdsHint, $lineAccountIds)));

        AccountingJournalEntry::query()->whereKey($entryId)->delete();

        if ($syncIds !== []) {
            $this->balanceService->syncAccountsAndAncestors($syncIds);
        }
    }

    /**
     * Replace the journal for an expense after edits (delete prior entry, post fresh).
     *
     * @throws \RuntimeException
     */
    public function replaceJournalForExpense(AccountingCompanyExpense $expense, ?User $user): AccountingJournalEntry
    {
        $oldHint = [];
        if ((int) ($expense->accounting_journal_entry_id ?? 0) > 0) {
            $oldHint = AccountingJournalLine::query()
                ->where('accounting_journal_entry_id', (int) $expense->accounting_journal_entry_id)
                ->pluck('accounting_chart_account_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $this->deleteJournalForExpense($expense, $oldHint);
        $expense->refresh();

        $entry = $this->postJournalForExpense($expense, $user);
        $expense->update(['accounting_journal_entry_id' => $entry->id]);

        return $entry;
    }
}
