<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use Illuminate\Validation\ValidationException;

class AccountingOverdraftGuardService
{
    /**
     * @param  array<int, float>  $accountDeltas key=account_id, value=net_delta
     */
    public function assertCanApplyDeltas(array $accountDeltas): void
    {
        $ids = collect($accountDeltas)->keys()->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $accounts = AccountingChartAccount::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($accountDeltas as $accountId => $delta) {
            $account = $accounts->get((int) $accountId);
            if (! $account) {
                continue;
            }

            $projected = round((float) ($account->current_balance ?? 0) + (float) $delta, 2);
            if ($projected >= 0) {
                continue;
            }

            if (! (bool) $account->allow_overdraft) {
                throw ValidationException::withMessages([
                    'lines' => "Insufficient funds in source account ({$account->code} - {$account->name}).",
                ]);
            }

            $limit = $account->overdraft_limit;
            if ($limit !== null && $projected < (0 - (float) $limit)) {
                throw ValidationException::withMessages([
                    'lines' => "Overdraft limit exceeded for {$account->code} - {$account->name}.",
                ]);
            }
        }
    }
}
