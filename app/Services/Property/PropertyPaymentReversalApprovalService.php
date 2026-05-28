<?php

namespace App\Services\Property;

use App\Models\PmPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PropertyPaymentReversalApprovalService
{
    public function request(PmPayment $payment, int $makerUserId, string $reason, ?int $utilityOverrideRequestId = null): PmPayment
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED) {
            throw new RuntimeException('Only completed payments can be submitted for reversal.');
        }
        if ($payment->reversal_status === PmPayment::REVERSAL_STATUS_REVERSED) {
            throw new RuntimeException('Payment is already reversed.');
        }

        app(UtilityPeriodGuardService::class)->assertPaymentReversalMutable(
            $payment,
            User::query()->find($makerUserId),
            $utilityOverrideRequestId,
        );

        return DB::transaction(function () use ($payment, $makerUserId, $reason, $utilityOverrideRequestId) {
            $payment->refresh();
            $meta = is_array($payment->meta) ? $payment->meta : [];
            if ($utilityOverrideRequestId) {
                $meta['utility_override_request_id'] = $utilityOverrideRequestId;
            }
            $payment->meta = $meta;
            $payment->reversal_status = PmPayment::REVERSAL_STATUS_PENDING;
            $payment->reversal_reason = $reason;
            $payment->reversal_requested_by = $makerUserId;
            $payment->reversal_requested_at = now();
            $payment->save();

            return $payment->fresh();
        });
    }

    public function approve(PmPayment $payment, int $checkerUserId, ?string $reason = null): PmPayment
    {
        if ($payment->reversal_status !== PmPayment::REVERSAL_STATUS_PENDING) {
            throw new RuntimeException('Payment reversal is not pending approval.');
        }
        if ((int) $payment->reversal_requested_by === $checkerUserId) {
            throw new RuntimeException('Maker/checker violation: requester cannot approve.');
        }

        return DB::transaction(function () use ($payment, $checkerUserId, $reason) {
            $payment->refresh();
            if ($payment->reversal_status !== PmPayment::REVERSAL_STATUS_PENDING) {
                throw new RuntimeException('Payment reversal is no longer pending approval.');
            }
            if ((int) $payment->reversal_requested_by === $checkerUserId) {
                throw new RuntimeException('Maker/checker violation: requester cannot approve.');
            }

            app(PropertyTransactionReversalService::class)
                ->reversePayment($payment, $checkerUserId, $reason ?: $payment->reversal_reason, (int) ($payment->meta['utility_override_request_id'] ?? 0) ?: null);

            $payment->refresh();
            $payment->reversal_status = PmPayment::REVERSAL_STATUS_REVERSED;
            $payment->reversal_approved_by = $checkerUserId;
            $payment->reversal_approved_at = now();
            $payment->save();

            return $payment->fresh();
        });
    }
}

