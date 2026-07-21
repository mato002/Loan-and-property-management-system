<?php

namespace App\Services\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceFirebreakService
{
    public const CARRY_FORWARD_PREFIX = '[Lease Opening Arrears]';

    /** @var bool Skip amount_paid audit when syncing from allocations. */
    public static bool $skipAmountPaidAudit = false;

    public function isCarryForwardInvoiceProtected(PmInvoice $invoice): bool
    {
        if ((float) $invoice->amount_paid > 0) {
            return true;
        }

        return $invoice->allocations()
            ->where(function (Builder $q) {
                $q->where('is_reversed', false)->orWhereNull('is_reversed');
            })
            ->exists();
    }

    /**
     * @return array{preserved: Collection<int, PmInvoice>, deleted: int, skipped: array<int, array<string, mixed>>}
     */
    public function pruneUnprotectedCarryForwardInvoices(PmLease $lease): array
    {
        $existing = PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', self::CARRY_FORWARD_PREFIX.'%')
            ->get();

        $preserved = collect();
        $skipped = [];
        $deleted = 0;

        foreach ($existing as $invoice) {
            if ($this->isCarryForwardInvoiceProtected($invoice)) {
                $preserved->push($invoice);
                $skipped[] = [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'amount' => round((float) $invoice->amount, 2),
                    'amount_paid' => round((float) $invoice->amount_paid, 2),
                ];

                PmFinanceAuditLog::record(
                    PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION_SKIPPED,
                    'pm_invoice',
                    (int) $invoice->id,
                    [
                        'pm_lease_id' => (int) $lease->id,
                        'pm_tenant_id' => (int) $lease->pm_tenant_id,
                        'pm_invoice_id' => (int) $invoice->id,
                        'summary' => 'Preserved paid carry-forward invoice '.$invoice->invoice_no,
                        'payload' => [
                            'invoice_no' => (string) $invoice->invoice_no,
                            'amount' => round((float) $invoice->amount, 2),
                            'amount_paid' => round((float) $invoice->amount_paid, 2),
                        ],
                    ]
                );

                continue;
            }

            PmFinanceAuditLog::record(
                PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION,
                'pm_invoice',
                (int) $invoice->id,
                [
                    'pm_lease_id' => (int) $lease->id,
                    'pm_tenant_id' => (int) $lease->pm_tenant_id,
                    'pm_invoice_id' => (int) $invoice->id,
                    'summary' => 'Deleted unpaid carry-forward invoice '.$invoice->invoice_no.' before recreation',
                    'payload' => [
                        'invoice_no' => (string) $invoice->invoice_no,
                        'amount' => round((float) $invoice->amount, 2),
                    ],
                ]
            );

            app(CarryForwardAccountingService::class)->reverseBeforeInvoiceRemoval(
                $invoice,
                Auth::user(),
                'Unpaid carry-forward invoice removed before recreation'
            );

            $invoice->delete();
            $deleted++;
        }

        return [
            'preserved' => $preserved,
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function carryForwardWarnings(PmLease $lease): array
    {
        $warnings = [];

        $jsonTotal = round(collect($lease->opening_arrears ?? [])
            ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
            ->sum(fn ($row) => (float) ($row['amount'] ?? 0)), 2);

        $invoicedTotal = round((float) PmInvoice::query()
            ->withoutGlobalScopes()
            ->liveBalances()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', self::CARRY_FORWARD_PREFIX.'%')
            ->sum('amount'), 2);

        if ($jsonTotal > 0 && abs($jsonTotal - $invoicedTotal) > 0.01) {
            $warnings[] = [
                'type' => PmFinanceAuditLog::ACTION_CARRY_FORWARD_JSON_MISMATCH,
                'lease_id' => (int) $lease->id,
                'tenant_id' => (int) $lease->pm_tenant_id,
                'json_total' => $jsonTotal,
                'invoiced_total' => $invoicedTotal,
                'delta' => round($jsonTotal - $invoicedTotal, 2),
                'message' => sprintf(
                    'Lease #%d carry-forward JSON (KES %s) differs from invoiced carry-forward (KES %s).',
                    $lease->id,
                    number_format($jsonTotal, 2),
                    number_format($invoicedTotal, 2),
                ),
            ];
        }

        $tenant = PmTenant::query()->find($lease->pm_tenant_id);
        if ($tenant && (float) ($tenant->opening_arrears_amount ?? 0) > 0 && $invoicedTotal > 0) {
            $warnings[] = [
                'type' => PmFinanceAuditLog::ACTION_TENANT_OPENING_ARREARS_DUPLICATE,
                'lease_id' => (int) $lease->id,
                'tenant_id' => (int) $tenant->id,
                'tenant_opening_arrears' => round((float) $tenant->opening_arrears_amount, 2),
                'lease_invoiced_carry_forward' => $invoicedTotal,
                'message' => sprintf(
                    'Tenant #%d has opening arrears (KES %s) while lease #%d already has invoiced carry-forward (KES %s).',
                    $tenant->id,
                    number_format((float) $tenant->opening_arrears_amount, 2),
                    $lease->id,
                    number_format($invoicedTotal, 2),
                ),
            ];
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $warning
     */
    public function logCarryForwardWarning(array $warning): void
    {
        PmFinanceAuditLog::record(
            (string) ($warning['type'] ?? PmFinanceAuditLog::ACTION_CARRY_FORWARD_JSON_MISMATCH),
            'pm_lease',
            (int) ($warning['lease_id'] ?? 0),
            [
                'pm_lease_id' => (int) ($warning['lease_id'] ?? 0),
                'pm_tenant_id' => (int) ($warning['tenant_id'] ?? 0),
                'summary' => (string) ($warning['message'] ?? 'Carry-forward warning'),
                'payload' => $warning,
            ]
        );
    }

    /**
     * Remaining JSON row amount after subtracting preserved invoice totals for the same key.
     *
     * @param  Collection<int, PmInvoice>  $preserved
     */
    public function remainingCarryForwardAmount(array $row, Collection $preserved): float
    {
        $key = $this->carryForwardRowKey($row);
        $jsonAmount = round((float) ($row['amount'] ?? 0), 2);
        if ($jsonAmount <= 0) {
            return 0.0;
        }

        $preservedAmount = round($preserved
            ->filter(fn (PmInvoice $invoice) => $this->invoiceCarryForwardRowKey($invoice) === $key)
            ->sum(fn (PmInvoice $invoice) => (float) $invoice->amount), 2);

        return round(max(0, $jsonAmount - $preservedAmount), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function carryForwardRowKey(array $row): string
    {
        $chargeType = mb_strtolower(trim((string) ($row['charge_type'] ?? 'other')));
        $specific = mb_strtolower(trim((string) ($row['specific_charge'] ?? '')));
        $period = trim((string) ($row['period'] ?? ''));

        return "{$chargeType}|{$specific}|{$period}";
    }

    public function invoiceCarryForwardRowKey(PmInvoice $invoice): string
    {
        $parts = array_map('trim', explode('|', str_replace(' | ', '|', (string) $invoice->description)));
        $chargeType = mb_strtolower(trim((string) ($parts[1] ?? 'other')));
        $specific = '';
        $period = trim((string) ($invoice->billing_period ?? ''));

        foreach ($parts as $part) {
            if (preg_match('/^Period\s+(\d{4}-\d{2})$/i', $part, $matches) === 1) {
                $period = $matches[1];
            } elseif ($part !== self::CARRY_FORWARD_PREFIX
                && ! str_starts_with($part, 'Note:')
                && mb_strtolower($part) !== $chargeType
                && ! str_starts_with($part, 'Period ')) {
                $specific = mb_strtolower($part);
            }
        }

        if ($chargeType === 'water') {
            $chargeType = PmInvoice::TYPE_WATER;
        }

        return "{$chargeType}|{$specific}|{$period}";
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectAllocationDrift(?int $tenantId = null, int $limit = 500): Collection
    {
        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->liveBalances()
            ->withSum(['allocations as allocated_sum' => function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->where('is_reversed', false)->orWhereNull('is_reversed');
                });
            }], 'amount');

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        return $query
            ->orderByDesc('id')
            ->limit($limit * 5)
            ->get()
            ->filter(function (PmInvoice $invoice) {
                $allocated = round((float) ($invoice->allocated_sum ?? 0), 2);
                $amountPaid = round((float) $invoice->amount_paid, 2);

                return abs($amountPaid - $allocated) > 0.009;
            })
            ->take($limit)
            ->map(function (PmInvoice $invoice) {
                $allocated = round((float) ($invoice->allocated_sum ?? 0), 2);
                $amountPaid = round((float) $invoice->amount_paid, 2);

                return [
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'tenant_id' => (int) $invoice->pm_tenant_id,
                    'lease_id' => (int) ($invoice->pm_lease_id ?? 0),
                    'amount' => round((float) $invoice->amount, 2),
                    'amount_paid' => $amountPaid,
                    'allocated_sum' => $allocated,
                    'drift' => round($amountPaid - $allocated, 2),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectDuplicatedCarryForward(int $limit = 200): Collection
    {
        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->liveBalances()
            ->where('description', 'like', self::CARRY_FORWARD_PREFIX.'%')
            ->whereNotNull('pm_lease_id')
            ->select('pm_lease_id', 'billing_period', DB::raw('COUNT(*) as invoice_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('pm_lease_id', 'billing_period')
            ->having('invoice_count', '>', 1)
            ->orderByDesc('invoice_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'lease_id' => (int) $row->pm_lease_id,
                'billing_period' => (string) ($row->billing_period ?? ''),
                'invoice_count' => (int) $row->invoice_count,
                'total_amount' => round((float) $row->total_amount, 2),
            ]);
    }

    /**
     * Soft-deleted carry-forward invoices that had payments before deletion.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detectRecreatedAfterPayment(int $limit = 200): Collection
    {
        if (! Schema::hasTable('pm_finance_audit_logs')) {
            return collect();
        }

        return PmFinanceAuditLog::query()
            ->where('action', PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION_SKIPPED)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (PmFinanceAuditLog $log) => [
                'occurred_at' => optional($log->occurred_at)->toDateTimeString(),
                'invoice_id' => (int) ($log->pm_invoice_id ?? 0),
                'lease_id' => (int) ($log->pm_lease_id ?? 0),
                'tenant_id' => (int) ($log->pm_tenant_id ?? 0),
                'summary' => (string) ($log->summary ?? ''),
                'amount_paid' => round((float) ($log->payload['amount_paid'] ?? 0), 2),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectStaleOpeningArrears(int $limit = 200): Collection
    {
        if (! Schema::hasColumn('pm_tenants', 'opening_arrears_amount')) {
            return collect();
        }

        return PmTenant::query()
            ->where('opening_arrears_amount', '>', 0)
            ->orderByDesc('opening_arrears_amount')
            ->limit($limit)
            ->get()
            ->filter(function (PmTenant $tenant) {
                $hasLeaseCarryForwardInvoices = PmInvoice::query()
                    ->withoutGlobalScopes()
                    ->liveBalances()
                    ->where('pm_tenant_id', $tenant->id)
                    ->where('description', 'like', self::CARRY_FORWARD_PREFIX.'%')
                    ->exists();

                return ! $hasLeaseCarryForwardInvoices;
            })
            ->map(fn (PmTenant $tenant) => [
                'tenant_id' => (int) $tenant->id,
                'tenant_name' => (string) ($tenant->full_name ?? $tenant->name ?? 'Tenant #'.$tenant->id),
                'opening_arrears_amount' => round((float) $tenant->opening_arrears_amount, 2),
                'as_of' => optional($tenant->opening_arrears_as_of)->toDateString(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectPartialOverdueInvoices(int $limit = 200): Collection
    {
        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('is_past_due', true)
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (PmInvoice $invoice) => [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'tenant_id' => (int) $invoice->pm_tenant_id,
                'due_date' => optional($invoice->due_date)->toDateString(),
                'amount' => round((float) $invoice->amount, 2),
                'amount_paid' => round((float) $invoice->amount_paid, 2),
                'balance' => $invoice->balanceFloat(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectOrphanAllocations(int $limit = 200): Collection
    {
        return PmPaymentAllocation::query()
            ->with(['invoice' => fn ($q) => $q->withoutGlobalScopes()->withTrashed(), 'payment'])
            ->where(function (Builder $q) {
                $q->whereNull('is_reversed')->orWhere('is_reversed', false);
            })
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmPaymentAllocation $allocation) {
                $invoice = $allocation->invoice;
                if (! $invoice || $invoice->trashed()) {
                    return true;
                }

                if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED) {
                    return true;
                }

                $payment = $allocation->payment;

                return $payment && (string) $payment->status === 'failed';
            })
            ->take($limit)
            ->map(fn (PmPaymentAllocation $allocation) => [
                'allocation_id' => (int) $allocation->id,
                'payment_id' => (int) $allocation->pm_payment_id,
                'invoice_id' => (int) ($allocation->pm_invoice_id ?? 0),
                'amount' => round((float) $allocation->amount, 2),
                'invoice_deleted' => $allocation->invoice?->trashed() ?? true,
                'invoice_status' => (string) ($allocation->invoice?->status ?? 'missing'),
                'payment_status' => (string) ($allocation->payment?->status ?? 'missing'),
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsSnapshot(?int $tenantId = null): array
    {
        return [
            'allocation_drift' => $this->detectAllocationDrift($tenantId, 100),
            'duplicated_carry_forward' => $this->detectDuplicatedCarryForward(100),
            'recreated_after_payment' => $this->detectRecreatedAfterPayment(100),
            'stale_opening_arrears' => $this->detectStaleOpeningArrears(100),
            'partial_overdue' => $this->detectPartialOverdueInvoices(100),
            'invoice_state_violations' => app(InvoiceStateIntegrityService::class)->detect(null, 100),
            'orphan_allocations' => $this->detectOrphanAllocations(100),
        ];
    }
}
