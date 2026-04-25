<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalLine;

class AccountingChartBalanceService
{
    /**
     * @param  array<int>  $accountIds
     */
    public function syncAccountsAndAncestors(array $accountIds): void
    {
        $ids = collect($accountIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $accounts = AccountingChartAccount::query()
            ->with('parent')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($accounts as $account) {
            $this->syncAccountBalance($account);
        }
    }

    private function syncAccountBalance(AccountingChartAccount $account): void
    {
        if ($account->isDetail()) {
            $net = (float) AccountingJournalLine::query()
                ->where('accounting_chart_account_id', $account->id)
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
                ->value('net');
            $balance = round($net, 2);
            $account->update([
                'current_balance' => $balance,
                'is_overdrawn' => $balance < 0 && (bool) $account->allow_overdraft,
            ]);
        } else {
            $sumChildren = (float) AccountingChartAccount::query()
                ->where('parent_id', $account->id)
                ->sum('current_balance');
            $balance = round($sumChildren, 2);
            $account->update([
                'current_balance' => $balance,
                'is_overdrawn' => $balance < 0 && (bool) $account->allow_overdraft,
            ]);
        }

        $this->rollUpToParents($account);
    }

    private function rollUpToParents(AccountingChartAccount $account): void
    {
        $seen = [];
        $parent = $account->parent;

        while ($parent && ! in_array((int) $parent->id, $seen, true)) {
            $seen[] = (int) $parent->id;
            $sumChildren = (float) AccountingChartAccount::query()
                ->where('parent_id', $parent->id)
                ->sum('current_balance');
            $balance = round($sumChildren, 2);
            $parent->update([
                'current_balance' => $balance,
                'is_overdrawn' => $balance < 0 && (bool) $parent->allow_overdraft,
            ]);
            $parent = $parent->parent;
        }
    }
}
