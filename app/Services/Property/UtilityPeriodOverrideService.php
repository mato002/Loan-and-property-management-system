<?php

namespace App\Services\Property;

use App\Models\User;
use App\Models\UtilityAuditLog;
use App\Models\UtilityBillingPeriod;
use App\Models\UtilityPeriodOverrideRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UtilityPeriodOverrideService
{
    public function __construct(
        private readonly UtilityPeriodGuardService $guard,
    ) {}

    public function request(
        string $billingMonth,
        string $actionType,
        string $reason,
        User $requester,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
    ): UtilityPeriodOverrideRequest {
        if (! $this->guard->isClosed($billingMonth, (int) $requester->id)) {
            throw new RuntimeException('Override is only required for closed billing periods.');
        }

        $period = $this->guard->ensurePeriod($billingMonth, (int) $requester->id);
        if ($period->isOpen()) {
            throw new RuntimeException('Period is open — override not required.');
        }

        return DB::transaction(function () use ($period, $billingMonth, $actionType, $reason, $requester, $entityType, $entityId, $payload) {
            $override = UtilityPeriodOverrideRequest::query()->create([
                'utility_billing_period_id' => $period->id,
                'billing_month' => $billingMonth,
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => UtilityPeriodOverrideRequest::STATUS_PENDING,
                'reason' => $reason,
                'requested_by' => $requester->id,
                'requested_at' => now(),
                'payload' => $payload,
            ]);

            UtilityAuditLog::record('period_override_requested', 'utility_period_override_request', (int) $override->id, [
                'billing_month' => $billingMonth,
                'actor_user_id' => $requester->id,
                'payload' => [
                    'action_type' => $actionType,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                'notes' => $reason,
            ]);

            return $override;
        });
    }

    public function approve(UtilityPeriodOverrideRequest $override, User $approver, ?string $notes = null): UtilityPeriodOverrideRequest
    {
        if ((string) $override->status !== UtilityPeriodOverrideRequest::STATUS_PENDING) {
            throw new RuntimeException('Override request is not pending.');
        }

        if ((int) $override->requested_by === (int) $approver->id) {
            throw new RuntimeException('Maker/checker violation: requester cannot approve their own override.');
        }

        return DB::transaction(function () use ($override, $approver, $notes) {
            $override->update([
                'status' => UtilityPeriodOverrideRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $notes,
            ]);

            UtilityAuditLog::record('period_override_approved', 'utility_period_override_request', (int) $override->id, [
                'billing_month' => $override->billing_month,
                'actor_user_id' => $approver->id,
                'notes' => $notes,
            ]);

            return $override->fresh();
        });
    }

    public function reject(UtilityPeriodOverrideRequest $override, User $rejecter, string $reason): UtilityPeriodOverrideRequest
    {
        if ((string) $override->status !== UtilityPeriodOverrideRequest::STATUS_PENDING) {
            throw new RuntimeException('Override request is not pending.');
        }

        return DB::transaction(function () use ($override, $rejecter, $reason) {
            $override->update([
                'status' => UtilityPeriodOverrideRequest::STATUS_REJECTED,
                'rejected_by' => $rejecter->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            UtilityAuditLog::record('period_override_rejected', 'utility_period_override_request', (int) $override->id, [
                'billing_month' => $override->billing_month,
                'actor_user_id' => $rejecter->id,
                'notes' => $reason,
            ]);

            return $override->fresh();
        });
    }
}
