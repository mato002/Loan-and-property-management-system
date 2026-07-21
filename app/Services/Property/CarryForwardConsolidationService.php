<?php

namespace App\Services\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmLeaseCarryForwardLine;
use App\Models\PmTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CarryForwardConsolidationService
{
    public function __construct(
        private readonly FinanceFirebreakService $firebreak,
        private readonly CarryForwardAccountingService $carryForwardAccounting,
        private readonly TenantCreditService $tenantCreditService,
    ) {}

    /**
     * Lifecycle entry point: capture → delta invoice → refresh statuses → duplicate guards.
     *
     * @return array<string, int|bool>
     */
    public function syncLease(PmLease $lease): array
    {
        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            return ['skipped' => true];
        }

        $lease->loadMissing(['units.property']);
        if ($lease->units->isEmpty()) {
            return ['skipped' => true];
        }

        return DB::transaction(function () use ($lease) {
            foreach ($this->firebreak->carryForwardWarnings($lease) as $warning) {
                $this->firebreak->logCarryForwardWarning($warning);
            }

            $this->supersedeOldLeaseLinesOnRenewal($lease);
            $captured = $this->captureLinesFromLeaseJson($lease);
            $created = $this->deltaSyncInvoices($lease);
            $this->refreshLineStatuses($lease);
            $this->supersedeTenantOpeningArrearsWhenInvoiced((int) $lease->pm_tenant_id);

            return [
                'captured' => $captured,
                'invoices_created' => $created,
            ];
        });
    }

    public function tenantHasInvoicedCarryForward(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        if (Schema::hasTable('pm_lease_carry_forward_lines')) {
            $hasLine = PmLeaseCarryForwardLine::query()
                ->where('pm_tenant_id', $tenantId)
                ->whereIn('carry_forward_status', [
                    PmLeaseCarryForwardLine::STATUS_INVOICED,
                    PmLeaseCarryForwardLine::STATUS_SETTLED,
                    PmLeaseCarryForwardLine::STATUS_RETIRED,
                ])
                ->exists();
            if ($hasLine) {
                return true;
            }
        }

        return PmInvoice::query()
            ->withoutGlobalScopes()
            ->liveBalances()
            ->where('pm_tenant_id', $tenantId)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->exists();
    }

    public function tenantOpeningArrearsInDue(PmTenant $tenant): float
    {
        $amount = round((float) ($tenant->opening_arrears_amount ?? 0), 2);
        if ($amount <= 0) {
            return 0.0;
        }

        if ($this->tenantHasInvoicedCarryForward((int) $tenant->id)) {
            return 0.0;
        }

        if (Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            $status = (string) ($tenant->opening_arrears_status ?? 'active');
            if (in_array($status, ['superseded', 'retired'], true)) {
                return 0.0;
            }
        }

        return $amount;
    }

    public function leaseJsonUninvoicedInDue(PmLease $lease): float
    {
        if ($this->tenantHasInvoicedCarryForward((int) $lease->pm_tenant_id)) {
            return 0.0;
        }

        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            return round(collect($lease->opening_arrears ?? [])
                ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
                ->sum(fn ($row) => (float) ($row['amount'] ?? 0)), 2);
        }

        return round((float) PmLeaseCarryForwardLine::query()
            ->where('pm_lease_id', $lease->id)
            ->where('carry_forward_status', PmLeaseCarryForwardLine::STATUS_UNINVOICED)
            ->sum('amount'), 2);
    }

    public function tenantUninvoicedCarryForwardDue(PmTenant $tenant): float
    {
        if ($this->tenantHasInvoicedCarryForward((int) $tenant->id)) {
            return 0.0;
        }

        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            $total = 0.0;
            foreach ($tenant->leases ?? [] as $lease) {
                $total += $this->leaseJsonUninvoicedInDue($lease);
            }

            return round($total, 2);
        }

        return round((float) PmLeaseCarryForwardLine::query()
            ->where('pm_tenant_id', $tenant->id)
            ->where('carry_forward_status', PmLeaseCarryForwardLine::STATUS_UNINVOICED)
            ->whereHas('lease', fn ($q) => $q->where('status', PmLease::STATUS_ACTIVE))
            ->sum('amount'), 2);
    }

    public function markLineSettledForInvoice(PmInvoice $invoice): void
    {
        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            return;
        }

        if (! str_starts_with((string) $invoice->description, FinanceFirebreakService::CARRY_FORWARD_PREFIX)) {
            return;
        }

        if ((string) $invoice->status !== PmInvoice::STATUS_PAID) {
            return;
        }

        $rowKey = $this->firebreak->invoiceCarryForwardRowKey($invoice);
        $line = PmLeaseCarryForwardLine::query()
            ->where('pm_lease_id', $invoice->pm_lease_id)
            ->where('row_key', $rowKey)
            ->first();

        if (! $line) {
            return;
        }

        $linkedIds = collect($line->pm_invoice_ids ?? [])->map(fn ($id) => (int) $id)->all();
        if (! in_array((int) $invoice->id, $linkedIds, true)) {
            $linkedIds[] = (int) $invoice->id;
        }

        $allPaid = PmInvoice::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $linkedIds)
            ->liveBalances()
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->get()
            ->every(fn (PmInvoice $row) => (string) $row->status === PmInvoice::STATUS_PAID);

        if ($allPaid) {
            $line->update([
                'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_SETTLED,
                'pm_invoice_ids' => $linkedIds,
                'settled_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileLease(PmLease $lease): array
    {
        $result = $this->syncLease($lease);
        $issues = $this->detectLeaseIssues($lease);

        return array_merge($result, ['issues' => $issues]);
    }

    /**
     * Remove carry-forward invoices/lines when a lease is deleted.
     * Paid or allocated carry-forward invoices block deletion.
     */
    public function purgeLeaseOnDelete(PmLease $lease): void
    {
        $lease->loadMissing(['units.property']);

        $result = $this->firebreak->pruneUnprotectedCarryForwardInvoices($lease);
        if ($result['preserved']->isNotEmpty()) {
            throw ValidationException::withMessages([
                'lease' => 'This lease has carry-forward invoices with payments. Reverse or reallocate those payments before deleting the lease.',
            ]);
        }

        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            return;
        }

        $lineCount = PmLeaseCarryForwardLine::query()
            ->where('pm_lease_id', $lease->id)
            ->count();

        if ($lineCount === 0 && (int) ($result['deleted'] ?? 0) === 0) {
            return;
        }

        PmLeaseCarryForwardLine::query()
            ->where('pm_lease_id', $lease->id)
            ->delete();

        PmFinanceAuditLog::record(
            PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION,
            'pm_lease',
            (int) $lease->id,
            [
                'pm_lease_id' => (int) $lease->id,
                'pm_tenant_id' => (int) $lease->pm_tenant_id,
                'summary' => 'Removed carry-forward artifacts for deleted lease #'.$lease->id,
                'payload' => [
                    'invoices_deleted' => (int) ($result['deleted'] ?? 0),
                    'lines_deleted' => $lineCount,
                ],
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileTenant(int $tenantId): array
    {
        $leases = PmLease::query()->where('pm_tenant_id', $tenantId)->orderBy('id')->get();
        $summary = ['leases' => 0, 'invoices_created' => 0, 'issues' => []];

        foreach ($leases as $lease) {
            $result = $this->reconcileLease($lease);
            $summary['leases']++;
            $summary['invoices_created'] += (int) ($result['invoices_created'] ?? 0);
            $summary['issues'] = array_merge($summary['issues'], $result['issues'] ?? []);
        }

        $this->supersedeTenantOpeningArrearsWhenInvoiced($tenantId);
        $this->autoApplyTenantCreditToOpenInvoices($tenantId);

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function detectLeaseIssues(PmLease $lease): array
    {
        $issues = [];

        $tenant = PmTenant::query()->find($lease->pm_tenant_id);
        if ($tenant && $this->tenantHasInvoicedCarryForward((int) $tenant->id) && (float) ($tenant->opening_arrears_amount ?? 0) > 0) {
            $status = Schema::hasColumn('pm_tenants', 'opening_arrears_status')
                ? (string) ($tenant->opening_arrears_status ?? 'active')
                : 'active';
            if ($status === 'active') {
                $issues[] = [
                    'type' => 'tenant_opening_duplicate',
                    'lease_id' => (int) $lease->id,
                    'tenant_id' => (int) $tenant->id,
                    'message' => 'Tenant opening arrears still active while carry-forward is invoiced.',
                ];
            }
        }

        if (Schema::hasTable('pm_lease_carry_forward_lines')) {
            $duplicateRows = PmLeaseCarryForwardLine::query()
                ->where('pm_tenant_id', $lease->pm_tenant_id)
                ->where('row_key', '!=', '')
                ->whereIn('carry_forward_status', [
                    PmLeaseCarryForwardLine::STATUS_UNINVOICED,
                    PmLeaseCarryForwardLine::STATUS_INVOICED,
                ])
                ->select('row_key', DB::raw('COUNT(*) as line_count'))
                ->groupBy('row_key')
                ->having('line_count', '>', 1)
                ->get();

            foreach ($duplicateRows as $row) {
                $issues[] = [
                    'type' => 'duplicate_row_key',
                    'lease_id' => (int) $lease->id,
                    'tenant_id' => (int) $lease->pm_tenant_id,
                    'row_key' => (string) $row->row_key,
                    'message' => 'Duplicate carry-forward row key across leases.',
                ];
            }
        }

        return $issues;
    }

    protected function captureLinesFromLeaseJson(PmLease $lease): int
    {
        $captured = 0;
        $rows = collect($lease->opening_arrears ?? [])
            ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
            ->values();

        foreach ($rows as $row) {
            $rowKey = $this->firebreak->carryForwardRowKey($row);
            $amount = round((float) ($row['amount'] ?? 0), 2);

            $line = PmLeaseCarryForwardLine::query()->firstOrNew([
                'pm_lease_id' => $lease->id,
                'row_key' => $rowKey,
            ]);

            if ($line->exists && ! in_array((string) $line->carry_forward_status, [
                PmLeaseCarryForwardLine::STATUS_UNINVOICED,
            ], true)) {
                if (! is_array($line->audit_payload)) {
                    $line->audit_payload = [];
                }
                $line->audit_payload = array_merge($line->audit_payload, [
                    'last_seen_json_amount' => $amount,
                    'last_seen_at' => now()->toIso8601String(),
                ]);
                $line->save();

                continue;
            }

            $line->fill([
                'pm_tenant_id' => (int) $lease->pm_tenant_id,
                'charge_type' => (string) ($row['charge_type'] ?? 'other'),
                'specific_charge' => (string) ($row['specific_charge'] ?? ''),
                'period' => (string) ($row['period'] ?? ''),
                'amount' => $amount,
                'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_UNINVOICED,
                'source' => PmLeaseCarryForwardLine::SOURCE_LEASE_JSON,
                'captured_at' => $line->captured_at ?? now(),
                'audit_payload' => [
                    'lease_opening_arrears' => $row,
                    'lease_note' => $lease->opening_arrears_note,
                ],
            ]);
            $line->save();
            $captured++;
        }

        return $captured;
    }

    protected function deltaSyncInvoices(PmLease $lease): int
    {
        $units = $lease->units;
        $unitCount = $units->count();
        $tenantId = (int) $lease->pm_tenant_id;
        $billingMonth = ($lease->start_date?->format('Y-m')) ?: now()->format('Y-m');
        $created = 0;

        $existingInvoices = PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->get();

        $lines = PmLeaseCarryForwardLine::query()
            ->where('pm_lease_id', $lease->id)
            ->whereIn('carry_forward_status', [
                PmLeaseCarryForwardLine::STATUS_UNINVOICED,
                PmLeaseCarryForwardLine::STATUS_INVOICED,
            ])
            ->get();

        foreach ($lines as $line) {
            $row = [
                'charge_type' => $line->charge_type,
                'specific_charge' => $line->specific_charge,
                'period' => $line->period,
                'amount' => (float) $line->amount,
            ];

            $matching = $existingInvoices->filter(
                fn (PmInvoice $invoice) => $this->firebreak->invoiceCarryForwardRowKey($invoice) === $line->row_key
            );

            $protected = $matching->filter(fn (PmInvoice $invoice) => $this->firebreak->isCarryForwardInvoiceProtected($invoice));
            $invoicedAmount = round((float) $matching->sum('amount'), 2);
            $remaining = round(max(0, (float) $line->amount - $invoicedAmount), 2);

            if ($remaining <= 0.009) {
                $line->update([
                    'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_INVOICED,
                    'invoiced_amount' => $invoicedAmount,
                    'pm_invoice_ids' => $matching->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'invoiced_at' => $line->invoiced_at ?? now(),
                ]);

                continue;
            }

            if ($protected->isNotEmpty()) {
                PmFinanceAuditLog::record(
                    PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION_SKIPPED,
                    'pm_lease_carry_forward_line',
                    (int) $line->id,
                    [
                        'pm_lease_id' => (int) $lease->id,
                        'pm_tenant_id' => $tenantId,
                        'summary' => 'Preserved paid carry-forward invoices while delta invoicing remainder',
                        'payload' => [
                            'row_key' => $line->row_key,
                            'remaining' => $remaining,
                            'protected_invoice_ids' => $protected->pluck('id')->all(),
                        ],
                    ]
                );
            }

            $chargeType = mb_strtolower(trim((string) $line->charge_type));
            $specific = trim((string) $line->specific_charge);
            $period = trim((string) $line->period);

            $baseDate = $lease->opening_arrears_as_of_date
                ? $lease->opening_arrears_as_of_date->toDateString()
                : ($lease->start_date?->toDateString() ?? now()->toDateString());
            if (preg_match('/^\d{4}\-\d{2}$/', $period) === 1) {
                $baseDate = $period.'-01';
            }
            $issueDate = $baseDate;
            $dueDate = $baseDate;
            if ($dueDate >= now()->toDateString()) {
                $dueDate = now()->subDay()->toDateString();
                $issueDate = $dueDate;
            }

            $descParts = array_filter([
                FinanceFirebreakService::CARRY_FORWARD_PREFIX,
                ucfirst($chargeType),
                $specific !== '' ? $specific : null,
                $period !== '' ? "Period {$period}" : null,
                $lease->opening_arrears_note ? 'Note: '.$lease->opening_arrears_note : null,
            ]);

            $amountParts = $this->splitMoneyAcrossParts($remaining, $unitCount);
            $newInvoiceIds = $matching->pluck('id')->map(fn ($id) => (int) $id)->all();

            foreach ($units->values() as $idx => $unit) {
                $partAmount = (float) ($amountParts[$idx] ?? 0.0);
                if ($partAmount <= 0) {
                    continue;
                }

                $agentUserId = optional($unit->property)->agent_user_id ?? Auth::id();
                $origin = [
                    'source' => 'lease_delta_sync',
                    'lease_id' => (int) $lease->id,
                    'line_id' => (int) $line->id,
                    'row_key' => (string) $line->row_key,
                    'delta' => true,
                ];

                $invoicePayload = [
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => (int) $unit->id,
                    'pm_tenant_id' => $tenantId,
                    'agent_user_id' => $agentUserId,
                    'created_by_user_id' => Auth::id(),
                    'invoice_no' => PmInvoice::nextInvoiceNumber(),
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'amount' => $partAmount,
                    'amount_paid' => 0,
                    'status' => PmInvoice::STATUS_SENT,
                    'invoice_type' => $chargeType === PmInvoice::TYPE_WATER ? PmInvoice::TYPE_WATER : PmInvoice::TYPE_MIXED,
                    'billing_period' => $period !== '' ? $period : $billingMonth,
                    'description' => implode(' | ', $descParts),
                ];

                if (Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
                    $invoicePayload['carry_forward_origin'] = $origin;
                }

                $invoice = PmInvoice::query()->create($invoicePayload);

                $invoice->syncAmountPaidFromAllocations();
                $this->carryForwardAccounting->ensureInvoiceIssued($invoice, Auth::user(), $origin);

                $newInvoiceIds[] = (int) $invoice->id;
                $created++;

                PmFinanceAuditLog::record(
                    PmFinanceAuditLog::ACTION_CARRY_FORWARD_RECREATION,
                    'pm_lease',
                    (int) $lease->id,
                    [
                        'pm_lease_id' => (int) $lease->id,
                        'pm_tenant_id' => $tenantId,
                        'pm_invoice_id' => (int) $invoice->id,
                        'summary' => 'Delta carry-forward invoice created for lease #'.$lease->id,
                        'payload' => [
                            'line_id' => (int) $line->id,
                            'row_key' => $line->row_key,
                            'amount' => round($partAmount, 2),
                            'delta' => true,
                        ],
                    ]
                );
            }

            $line->update([
                'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_INVOICED,
                'invoiced_amount' => round($invoicedAmount + $remaining, 2),
                'pm_invoice_ids' => array_values(array_unique($newInvoiceIds)),
                'invoiced_at' => $line->invoiced_at ?? now(),
            ]);
        }

        if ($created > 0 && $tenantId > 0) {
            $this->autoApplyTenantCreditToOpenInvoices($tenantId);
        }

        return $created;
    }

    /**
     * Apply any unallocated tenant credit to open invoices (oldest due first).
     */
    protected function autoApplyTenantCreditToOpenInvoices(int $tenantId): void
    {
        if ($tenantId <= 0 || ! $this->tenantCreditService->isEnabled()) {
            return;
        }

        $this->tenantCreditService->autoApplyForTenant($tenantId, Auth::user());
    }

    protected function refreshLineStatuses(PmLease $lease): void
    {
        $lines = PmLeaseCarryForwardLine::query()->where('pm_lease_id', $lease->id)->get();
        $invoices = PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->liveBalances()
            ->get();

        foreach ($lines as $line) {
            $matching = $invoices->filter(
                fn (PmInvoice $invoice) => $this->firebreak->invoiceCarryForwardRowKey($invoice) === $line->row_key
            );

            if ($matching->isEmpty()) {
                if ((string) $line->carry_forward_status !== PmLeaseCarryForwardLine::STATUS_UNINVOICED) {
                    continue;
                }

                continue;
            }

            $invoiceIds = $matching->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $invoicedAmount = round((float) $matching->sum('amount'), 2);
            $allPaid = $matching->every(fn (PmInvoice $invoice) => (string) $invoice->status === PmInvoice::STATUS_PAID);

            if ($allPaid) {
                $line->update([
                    'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_SETTLED,
                    'invoiced_amount' => $invoicedAmount,
                    'pm_invoice_ids' => $invoiceIds,
                    'invoiced_at' => $line->invoiced_at ?? now(),
                    'settled_at' => $line->settled_at ?? now(),
                ]);

                continue;
            }

            if ($invoicedAmount + 0.009 >= (float) $line->amount) {
                $line->update([
                    'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_INVOICED,
                    'invoiced_amount' => $invoicedAmount,
                    'pm_invoice_ids' => $invoiceIds,
                    'invoiced_at' => $line->invoiced_at ?? now(),
                ]);
            }
        }
    }

    protected function supersedeTenantOpeningArrearsWhenInvoiced(int $tenantId): void
    {
        if ($tenantId <= 0 || ! Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            return;
        }

        if (! $this->tenantHasInvoicedCarryForward($tenantId)) {
            return;
        }

        PmTenant::query()
            ->whereKey($tenantId)
            ->where('opening_arrears_amount', '>', 0)
            ->where('opening_arrears_status', 'active')
            ->update(['opening_arrears_status' => 'superseded']);
    }

    protected function supersedeOldLeaseLinesOnRenewal(PmLease $lease): void
    {
        if (! Schema::hasTable('pm_lease_carry_forward_lines')) {
            return;
        }

        if ((string) $lease->status !== PmLease::STATUS_ACTIVE) {
            return;
        }

        $olderLeases = PmLease::query()
            ->where('pm_tenant_id', $lease->pm_tenant_id)
            ->where('id', '!=', $lease->id)
            ->whereIn('status', [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED])
            ->pluck('id');

        if ($olderLeases->isEmpty()) {
            return;
        }

        PmLeaseCarryForwardLine::query()
            ->whereIn('pm_lease_id', $olderLeases)
            ->where('carry_forward_status', PmLeaseCarryForwardLine::STATUS_UNINVOICED)
            ->update([
                'carry_forward_status' => PmLeaseCarryForwardLine::STATUS_RETIRED,
                'superseded_by_lease_id' => $lease->id,
                'retired_at' => now(),
            ]);
    }

    /**
     * @return array<int, float>
     */
    protected function splitMoneyAcrossParts(float $amount, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $amount = round($amount, 2);
        $base = round(floor(($amount / $parts) * 100) / 100, 2);
        $allocations = array_fill(0, $parts, $base);
        $allocated = round($base * $parts, 2);
        $remainderCents = (int) round(($amount - $allocated) * 100);
        for ($i = 0; $i < $remainderCents; $i++) {
            $allocations[$i % $parts] = round($allocations[$i % $parts] + 0.01, 2);
        }

        return $allocations;
    }
}
