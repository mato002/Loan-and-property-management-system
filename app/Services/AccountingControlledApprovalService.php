<?php

namespace App\Services;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalApprovalQueue;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccountingControlledApprovalService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, AccountingChartAccount>  $lineAccounts
     * @return array{
     *   requires_approval: bool,
     *   blocked: bool,
     *   blocked_message: ?string,
     *   reason_code: ?string,
     *   reason_detail: string,
     *   approval_type: string,
     *   required_role: ?string,
     *   required_approver_ids: list<int>
     * }
     */
    public function evaluate(Collection $lines, Collection $lineAccounts): array
    {
        $requiresApproval = false;
        $blocked = false;
        $blockedMessage = null;
        $reasonCode = null;
        $reasons = [];
        $approvalType = 'any';
        $requiredRole = null;
        $requiredApproverIds = [];

        foreach ($lines as $line) {
            $accountId = (int) ($line['accounting_chart_account_id'] ?? 0);
            /** @var AccountingChartAccount|null $account */
            $account = $lineAccounts->get($accountId);
            if (! $account) {
                continue;
            }
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            $delta = $debit - $credit;
            $projected = round((float) ($account->current_balance ?? 0) + $delta, 2);

            $floorApplies = (bool) $account->floor_enabled || ((bool) $account->is_cash_account && (float) $account->min_balance_floor > 0);
            if ($floorApplies && $projected < (float) $account->min_balance_floor) {
                if ((string) $account->floor_action === 'require_approval') {
                    $requiresApproval = true;
                    $reasonCode = $reasonCode ?? 'below_floor';
                    $reasons[] = "Below floor: {$account->code} would drop below ".number_format((float) $account->min_balance_floor, 2).'.';
                } else {
                    $blocked = true;
                    $blockedMessage = "Below floor transaction is blocked for {$account->code} - {$account->name}.";
                }
            }

            if (! (bool) $account->is_controlled_account) {
                continue;
            }

            if (! (bool) $account->control_requires_approval) {
                continue;
            }

            $direction = (string) ($account->control_applies_to ?: 'both');
            $directionMatched = $direction === 'both'
                || ($direction === 'debit' && $debit > 0)
                || ($direction === 'credit' && $credit > 0);

            $thresholdMatched = ! (bool) $account->control_threshold_enabled
                || ((float) ($account->control_threshold_amount ?? 0) <= 0)
                || max($debit, $credit) >= (float) ($account->control_threshold_amount ?? 0);

            $isAlways = (bool) $account->control_always_require_approval;
            if ($isAlways || ($directionMatched && $thresholdMatched)) {
                $requiresApproval = true;
                $reasonCode = $reasonCode ?? ($isAlways ? 'controlled_account' : 'threshold');
                $reasons[] = "Controlled account hit: {$account->code} - {$account->name}.";

                $approvalType = (string) ($account->control_approval_type ?: 'any');
                $requiredRole = $approvalType === 'role' ? (string) ($account->control_approval_role ?: '') : null;
                $requiredApproverIds = array_values(array_unique(array_merge(
                    $requiredApproverIds,
                    $account->controlledApprovers()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
                )));
            }
        }

        return [
            'requires_approval' => $requiresApproval && ! $blocked,
            'blocked' => $blocked,
            'blocked_message' => $blockedMessage,
            'reason_code' => $requiresApproval ? ($reasonCode ?? 'controlled_account') : null,
            'reason_detail' => implode(' ', $reasons),
            'approval_type' => $approvalType,
            'required_role' => $requiredRole,
            'required_approver_ids' => $requiredApproverIds,
        ];
    }

    public function userCanApprove(AccountingJournalApprovalQueue $queue, User $user): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $type = (string) ($queue->required_approval_type ?: 'any');
        if ($type === 'role') {
            $role = strtolower(trim((string) ($queue->required_role ?? '')));
            if ($role === '') {
                return false;
            }

            return strtolower(trim((string) $user->effectiveLoanRole())) === $role;
        }

        $required = collect($queue->required_approver_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($required->isEmpty()) {
            return false;
        }

        return $required->contains((int) $user->id);
    }

    public function applyApprovalAndPost(AccountingJournalApprovalQueue $queue, User $approver): bool
    {
        $progress = collect($queue->approval_progress ?? [])->values();
        if ($progress->pluck('user_id')->contains((int) $approver->id)) {
            return false;
        }

        $progress->push([
            'user_id' => (int) $approver->id,
            'at' => now()->toDateTimeString(),
        ]);

        $type = (string) ($queue->required_approval_type ?: 'any');
        $isApproved = $type !== 'all';
        if ($type === 'all') {
            $required = collect($queue->required_approver_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            $approvedIds = $progress->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
            $isApproved = $required->diff($approvedIds)->isEmpty();
        }

        $queue->update([
            'approval_progress' => $progress->all(),
            'status' => $isApproved ? AccountingJournalApprovalQueue::STATUS_APPROVED : AccountingJournalApprovalQueue::STATUS_PENDING,
            'approved_by' => $isApproved ? $approver->id : null,
            'approved_at' => $isApproved ? now() : null,
        ]);

        if (! $isApproved) {
            return false;
        }

        $entry = $queue->journalEntry()->first();
        if (! $entry) {
            throw ValidationException::withMessages(['journal' => 'Linked journal entry was not found.']);
        }

        $entry->update([
            'status' => AccountingJournalEntry::STATUS_POSTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $lineAccountIds = AccountingJournalLine::query()
            ->where('accounting_journal_entry_id', $entry->id)
            ->pluck('accounting_chart_account_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        app(AccountingChartBalanceService::class)->syncAccountsAndAncestors($lineAccountIds);

        return true;
    }
}
