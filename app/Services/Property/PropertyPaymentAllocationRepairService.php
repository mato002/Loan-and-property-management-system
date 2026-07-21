<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmAccountingAuditLog;
use App\Models\PmTenant;
use Illuminate\Support\Facades\DB;

class PropertyPaymentAllocationRepairService
{
    public function __construct(
        private readonly PropertyPaymentSettlementService $settlement,
    ) {}

    /**
     * Rebuild payment→invoice allocations oldest-due-first for each tenant.
     *
     * @return array{invoices_synced: int, allocations_moved: int, tenants: int}
     */
    public function repair(?int $tenantId = null, int $limit = 500): array
    {
        $invoicesSynced = 0;
        $allocationsMoved = 0;
        $tenantsTouched = 0;

        $tenantIds = PmTenant::query()
            ->when($tenantId, fn ($q) => $q->whereKey($tenantId))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($tenantIds as $tid) {
            $result = $this->repairTenant((int) $tid);
            if ($result['invoices_synced'] > 0 || $result['allocations_moved'] > 0) {
                $tenantsTouched++;
            }
            $invoicesSynced += $result['invoices_synced'];
            $allocationsMoved += $result['allocations_moved'];
        }

        return [
            'invoices_synced' => $invoicesSynced,
            'allocations_moved' => $allocationsMoved,
            'tenants' => $tenantsTouched,
        ];
    }

    /**
     * @return array{invoices_synced: int, allocations_moved: int}
     */
    public function repairTenant(int $tenantId): array
    {
        return DB::transaction(function () use ($tenantId) {
            $payments = PmPayment::query()
                ->where('pm_tenant_id', $tenantId)
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->orderBy('paid_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                return ['invoices_synced' => 0, 'allocations_moved' => 0];
            }

            $beforeMap = $this->paymentAllocationMap($tenantId);

            $invoices = PmInvoice::query()
                ->where('pm_tenant_id', $tenantId)
                ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                ->orderBy('due_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $beforePaid = $invoices->mapWithKeys(
                fn (PmInvoice $i) => [(int) $i->id => round((float) $i->amount_paid, 2)]
            );

            PmPaymentAllocation::query()
                ->whereIn('pm_payment_id', $payments->pluck('id'))
                ->where(function ($q) {
                    $q->where('is_reversed', false)->orWhereNull('is_reversed');
                })
                ->delete();

            foreach ($invoices as $invoice) {
                $invoice->syncAmountPaidFromAllocations();
            }

            foreach ($payments as $payment) {
                $scope = (string) data_get($payment->meta, 'bill_scope', 'all');
                $invoiceType = match (strtolower(trim($scope))) {
                    'rent' => PmInvoice::TYPE_RENT,
                    'water' => PmInvoice::TYPE_WATER,
                    default => null,
                };

                $this->settlement->allocatePaymentToOpenInvoices($payment, $invoiceType);
            }

            $invoicesSynced = 0;
            foreach ($invoices as $invoice) {
                $invoice->refresh();
                $invoice->syncAmountPaidFromAllocations();
                $prior = (float) ($beforePaid[(int) $invoice->id] ?? 0);
                if (abs($prior - (float) $invoice->amount_paid) > 0.009) {
                    $invoicesSynced++;
                }
            }

            $afterMap = $this->paymentAllocationMap($tenantId);
            $allocationsMoved = $this->countPaymentsRemapped($beforeMap, $afterMap);

            if ($allocationsMoved > 0 || $invoicesSynced > 0) {
                PmAccountingAuditLog::record(
                    PmAccountingAuditLog::ACTION_ALLOCATION_REPAIR_REVIEW,
                    'pm_tenant',
                    $tenantId,
                    [
                        'pm_tenant_id' => $tenantId,
                        'summary' => 'Allocation repair requires accounting reconciliation review (tenant #'.$tenantId.')',
                        'payload' => [
                            'tenant_id' => $tenantId,
                            'invoices_synced' => $invoicesSynced,
                            'allocations_moved' => $allocationsMoved,
                        ],
                    ]
                );
            }

            return [
                'invoices_synced' => $invoicesSynced,
                'allocations_moved' => $allocationsMoved,
            ];
        });
    }

    /**
     * @return array<int, list<array{invoice_id: int, amount: float}>>
     */
    private function paymentAllocationMap(int $tenantId): array
    {
        $map = [];

        PmPaymentAllocation::query()
            ->whereHas('payment', fn ($q) => $q
                ->where('pm_tenant_id', $tenantId)
                ->where('status', PmPayment::STATUS_COMPLETED))
            ->where(function ($q) {
                $q->where('is_reversed', false)->orWhereNull('is_reversed');
            })
            ->orderBy('pm_payment_id')
            ->orderBy('pm_invoice_id')
            ->get(['pm_payment_id', 'pm_invoice_id', 'amount'])
            ->each(function (PmPaymentAllocation $row) use (&$map) {
                $paymentId = (int) $row->pm_payment_id;
                $map[$paymentId] ??= [];
                $map[$paymentId][] = [
                    'invoice_id' => (int) $row->pm_invoice_id,
                    'amount' => round((float) $row->amount, 2),
                ];
            });

        return $map;
    }

    /**
     * @param  array<int, list<array{invoice_id: int, amount: float}>>  $before
     * @param  array<int, list<array{invoice_id: int, amount: float}>>  $after
     */
    private function countPaymentsRemapped(array $before, array $after): int
    {
        $changed = 0;
        $paymentIds = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($paymentIds as $paymentId) {
            $fingerprint = static fn (array $rows) => collect($rows)
                ->map(fn (array $r) => $r['invoice_id'].':'.number_format($r['amount'], 2, '.', ''))
                ->sort()
                ->implode('|');

            if ($fingerprint($before[$paymentId] ?? []) !== $fingerprint($after[$paymentId] ?? [])) {
                $changed++;
            }
        }

        return $changed;
    }
}
