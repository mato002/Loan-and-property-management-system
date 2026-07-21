<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenantCreditBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingFirebreakService
{
    private const ACC_CASH_BANK = '1100';

    private const ACC_AR = '1200';

    private const ACC_UTILITY_AR = '1210';

    private const ACC_LANDLORD_PAYABLE = '2100';

    private const ACC_TENANT_CREDIT_LIABILITY = '2260';

    /** @var array<string, int>|null */
    private ?array $accountIds = null;

    public function accountingReady(): bool
    {
        return Schema::hasTable('accounting_journal_batches')
            && Schema::hasTable('accounting_journal_lines')
            && Schema::hasTable('accounting_chart_accounts');
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsSnapshot(?int $tenantId = null, int $limit = 100): array
    {
        if (! $this->accountingReady()) {
            return [
                'ready' => false,
                'message' => 'Accounting journal tables are not available on this database.',
                'carry_forward_missing_invoice_issued' => collect(),
                'utility_missing_invoice_issued' => collect(),
                'invoices_missing_gl_batch' => collect(),
                'landlord_ledger_gaps' => collect(),
                'suspense_double_post_risk' => collect(),
                'allocation_gl_drift' => collect(),
                'cash_double_debit' => collect(),
                'negative_landlord_payable' => collect(),
                'invoice_without_ar' => collect(),
                'payment_without_cash' => collect(),
            ];
        }

        return [
            'ready' => true,
            'carry_forward_missing_invoice_issued' => $this->detectCarryForwardMissingInvoiceIssued($tenantId, $limit),
            'utility_missing_invoice_issued' => $this->detectUtilityMissingInvoiceIssued($tenantId, $limit),
            'invoices_missing_gl_batch' => $this->detectInvoicesMissingGlBatch($tenantId, $limit),
            'landlord_ledger_gaps' => $this->detectLandlordLedgerGaps($tenantId, $limit),
            'suspense_double_post_risk' => $this->detectSuspenseDoublePostRisk($tenantId, $limit),
            'allocation_gl_drift' => $this->detectAllocationGlDrift($tenantId, $limit),
            'cash_double_debit' => $this->detectCashDoubleDebit($tenantId, $limit),
            'negative_landlord_payable' => $this->detectNegativeLandlordPayable($limit),
            'invoice_without_ar' => $this->detectInvoiceWithoutAr($tenantId, $limit),
            'payment_without_cash' => $this->detectPaymentWithoutCash($tenantId, $limit),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function persistDetectedIssues(array $snapshot, bool $dedupe = true): int
    {
        if (! Schema::hasTable('pm_accounting_audit_logs')) {
            return 0;
        }

        $logged = 0;
        $record = $dedupe
            ? [PmAccountingAuditLog::class, 'recordIfNew']
            : [PmAccountingAuditLog::class, 'record'];

        $map = [
            'carry_forward_missing_invoice_issued' => PmAccountingAuditLog::ACTION_MISSING_INVOICE_ISSUED,
            'utility_missing_invoice_issued' => PmAccountingAuditLog::ACTION_MISSING_INVOICE_ISSUED,
            'invoices_missing_gl_batch' => PmAccountingAuditLog::ACTION_MISSING_INVOICE_ISSUED,
            'landlord_ledger_gaps' => PmAccountingAuditLog::ACTION_LANDLORD_LEDGER_GAP,
            'suspense_double_post_risk' => PmAccountingAuditLog::ACTION_SUSPENSE_DOUBLE_POST,
            'allocation_gl_drift' => PmAccountingAuditLog::ACTION_ALLOCATION_GL_DRIFT,
            'cash_double_debit' => PmAccountingAuditLog::ACTION_CASH_DOUBLE_DEBIT,
            'negative_landlord_payable' => PmAccountingAuditLog::ACTION_NEGATIVE_LANDLORD_PAYABLE,
            'invoice_without_ar' => PmAccountingAuditLog::ACTION_INVOICE_WITHOUT_AR,
            'payment_without_cash' => PmAccountingAuditLog::ACTION_PAYMENT_WITHOUT_CASH,
        ];

        foreach ($map as $key => $action) {
            $rows = $snapshot[$key] ?? collect();
            if (! $rows instanceof Collection) {
                continue;
            }

            foreach ($rows as $row) {
                $entityType = match ($key) {
                    'landlord_ledger_gaps', 'suspense_double_post_risk', 'cash_double_debit', 'payment_without_cash' => 'pm_payment',
                    'allocation_gl_drift' => 'pm_tenant',
                    'negative_landlord_payable' => 'property',
                    default => 'pm_invoice',
                };

                $entityId = match ($entityType) {
                    'pm_payment' => (int) ($row['payment_id'] ?? 0),
                    'pm_tenant' => (int) ($row['tenant_id'] ?? 0),
                    'property' => (int) ($row['property_id'] ?? 0),
                    default => (int) ($row['invoice_id'] ?? 0),
                };

                if ($entityId <= 0 && $entityType !== 'pm_tenant') {
                    continue;
                }

                $record(
                    $action,
                    $entityType,
                    $entityId > 0 ? $entityId : null,
                    [
                        'pm_tenant_id' => (int) ($row['tenant_id'] ?? 0) ?: null,
                        'pm_invoice_id' => (int) ($row['invoice_id'] ?? 0) ?: null,
                        'pm_payment_id' => (int) ($row['payment_id'] ?? 0) ?: null,
                        'summary' => (string) ($row['message'] ?? $row['summary'] ?? ucfirst(str_replace('_', ' ', $key))),
                        'payload' => array_merge(['category' => $key], $row),
                    ]
                );
                $logged++;
            }
        }

        PmAccountingAuditLog::record(
            PmAccountingAuditLog::ACTION_RECONCILIATION_SCAN,
            'accounting_reconciliation',
            null,
            [
                'summary' => 'Accounting reconciliation scan completed',
                'payload' => [
                    'issue_counts' => collect($map)->mapWithKeys(fn ($action, $key) => [
                        $key => ($snapshot[$key] ?? collect())->count(),
                    ])->all(),
                ],
            ]
        );
        $logged++;

        return $logged;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectCarryForwardMissingInvoiceIssued(?int $tenantId = null, int $limit = 100): Collection
    {
        return $this->detectMissingInvoiceIssuedQuery($tenantId, $limit)
            ->where('description', 'like', FinanceFirebreakService::CARRY_FORWARD_PREFIX.'%')
            ->get()
            ->map(fn (PmInvoice $invoice) => $this->mapMissingInvoiceRow($invoice, 'carry_forward'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectUtilityMissingInvoiceIssued(?int $tenantId = null, int $limit = 100): Collection
    {
        return $this->detectMissingInvoiceIssuedQuery($tenantId, $limit)
            ->where('invoice_type', PmInvoice::TYPE_WATER)
            ->get()
            ->map(fn (PmInvoice $invoice) => $this->mapMissingInvoiceRow($invoice, 'utility'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectInvoicesMissingGlBatch(?int $tenantId = null, int $limit = 100): Collection
    {
        return $this->detectMissingInvoiceIssuedQuery($tenantId, $limit)
            ->get()
            ->map(fn (PmInvoice $invoice) => $this->mapMissingInvoiceRow($invoice, 'general'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectLandlordLedgerGaps(?int $tenantId = null, int $limit = 100): Collection
    {
        $query = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('amount', '>', 0)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('accounting_journal_batches as b')
                    ->whereColumn('b.source_id', 'pm_payments.id')
                    ->where('b.source_type', 'pm_payment')
                    ->where('b.event_type', 'payment_received')
                    ->where('b.status', AccountingJournalBatch::STATUS_POSTED);
            })
            ->whereExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('pm_payment_allocations as a')
                    ->whereColumn('a.pm_payment_id', 'pm_payments.id')
                    ->where(function ($inner) {
                        $inner->whereNull('a.is_reversed')->orWhere('a.is_reversed', false);
                    });
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('pm_landlord_ledger_entries as l')
                    ->whereColumn('l.reference_id', 'pm_payments.id')
                    ->where('l.reference_type', 'pm_payment');
            });

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        return $query
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (PmPayment $payment) => [
                'payment_id' => (int) $payment->id,
                'tenant_id' => (int) $payment->pm_tenant_id,
                'amount' => round((float) $payment->amount, 2),
                'external_ref' => (string) ($payment->external_ref ?? ''),
                'paid_at' => optional($payment->paid_at)->toDateTimeString(),
                'message' => sprintf(
                    'Payment #%d posted to GL but has no landlord ledger credit.',
                    $payment->id,
                ),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectSuspenseDoublePostRisk(?int $tenantId = null, int $limit = 100): Collection
    {
        $query = DB::table('accounting_journal_batches as b1')
            ->join('accounting_journal_batches as b2', function ($join) {
                $join->on('b1.source_id', '=', 'b2.source_id')
                    ->where('b1.source_type', 'pm_payment')
                    ->where('b2.source_type', 'pm_payment')
                    ->where('b1.event_type', 'payment_received')
                    ->where('b2.event_type', 'payment_unmatched_suspense')
                    ->where('b1.status', AccountingJournalBatch::STATUS_POSTED)
                    ->where('b2.status', AccountingJournalBatch::STATUS_POSTED);
            })
            ->join('pm_payments as p', 'p.id', '=', 'b1.source_id')
            ->select([
                'p.id as payment_id',
                'p.pm_tenant_id as tenant_id',
                'p.amount',
                'p.external_ref',
                'p.paid_at',
            ])
            ->orderByDesc('p.id')
            ->limit($limit);

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('p.pm_tenant_id', $tenantId);
        }

        return collect($query->get())->map(fn ($row) => [
            'payment_id' => (int) $row->payment_id,
            'tenant_id' => (int) $row->tenant_id,
            'amount' => round((float) $row->amount, 2),
            'external_ref' => (string) ($row->external_ref ?? ''),
            'paid_at' => $row->paid_at ? (string) $row->paid_at : null,
            'message' => sprintf(
                'Payment #%d has both payment_received and payment_unmatched_suspense batches (cash double-debit risk).',
                (int) $row->payment_id,
            ),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectAllocationGlDrift(?int $tenantId = null, int $limit = 100): Collection
    {
        $arAccountIds = array_values(array_filter([
            $this->accountId(self::ACC_AR),
            $this->accountId(self::ACC_UTILITY_AR),
        ]));

        $creditAccountId = $this->accountId(self::ACC_TENANT_CREDIT_LIABILITY);

        if ($arAccountIds === [] && $creditAccountId === null) {
            return collect();
        }

        $operational = PmPaymentAllocation::query()
            ->join('pm_payments as p', 'p.id', '=', 'pm_payment_allocations.pm_payment_id')
            ->where('p.status', PmPayment::STATUS_COMPLETED)
            ->where(function (Builder $q) {
                $q->whereNull('pm_payment_allocations.is_reversed')
                    ->orWhere('pm_payment_allocations.is_reversed', false);
            })
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('p.pm_tenant_id', $tenantId))
            ->groupBy('p.pm_tenant_id')
            ->selectRaw('p.pm_tenant_id as tenant_id, ROUND(SUM(pm_payment_allocations.amount), 2) as operational_allocations')
            ->pluck('operational_allocations', 'tenant_id');

        $glArReductions = collect();
        if ($arAccountIds !== []) {
            $glArReductions = AccountingJournalLine::query()
                ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
                ->where('b.source_type', 'pm_payment')
                ->where('b.event_type', 'payment_received')
                ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
                ->whereIn('accounting_journal_lines.account_id', $arAccountIds)
                ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
                ->groupBy('accounting_journal_lines.tenant_id')
                ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.credit), 2) as gl_ar_reductions')
                ->pluck('gl_ar_reductions', 'tenant_id');
        }

        $glTenantCredit = collect();
        if ($creditAccountId !== null) {
            $glTenantCredit = AccountingJournalLine::query()
                ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
                ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
                ->where('accounting_journal_lines.account_id', $creditAccountId)
                ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
                ->groupBy('accounting_journal_lines.tenant_id')
                ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) as gl_tenant_credit_liability')
                ->pluck('gl_tenant_credit_liability', 'tenant_id');
        }

        $operationalCredit = collect();
        if (Schema::hasTable('pm_tenant_credit_balances')) {
            $operationalCredit = PmTenantCreditBalance::query()
                ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
                ->pluck('balance', 'pm_tenant_id');
        }

        $tenantIds = collect()
            ->merge($operational->keys())
            ->merge($glArReductions->keys())
            ->merge($glTenantCredit->keys())
            ->merge($operationalCredit->keys())
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values();

        return $tenantIds
            ->map(function ($id) use ($operational, $glArReductions, $glTenantCredit, $operationalCredit) {
                $tenantId = (int) $id;
                $opsAlloc = round((float) ($operational[$tenantId] ?? 0), 2);
                $glAr = round((float) ($glArReductions[$tenantId] ?? 0), 2);
                $opsCredit = round((float) ($operationalCredit[$tenantId] ?? 0), 2);
                $glCredit = round((float) ($glTenantCredit[$tenantId] ?? 0), 2);
                $allocationDrift = round($opsAlloc - $glAr, 2);
                $creditDrift = round($opsCredit - $glCredit, 2);

                return [
                    'tenant_id' => $tenantId,
                    'operational_allocations' => $opsAlloc,
                    'gl_ar_reductions' => $glAr,
                    'allocation_drift' => $allocationDrift,
                    'tenant_credit_operational' => $opsCredit,
                    'tenant_credit_gl_liability' => $glCredit,
                    'tenant_credit_drift' => $creditDrift,
                    'message' => sprintf(
                        'Tenant #%d allocation/GL drift KES %s; tenant credit drift KES %s.',
                        $tenantId,
                        number_format($allocationDrift, 2),
                        number_format($creditDrift, 2),
                    ),
                ];
            })
            ->filter(function (array $row) {
                return abs((float) $row['allocation_drift']) > 0.02
                    || abs((float) $row['tenant_credit_drift']) > 0.02;
            })
            ->sortByDesc(fn (array $row) => max(abs((float) $row['allocation_drift']), abs((float) $row['tenant_credit_drift'])))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectCashDoubleDebit(?int $tenantId = null, int $limit = 100): Collection
    {
        $cashAccountId = $this->accountId(self::ACC_CASH_BANK);
        if ($cashAccountId === null) {
            return collect();
        }

        $rows = DB::table('pm_payments as p')
            ->join('accounting_journal_batches as b', function ($join) {
                $join->on('b.source_id', '=', 'p.id')
                    ->where('b.source_type', 'pm_payment')
                    ->whereIn('b.event_type', ['payment_received', 'payment_unmatched_suspense'])
                    ->where('b.status', AccountingJournalBatch::STATUS_POSTED);
            })
            ->join('accounting_journal_lines as l', function ($join) use ($cashAccountId) {
                $join->on('l.batch_id', '=', 'b.id')
                    ->where('l.account_id', $cashAccountId)
                    ->where('l.debit', '>', 0);
            })
            ->when($tenantId !== null && $tenantId > 0, fn ($q) => $q->where('p.pm_tenant_id', $tenantId))
            ->groupBy('p.id', 'p.pm_tenant_id', 'p.amount', 'p.external_ref', 'p.paid_at')
            ->havingRaw('ROUND(SUM(l.debit), 2) > ROUND(p.amount, 2) + 0.01')
            ->selectRaw('p.id as payment_id, p.pm_tenant_id as tenant_id, p.amount, p.external_ref, p.paid_at, ROUND(SUM(l.debit), 2) as cash_debit_total')
            ->orderByDesc('p.id')
            ->limit($limit)
            ->get();

        return collect($rows)->map(fn ($row) => [
            'payment_id' => (int) $row->payment_id,
            'tenant_id' => (int) $row->tenant_id,
            'payment_amount' => round((float) $row->amount, 2),
            'cash_debit_total' => round((float) $row->cash_debit_total, 2),
            'external_ref' => (string) ($row->external_ref ?? ''),
            'paid_at' => $row->paid_at ? (string) $row->paid_at : null,
            'message' => sprintf(
                'Payment #%d cash debits (KES %s) exceed payment amount (KES %s).',
                (int) $row->payment_id,
                number_format((float) $row->cash_debit_total, 2),
                number_format((float) $row->amount, 2),
            ),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectNegativeLandlordPayable(int $limit = 100): Collection
    {
        $payableAccountId = $this->accountId(self::ACC_LANDLORD_PAYABLE);
        if ($payableAccountId === null) {
            return collect();
        }

        $rows = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->where('accounting_journal_lines.account_id', $payableAccountId)
            ->whereNotNull('accounting_journal_lines.property_id')
            ->groupBy('accounting_journal_lines.property_id')
            ->havingRaw('ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) < -0.01')
            ->selectRaw('accounting_journal_lines.property_id, ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) as net_payable')
            ->orderBy('net_payable')
            ->limit($limit)
            ->get();

        return collect($rows)->map(fn ($row) => [
            'property_id' => (int) $row->property_id,
            'net_landlord_payable' => round((float) $row->net_payable, 2),
            'message' => sprintf(
                'Property #%d landlord payable balance is negative (KES %s).',
                (int) $row->property_id,
                number_format((float) $row->net_payable, 2),
            ),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectInvoiceWithoutAr(?int $tenantId = null, int $limit = 100): Collection
    {
        $arAccountIds = array_values(array_filter([
            $this->accountId(self::ACC_AR),
            $this->accountId(self::ACC_UTILITY_AR),
        ]));

        if ($arAccountIds === []) {
            return collect();
        }

        $query = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('event_type', 'invoice_issued')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->whereNotExists(function ($sub) use ($arAccountIds) {
                $sub->select(DB::raw('1'))
                    ->from('accounting_journal_lines as l')
                    ->whereColumn('l.batch_id', 'accounting_journal_batches.id')
                    ->whereIn('l.account_id', $arAccountIds)
                    ->where('l.debit', '>', 0);
            })
            ->orderByDesc('id')
            ->limit($limit);

        if ($tenantId !== null && $tenantId > 0) {
            $query->whereExists(function ($sub) use ($tenantId) {
                $sub->select(DB::raw('1'))
                    ->from('pm_invoices as i')
                    ->whereColumn('i.id', 'accounting_journal_batches.source_id')
                    ->where('i.pm_tenant_id', $tenantId);
            });
        }

        return $query->get()->map(function (AccountingJournalBatch $batch) {
            $invoice = PmInvoice::query()->withoutGlobalScopes()->find($batch->source_id);

            return [
                'batch_id' => (int) $batch->id,
                'invoice_id' => (int) $batch->source_id,
                'tenant_id' => (int) ($invoice?->pm_tenant_id ?? 0),
                'invoice_no' => (string) ($invoice?->invoice_no ?? ''),
                'batch_date' => optional($batch->date)->toDateString(),
                'message' => sprintf(
                    'Invoice issued batch #%d for invoice #%d has no AR debit line.',
                    $batch->id,
                    (int) $batch->source_id,
                ),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectPaymentWithoutCash(?int $tenantId = null, int $limit = 100): Collection
    {
        $cashAccountId = $this->accountId(self::ACC_CASH_BANK);
        if ($cashAccountId === null) {
            return collect();
        }

        $query = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('event_type', 'payment_received')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->whereNotExists(function ($sub) use ($cashAccountId) {
                $sub->select(DB::raw('1'))
                    ->from('accounting_journal_lines as l')
                    ->whereColumn('l.batch_id', 'accounting_journal_batches.id')
                    ->where('l.account_id', $cashAccountId)
                    ->where('l.debit', '>', 0);
            })
            ->orderByDesc('id')
            ->limit($limit);

        if ($tenantId !== null && $tenantId > 0) {
            $query->whereExists(function ($sub) use ($tenantId) {
                $sub->select(DB::raw('1'))
                    ->from('pm_payments as p')
                    ->whereColumn('p.id', 'accounting_journal_batches.source_id')
                    ->where('p.pm_tenant_id', $tenantId);
            });
        }

        return $query->get()->map(function (AccountingJournalBatch $batch) {
            $payment = PmPayment::query()->find($batch->source_id);

            return [
                'batch_id' => (int) $batch->id,
                'payment_id' => (int) $batch->source_id,
                'tenant_id' => (int) ($payment?->pm_tenant_id ?? 0),
                'external_ref' => (string) ($payment?->external_ref ?? ''),
                'batch_date' => optional($batch->date)->toDateString(),
                'message' => sprintf(
                    'Payment received batch #%d for payment #%d has no cash debit line.',
                    $batch->id,
                    (int) $batch->source_id,
                ),
            ];
        });
    }

    /**
     * @return Collection<int, PmAccountingAuditLog>
     */
    public function recentAuditLogs(int $limit = 50): Collection
    {
        if (! Schema::hasTable('pm_accounting_audit_logs')) {
            return collect();
        }

        return PmAccountingAuditLog::query()
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    private function detectMissingInvoiceIssuedQuery(?int $tenantId, int $limit): Builder
    {
        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('amount', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('accounting_journal_batches as b')
                    ->whereColumn('b.source_id', 'pm_invoices.id')
                    ->where('b.source_type', 'pm_invoice')
                    ->where('b.event_type', 'invoice_issued')
                    ->where('b.status', AccountingJournalBatch::STATUS_POSTED);
            })
            ->orderByDesc('id')
            ->limit($limit);

        if ($tenantId !== null && $tenantId > 0) {
            $query->where('pm_tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMissingInvoiceRow(PmInvoice $invoice, string $category): array
    {
        return [
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'tenant_id' => (int) $invoice->pm_tenant_id,
            'invoice_type' => (string) $invoice->invoice_type,
            'amount' => round((float) $invoice->amount, 2),
            'status' => (string) $invoice->status,
            'category' => $category,
            'message' => sprintf(
                'Invoice %s (#%d) has amount > 0 but no posted invoice_issued GL batch.',
                $invoice->invoice_no,
                $invoice->id,
            ),
        ];
    }

    private function accountId(string $code): ?int
    {
        if ($this->accountIds === null) {
            $this->accountIds = AccountingChartAccount::query()
                ->whereIn('code', [
                    self::ACC_CASH_BANK,
                    self::ACC_AR,
                    self::ACC_UTILITY_AR,
                    self::ACC_LANDLORD_PAYABLE,
                    self::ACC_TENANT_CREDIT_LIABILITY,
                ])
                ->pluck('id', 'code')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $this->accountIds[$code] ?? null;
    }
}
