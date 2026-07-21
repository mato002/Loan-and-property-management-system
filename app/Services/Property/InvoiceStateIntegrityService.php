<?php

namespace App\Services\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class InvoiceStateIntegrityService
{
    public const VIOLATION_PAID_WITH_BALANCE = 'paid_with_balance';

    public const VIOLATION_SENT_WITH_FULL_PAYMENT = 'sent_with_full_payment';

    public const VIOLATION_PARTIAL_ZERO_PAID = 'partial_zero_paid';

    public const VIOLATION_ALLOCATION_DRIFT = 'allocation_drift';

    public const VIOLATION_PAST_DUE_FLAG = 'past_due_flag_mismatch';

    public const VIOLATION_LEGACY_OVERDUE_STATUS = 'legacy_overdue_status';

    /**
     * @return list<string>
     */
    public function inspect(PmInvoice $invoice): array
    {
        $invoice->refresh();
        $violations = [];

        $amount = round((float) $invoice->amount, 2);
        $paid = round((float) $invoice->amount_paid, 2);
        $allocated = $invoice->allocatedAmount();
        $balance = max(0.0, round($amount - $paid, 2));
        $status = (string) $invoice->status;

        if (abs($allocated - $paid) > 0.009) {
            $violations[] = self::VIOLATION_ALLOCATION_DRIFT;
        }

        if ($status === PmInvoice::STATUS_PAID && $balance > 0.009) {
            $violations[] = self::VIOLATION_PAID_WITH_BALANCE;
        }

        if (in_array($status, [PmInvoice::STATUS_SENT, PmInvoice::STATUS_PARTIAL], true) && $balance <= 0.009 && $amount > 0) {
            $violations[] = self::VIOLATION_SENT_WITH_FULL_PAYMENT;
        }

        if ($status === PmInvoice::STATUS_PARTIAL && $paid <= 0.009) {
            $violations[] = self::VIOLATION_PARTIAL_ZERO_PAID;
        }

        if ($status === PmInvoice::STATUS_OVERDUE) {
            $violations[] = self::VIOLATION_LEGACY_OVERDUE_STATUS;
        }

        $expectedPastDue = $this->expectedPastDue($invoice, $balance);
        if ((bool) $invoice->is_past_due !== $expectedPastDue) {
            $violations[] = self::VIOLATION_PAST_DUE_FLAG;
        }

        return $violations;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detect(?int $tenantId = null, int $limit = 200): Collection
    {
        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->whereNotIn('status', [PmInvoice::STATUS_DRAFT, PmInvoice::STATUS_CANCELLED])
            ->orderBy('id');

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        return $query
            ->limit(max(1, $limit) * 5)
            ->get()
            ->map(function (PmInvoice $invoice) {
                $violations = $this->inspect($invoice);
                if ($violations === []) {
                    return null;
                }

                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'tenant_id' => (int) ($invoice->pm_tenant_id ?? 0),
                    'status' => (string) $invoice->status,
                    'is_past_due' => (bool) $invoice->is_past_due,
                    'amount' => round((float) $invoice->amount, 2),
                    'amount_paid' => round((float) $invoice->amount_paid, 2),
                    'balance' => $invoice->balanceFloat(),
                    'violations' => $violations,
                ];
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    public function assertHealthy(PmInvoice $invoice, bool $autoRepair = true): void
    {
        $violations = $this->inspect($invoice);
        if ($violations === []) {
            return;
        }

        if ($autoRepair) {
            $invoice->syncAmountPaidFromAllocations();
            $violations = $this->inspect($invoice->fresh() ?? $invoice);
        }

        if ($violations === []) {
            return;
        }

        Log::warning('Invoice state integrity violation', [
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'violations' => $violations,
        ]);

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_INVOICE_STATE_VIOLATION,
            'pm_invoice',
            (int) $invoice->id,
            [
                'pm_invoice_id' => (int) $invoice->id,
                'pm_tenant_id' => (int) ($invoice->pm_tenant_id ?? 0),
                'summary' => sprintf(
                    'Invoice state integrity violation on %s: %s',
                    (string) ($invoice->invoice_no ?? '#'.$invoice->id),
                    implode(', ', $violations),
                ),
                'payload' => [
                    'violations' => $violations,
                    'status' => (string) $invoice->status,
                    'is_past_due' => (bool) $invoice->is_past_due,
                    'amount' => round((float) $invoice->amount, 2),
                    'amount_paid' => round((float) $invoice->amount_paid, 2),
                    'balance' => $invoice->balanceFloat(),
                ],
            ]
        );
    }

    public function expectedPastDue(PmInvoice $invoice, ?float $balance = null): bool
    {
        $status = (string) $invoice->status;
        if (in_array($status, [PmInvoice::STATUS_DRAFT, PmInvoice::STATUS_CANCELLED, PmInvoice::STATUS_PAID], true)) {
            return false;
        }

        $balance ??= $invoice->balanceFloat();
        if ($balance <= 0.009) {
            return false;
        }

        if (! $invoice->due_date) {
            return false;
        }

        return $invoice->due_date->copy()->endOfDay()->isPast();
    }

    public function pastDueOpenQuery(?Builder $base = null): Builder
    {
        $query = $base ?? PmInvoice::query();

        return $query
            ->billableAr()
            ->where('is_past_due', true)
            ->where('balance_due', '>', 0);
    }

    public function countPastDueInvoices(): int
    {
        return (int) $this->pastDueOpenQuery()->count();
    }

    public function countPastDueTenants(): int
    {
        return (int) $this->pastDueOpenQuery()
            ->whereNotNull('pm_tenant_id')
            ->distinct()
            ->count('pm_tenant_id');
    }
}
