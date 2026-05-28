<?php

namespace App\Services\Property;

use App\Exceptions\Property\UtilityPeriodClosedException;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmWaterReading;
use App\Models\User;
use App\Models\UtilityAuditLog;
use App\Models\UtilityBillingPeriod;
use App\Models\UtilityPeriodOverrideRequest;
use Illuminate\Support\Facades\Auth;

class UtilityPeriodGuardService
{
    public const ACTION_EDIT_READING = 'edit_reading';

    public const ACTION_DELETE_READING = 'delete_reading';

    public const ACTION_GENERATE_INVOICE = 'generate_invoice';

    public const ACTION_REVERSE_INVOICE = 'reverse_invoice';

    public const ACTION_EDIT_INVOICE = 'edit_invoice';

    public const ACTION_REVERSE_PENALTY = 'reverse_penalty';

    public const ACTION_APPLY_PENALTY = 'apply_penalty';

    public const ACTION_REVERSE_ALLOCATION = 'reverse_allocation';

    public function isClosed(string $billingMonth, ?int $agentUserId = null): bool
    {
        $agentUserId = $agentUserId ?? (int) Auth::id();
        if ($agentUserId <= 0 || ! preg_match('/^\d{4}-\d{2}$/', $billingMonth)) {
            return false;
        }

        return UtilityBillingPeriod::query()
            ->where('agent_user_id', $agentUserId)
            ->where('billing_month', $billingMonth)
            ->where('status', UtilityBillingPeriod::STATUS_CLOSED)
            ->exists();
    }

    /**
     * @throws UtilityPeriodClosedException
     */
    public function assertMutable(
        string $billingMonth,
        string $action,
        ?User $actor = null,
        ?int $overrideRequestId = null,
        ?string $entityType = null,
        ?int $entityId = null,
    ): void {
        if (! $this->isClosed($billingMonth, $actor?->id ? (int) $actor->id : null)) {
            return;
        }

        if ($overrideRequestId) {
            $this->consumeApprovedOverride(
                $overrideRequestId,
                $billingMonth,
                $action,
                $entityType,
                $entityId,
                $actor
            );

            return;
        }

        throw new UtilityPeriodClosedException(
            $billingMonth,
            $action,
            "Utility billing period {$billingMonth} is closed. A supervisor override is required for: ".str_replace('_', ' ', $action).'.'
        );
    }

    /**
     * @throws UtilityPeriodClosedException
     */
    public function assertReadingMutable(PmWaterReading $reading, string $action, ?User $actor = null, ?int $overrideRequestId = null): void
    {
        $this->assertMutable(
            (string) $reading->billing_month,
            $action,
            $actor,
            $overrideRequestId,
            'pm_water_reading',
            (int) $reading->id,
        );
    }

    /**
     * @throws UtilityPeriodClosedException
     */
    public function assertInvoiceMutable(PmInvoice $invoice, string $action, ?User $actor = null, ?int $overrideRequestId = null): void
    {
        if (! $this->isUtilityInvoice($invoice)) {
            return;
        }

        $month = $this->billingMonthForInvoice($invoice);
        if ($month === null) {
            return;
        }

        $this->assertMutable(
            $month,
            $action,
            $actor,
            $overrideRequestId,
            'pm_invoice',
            (int) $invoice->id,
        );
    }

    /**
     * @throws UtilityPeriodClosedException
     */
    public function assertPaymentReversalMutable(PmPayment $payment, ?User $actor = null, ?int $overrideRequestId = null): void
    {
        $payment->loadMissing('allocations.invoice');

        foreach ($payment->allocations as $allocation) {
            if ($allocation->is_reversed) {
                continue;
            }
            $invoice = $allocation->invoice;
            if (! $invoice || ! $this->isUtilityInvoice($invoice)) {
                continue;
            }
            $month = $this->billingMonthForInvoice($invoice);
            if ($month === null) {
                continue;
            }
            if ($this->isClosed($month, $actor?->id ? (int) $actor->id : null)) {
                $this->assertMutable(
                    $month,
                    self::ACTION_REVERSE_ALLOCATION,
                    $actor,
                    $overrideRequestId,
                    'pm_payment_allocation',
                    (int) $allocation->id,
                );
            }
        }
    }

    public function billingMonthForInvoice(PmInvoice $invoice): ?string
    {
        if ($invoice->billing_period && preg_match('/^\d{4}-\d{2}$/', (string) $invoice->billing_period)) {
            return (string) $invoice->billing_period;
        }

        return $invoice->issue_date?->format('Y-m');
    }

    public function isUtilityInvoice(PmInvoice $invoice): bool
    {
        return in_array((string) $invoice->invoice_type, [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED], true);
    }

    public function ensurePeriod(string $billingMonth, ?int $agentUserId = null): UtilityBillingPeriod
    {
        $agentUserId = $agentUserId ?? (int) Auth::id();

        return UtilityBillingPeriod::query()->firstOrCreate(
            [
                'agent_user_id' => $agentUserId,
                'billing_month' => $billingMonth,
            ],
            ['status' => UtilityBillingPeriod::STATUS_OPEN]
        );
    }

    /**
     * @throws UtilityPeriodClosedException
     */
    private function consumeApprovedOverride(
        int $overrideRequestId,
        string $billingMonth,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?User $actor,
    ): void {
        $override = UtilityPeriodOverrideRequest::query()->find($overrideRequestId);
        if (! $override || ! $override->isApproved()) {
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override request is not approved.');
        }

        if ((string) $override->billing_month !== $billingMonth) {
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override request billing month mismatch.');
        }

        if ((string) $override->action_type !== $action) {
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override request action type mismatch.');
        }

        if ($override->approved_at && $override->approved_at->lt(now()->subHours(48))) {
            $override->update(['status' => UtilityPeriodOverrideRequest::STATUS_EXPIRED]);
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override approval has expired (48h limit). Request a new override.');
        }

        if ($entityType && $override->entity_type && (string) $override->entity_type !== $entityType) {
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override entity type mismatch.');
        }

        if ($entityId && $override->entity_id && (int) $override->entity_id !== $entityId) {
            throw new UtilityPeriodClosedException($billingMonth, $action, 'Override entity mismatch.');
        }

        $override->update([
            'status' => UtilityPeriodOverrideRequest::STATUS_EXECUTED,
            'executed_at' => now(),
            'executed_by' => $actor?->id,
        ]);

        UtilityAuditLog::record('period_override_executed', 'utility_period_override_request', (int) $override->id, [
            'billing_month' => $billingMonth,
            'actor_user_id' => $actor?->id,
            'payload' => [
                'action_type' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            'notes' => 'Supervisor override consumed',
        ]);
    }
}
