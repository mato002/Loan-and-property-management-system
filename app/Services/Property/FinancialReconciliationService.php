<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenantCreditBalance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialReconciliationService
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    public const LAYER_INVOICE_AR_VS_GL = 'invoice_ar_vs_gl_ar';

    public const LAYER_ALLOCATIONS_VS_PAID = 'allocations_vs_amount_paid';

    public const LAYER_LANDLORD_VS_GL_2100 = 'landlord_subledger_vs_gl_2100';

    public const LAYER_UTILITY_AR_VS_1210 = 'utility_ar_vs_1210';

    public const LAYER_PENALTIES_VS_GL = 'penalties_vs_penalty_gl';

    public const LAYER_SUSPENSE_VS_UNMATCHED = 'suspense_vs_unmatched_payments';

    public const LAYER_TENANT_CREDIT_VS_LIABILITY = 'tenant_credits_vs_liability';

    private const ACC_AR = '1200';

    private const ACC_UTILITY_AR = '1210';

    private const ACC_SUSPENSE = '1250';

    private const ACC_LANDLORD_PAYABLE = '2100';

    private const ACC_TENANT_CREDIT_LIABILITY = '2260';

    private const TOLERANCE = 0.02;

    /** @var array<string, int>|null */
    private ?array $accountIds = null;

    public function __construct(
        private readonly LandlordSubledgerService $landlordSubledger,
    ) {}

    public function accountingReady(): bool
    {
        return Schema::hasTable('accounting_journal_batches')
            && Schema::hasTable('accounting_journal_lines')
            && Schema::hasTable('accounting_chart_accounts')
            && Schema::hasTable('pm_invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcile(?int $tenantId = null, int $limit = 100, ?string $layer = null): array
    {
        if (! $this->accountingReady()) {
            return [
                'ready' => false,
                'message' => 'Accounting journal tables are not available on this database.',
                'run_at' => now()->toIso8601String(),
                'tenant_filter' => $tenantId,
                'layers' => [],
                'summary' => $this->emptySummary(),
            ];
        }

        $layers = [
            self::LAYER_INVOICE_AR_VS_GL => [
                'label' => 'Invoice AR vs GL AR',
                'mismatches' => $this->reconcileInvoiceArVsGlAr($tenantId, $limit),
                'repair_hint' => 'Run finance:detect-accounting-drift and finance:backfill-carry-forward-gl; verify invoice_issued / credit_memo batches.',
            ],
            self::LAYER_ALLOCATIONS_VS_PAID => [
                'label' => 'Allocations vs amount_paid',
                'mismatches' => $this->reconcileAllocationsVsAmountPaid($tenantId, $limit),
                'repair_hint' => 'Run allocation repair for affected tenants; review payment settlement order.',
            ],
            self::LAYER_LANDLORD_VS_GL_2100 => [
                'label' => 'Landlord subledger vs GL 2100',
                'mismatches' => $this->reconcileLandlordSubledgerVsGl2100($limit),
                'repair_hint' => 'Run finance:backfill-landlord-subledger and finance:reconcile-landlord-subledger.',
            ],
            self::LAYER_UTILITY_AR_VS_1210 => [
                'label' => 'Utility AR vs GL 1210',
                'mismatches' => $this->reconcileUtilityArVs1210($tenantId, $limit),
                'repair_hint' => 'Post missing utility invoice_issued batches; run finance:detect-accounting-drift.',
            ],
            self::LAYER_PENALTIES_VS_GL => [
                'label' => 'Penalties vs penalty GL',
                'mismatches' => $this->reconcilePenaltiesVsPenaltyGl($tenantId, $limit),
                'repair_hint' => 'Reverse and re-apply penalties or post missing water_penalty_applied batches.',
            ],
            self::LAYER_SUSPENSE_VS_UNMATCHED => [
                'label' => 'Suspense vs unmatched payments',
                'mismatches' => $this->reconcileSuspenseVsUnmatchedPayments($tenantId, $limit),
                'repair_hint' => 'Review payment finalize path; ensure unmatched payments use payment_unmatched_suspense only.',
            ],
            self::LAYER_TENANT_CREDIT_VS_LIABILITY => [
                'label' => 'Tenant credits vs liability account',
                'mismatches' => $this->reconcileTenantCreditsVsLiability($tenantId, $limit),
                'repair_hint' => 'Review tenant credit apply/refund flows and payment reversal hardening.',
            ],
        ];

        if ($layer !== null && $layer !== '') {
            $layers = array_filter(
                $layers,
                fn (string $key) => $key === $layer,
                ARRAY_FILTER_USE_KEY
            );
        }

        $summary = $this->summarizeLayers($layers);

        return [
            'ready' => true,
            'run_at' => now()->toIso8601String(),
            'tenant_filter' => $tenantId,
            'layers' => $layers,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function persistReport(array $report, bool $dedupe = true): int
    {
        if (! Schema::hasTable('pm_accounting_audit_logs') || ($report['ready'] ?? false) !== true) {
            return 0;
        }

        $logged = 0;
        $record = $dedupe
            ? [PmAccountingAuditLog::class, 'recordIfNew']
            : [PmAccountingAuditLog::class, 'record'];

        $actionMap = [
            self::LAYER_INVOICE_AR_VS_GL => PmAccountingAuditLog::ACTION_FIN_RECON_INVOICE_AR,
            self::LAYER_ALLOCATIONS_VS_PAID => PmAccountingAuditLog::ACTION_FIN_RECON_ALLOCATIONS,
            self::LAYER_LANDLORD_VS_GL_2100 => PmAccountingAuditLog::ACTION_FIN_RECON_LANDLORD,
            self::LAYER_UTILITY_AR_VS_1210 => PmAccountingAuditLog::ACTION_FIN_RECON_UTILITY_AR,
            self::LAYER_PENALTIES_VS_GL => PmAccountingAuditLog::ACTION_FIN_RECON_PENALTIES,
            self::LAYER_SUSPENSE_VS_UNMATCHED => PmAccountingAuditLog::ACTION_FIN_RECON_SUSPENSE,
            self::LAYER_TENANT_CREDIT_VS_LIABILITY => PmAccountingAuditLog::ACTION_FIN_RECON_TENANT_CREDIT,
        ];

        foreach ($report['layers'] ?? [] as $layerKey => $layerData) {
            $rows = $layerData['mismatches'] ?? collect();
            if (! $rows instanceof Collection) {
                continue;
            }

            foreach ($rows as $row) {
                $entityType = (string) ($row['entity_type'] ?? 'pm_tenant');
                $entityId = (int) ($row['entity_id'] ?? 0);

                $record(
                    $actionMap[$layerKey] ?? PmAccountingAuditLog::ACTION_FIN_RECON_SCAN,
                    $entityType,
                    $entityId > 0 ? $entityId : null,
                    [
                        'pm_tenant_id' => (int) ($row['tenant_id'] ?? 0) ?: null,
                        'pm_invoice_id' => (int) ($row['invoice_id'] ?? 0) ?: null,
                        'pm_payment_id' => (int) ($row['payment_id'] ?? 0) ?: null,
                        'summary' => (string) ($row['message'] ?? 'Financial reconciliation mismatch'),
                        'payload' => array_merge(['layer' => $layerKey, 'severity' => $row['severity'] ?? self::SEVERITY_INFO], $row),
                    ]
                );
                $logged++;
            }
        }

        PmAccountingAuditLog::record(
            PmAccountingAuditLog::ACTION_FIN_RECON_SCAN,
            'financial_reconciliation',
            null,
            [
                'summary' => 'Financial reconciliation scan completed',
                'payload' => $report['summary'] ?? [],
            ]
        );

        return $logged;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileInvoiceArVsGlAr(?int $tenantId = null, int $limit = 100): Collection
    {
        $arAccountIds = $this->accountIdsFor([self::ACC_AR, self::ACC_UTILITY_AR]);
        if ($arAccountIds === []) {
            return collect();
        }

        $operational = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('invoice_kind', '!=', PmInvoice::KIND_CREDIT_NOTE)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
            ->groupBy('pm_tenant_id')
            ->selectRaw('pm_tenant_id as tenant_id')
            ->selectRaw('ROUND(SUM(balance_due), 2) as operational_open_ar')
            ->pluck('operational_open_ar', 'tenant_id');

        $glNet = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->whereIn('accounting_journal_lines.account_id', $arAccountIds)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
            ->groupBy('accounting_journal_lines.tenant_id')
            ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.debit - accounting_journal_lines.credit), 2) as gl_net_ar')
            ->pluck('gl_net_ar', 'tenant_id');

        return $this->mergeTenantDriftRows($operational, $glNet, 'operational_open_ar', 'gl_net_ar', [
            'layer' => self::LAYER_INVOICE_AR_VS_GL,
            'entity_type' => 'pm_tenant',
            'message' => fn (int $tenantId, float $drift, float $ops, float $gl) => sprintf(
                'Tenant #%d open AR (KES %s) vs GL AR net (KES %s), drift KES %s.',
                $tenantId,
                number_format($ops, 2),
                number_format($gl, 2),
                number_format($drift, 2),
            ),
            'repair_recommendation' => 'Run php artisan finance:detect-accounting-drift --audit; backfill missing invoice_issued batches.',
        ], $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileAllocationsVsAmountPaid(?int $tenantId = null, int $limit = 100): Collection
    {
        $query = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('invoice_kind', '!=', PmInvoice::KIND_CREDIT_NOTE)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
            ->whereExists(function ($q) {
                $q->select(DB::raw('1'))
                    ->from('pm_payment_allocations as a')
                    ->whereColumn('a.pm_invoice_id', 'pm_invoices.id');
            })
            ->orderByDesc('id')
            ->limit($limit * 5);

        return $query->get()
            ->map(function (PmInvoice $invoice) {
                $allocated = round((float) $invoice->allocatedAmount(), 2);
                $paid = round((float) $invoice->amount_paid, 2);
                $drift = round($allocated - $paid, 2);

                return [
                    'entity_type' => 'pm_invoice',
                    'entity_id' => (int) $invoice->id,
                    'invoice_id' => (int) $invoice->id,
                    'invoice_no' => (string) $invoice->invoice_no,
                    'tenant_id' => (int) $invoice->pm_tenant_id,
                    'operational_allocations' => $allocated,
                    'amount_paid' => $paid,
                    'drift' => $drift,
                    'severity' => $this->severityForDrift($drift),
                    'repair_recommendation' => 'Run allocation repair for tenant #'.$invoice->pm_tenant_id.'; verify syncAmountPaidFromAllocations.',
                    'message' => sprintf(
                        'Invoice %s allocation total (KES %s) != amount_paid (KES %s), drift KES %s.',
                        $invoice->invoice_no,
                        number_format($allocated, 2),
                        number_format($paid, 2),
                        number_format($drift, 2),
                    ),
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['drift']) > self::TOLERANCE)
            ->sortByDesc(fn (array $row) => abs((float) $row['drift']))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileLandlordSubledgerVsGl2100(int $limit = 100): Collection
    {
        return $this->landlordSubledger
            ->reconcileGl2100VsSubledger(null, $limit)
            ->map(fn (array $row) => [
                'entity_type' => 'property',
                'entity_id' => (int) ($row['property_id'] ?? 0),
                'property_id' => (int) ($row['property_id'] ?? 0),
                'gl_2100_net' => (float) ($row['gl_2100_net'] ?? 0),
                'subledger_net' => (float) ($row['subledger_net'] ?? 0),
                'drift' => (float) ($row['drift'] ?? 0),
                'severity' => $this->severityForDrift((float) ($row['drift'] ?? 0)),
                'repair_recommendation' => 'Run php artisan finance:backfill-landlord-subledger and finance:reconcile-landlord-subledger.',
                'message' => (string) ($row['message'] ?? 'Landlord subledger vs GL 2100 drift'),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileUtilityArVs1210(?int $tenantId = null, int $limit = 100): Collection
    {
        $utilityArId = $this->accountId(self::ACC_UTILITY_AR);
        if ($utilityArId === null) {
            return collect();
        }

        $operational = PmInvoice::query()
            ->withoutGlobalScopes()
            ->billableAr()
            ->where('invoice_type', PmInvoice::TYPE_WATER)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
            ->groupBy('pm_tenant_id')
            ->selectRaw('pm_tenant_id as tenant_id')
            ->selectRaw('ROUND(SUM(balance_due), 2) as operational_utility_open_ar')
            ->pluck('operational_utility_open_ar', 'tenant_id');

        $glNet = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->where('accounting_journal_lines.account_id', $utilityArId)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
            ->groupBy('accounting_journal_lines.tenant_id')
            ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.debit - accounting_journal_lines.credit), 2) as gl_utility_ar_net')
            ->pluck('gl_utility_ar_net', 'tenant_id');

        return $this->mergeTenantDriftRows($operational, $glNet, 'operational_utility_open_ar', 'gl_utility_ar_net', [
            'layer' => self::LAYER_UTILITY_AR_VS_1210,
            'entity_type' => 'pm_tenant',
            'message' => fn (int $tenantId, float $drift, float $ops, float $gl) => sprintf(
                'Tenant #%d utility open AR (KES %s) vs GL 1210 net (KES %s), drift KES %s.',
                $tenantId,
                number_format($ops, 2),
                number_format($gl, 2),
                number_format($drift, 2),
            ),
            'repair_recommendation' => 'Post missing utility invoice_issued / penalty batches; run finance:detect-accounting-drift.',
        ], $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcilePenaltiesVsPenaltyGl(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_invoice_penalty_applications')) {
            return collect();
        }

        $query = PmInvoicePenaltyApplication::query()
            ->join('pm_invoices as i', 'i.id', '=', 'pm_invoice_penalty_applications.pm_invoice_id')
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('i.pm_tenant_id', $tenantId))
            ->whereNull('pm_invoice_penalty_applications.reversed_at')
            ->groupBy('pm_invoice_penalty_applications.pm_invoice_id', 'i.invoice_no', 'i.pm_tenant_id')
            ->selectRaw('pm_invoice_penalty_applications.pm_invoice_id as invoice_id')
            ->selectRaw('i.invoice_no')
            ->selectRaw('i.pm_tenant_id as tenant_id')
            ->selectRaw('ROUND(SUM(pm_invoice_penalty_applications.amount), 2) as operational_penalties')
            ->orderByDesc('invoice_id')
            ->limit($limit * 5);

        return $query->get()
            ->map(function ($row) {
                $invoiceId = (int) $row->invoice_id;
                $opsPenalties = round((float) $row->operational_penalties, 2);

                $glPenalties = round((float) AccountingJournalBatch::query()
                    ->join('pm_invoice_penalty_applications as p', function ($join) {
                        $join->on('p.id', '=', 'accounting_journal_batches.source_id')
                            ->where('accounting_journal_batches.source_type', 'pm_invoice_penalty_application')
                            ->where('accounting_journal_batches.event_type', 'water_penalty_applied');
                    })
                    ->where('p.pm_invoice_id', $invoiceId)
                    ->whereNull('p.reversed_at')
                    ->where('accounting_journal_batches.status', AccountingJournalBatch::STATUS_POSTED)
                    ->join('accounting_journal_lines as l', 'l.batch_id', '=', 'accounting_journal_batches.id')
                    ->where('l.debit', '>', 0)
                    ->sum('l.debit'), 2);

                $drift = round($opsPenalties - $glPenalties, 2);

                return [
                    'entity_type' => 'pm_invoice',
                    'entity_id' => $invoiceId,
                    'invoice_id' => $invoiceId,
                    'invoice_no' => (string) $row->invoice_no,
                    'tenant_id' => (int) $row->tenant_id,
                    'operational_penalties' => $opsPenalties,
                    'gl_penalty_ar' => $glPenalties,
                    'drift' => $drift,
                    'severity' => $this->severityForDrift($drift),
                    'repair_recommendation' => 'Re-apply or reverse penalties on invoice '.$row->invoice_no.'; verify water_penalty_applied batches.',
                    'message' => sprintf(
                        'Invoice %s penalty ops (KES %s) vs GL penalty AR (KES %s), drift KES %s.',
                        $row->invoice_no,
                        number_format($opsPenalties, 2),
                        number_format($glPenalties, 2),
                        number_format($drift, 2),
                    ),
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['drift']) > self::TOLERANCE)
            ->sortByDesc(fn (array $row) => abs((float) $row['drift']))
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileSuspenseVsUnmatchedPayments(?int $tenantId = null, int $limit = 100): Collection
    {
        $suspenseId = $this->accountId(self::ACC_SUSPENSE);
        if ($suspenseId === null) {
            return collect();
        }

        $operational = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
            ->where('channel', '!=', 'tenant_credit')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw('1'))
                    ->from('pm_payment_allocations as a')
                    ->whereColumn('a.pm_payment_id', 'pm_payments.id')
                    ->where(function ($q) {
                        $q->whereNull('a.is_reversed')->orWhere('a.is_reversed', false);
                    });
            })
            ->groupBy('pm_tenant_id')
            ->selectRaw('pm_tenant_id as tenant_id, ROUND(SUM(amount), 2) as operational_unmatched')
            ->pluck('operational_unmatched', 'tenant_id');

        $glSuspense = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.source_type', 'pm_payment')
            ->where('b.event_type', 'payment_unmatched_suspense')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->where('accounting_journal_lines.account_id', $suspenseId)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
            ->groupBy('accounting_journal_lines.tenant_id')
            ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) as gl_suspense_net')
            ->pluck('gl_suspense_net', 'tenant_id');

        return $this->mergeTenantDriftRows($operational, $glSuspense, 'operational_unmatched', 'gl_suspense_net', [
            'layer' => self::LAYER_SUSPENSE_VS_UNMATCHED,
            'entity_type' => 'pm_tenant',
            'message' => fn (int $tenantId, float $drift, float $ops, float $gl) => sprintf(
                'Tenant #%d unmatched payments (KES %s) vs GL suspense 1250 net (KES %s), drift KES %s.',
                $tenantId,
                number_format($ops, 2),
                number_format($gl, 2),
                number_format($drift, 2),
            ),
            'repair_recommendation' => 'Review payment finalize gateway; run finance:detect-accounting-drift for suspense double-post.',
        ], $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileTenantCreditsVsLiability(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_tenant_credit_balances')) {
            return collect();
        }

        $creditAccountId = $this->accountId(self::ACC_TENANT_CREDIT_LIABILITY);
        if ($creditAccountId === null) {
            return collect();
        }

        $operational = PmTenantCreditBalance::query()
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('pm_tenant_id', $tenantId))
            ->pluck('balance', 'pm_tenant_id');

        $glLiability = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->where('accounting_journal_lines.account_id', $creditAccountId)
            ->when($tenantId !== null && $tenantId > 0, fn (Builder $q) => $q->where('accounting_journal_lines.tenant_id', $tenantId))
            ->groupBy('accounting_journal_lines.tenant_id')
            ->selectRaw('accounting_journal_lines.tenant_id, ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) as gl_liability_net')
            ->pluck('gl_liability_net', 'tenant_id');

        return $this->mergeTenantDriftRows($operational, $glLiability, 'operational_tenant_credit', 'gl_liability_net', [
            'layer' => self::LAYER_TENANT_CREDIT_VS_LIABILITY,
            'entity_type' => 'pm_tenant',
            'message' => fn (int $tenantId, float $drift, float $ops, float $gl) => sprintf(
                'Tenant #%d credit balance (KES %s) vs GL 2260 liability net (KES %s), drift KES %s.',
                $tenantId,
                number_format($ops, 2),
                number_format($gl, 2),
                number_format($drift, 2),
            ),
            'repair_recommendation' => 'Review tenant credit apply/refund and payment reversal flows; run finance:detect-reversal-drift.',
        ], $limit);
    }

    public function severityForDrift(float $drift): string
    {
        $abs = abs($drift);
        if ($abs > 1000) {
            return self::SEVERITY_CRITICAL;
        }
        if ($abs > 100) {
            return self::SEVERITY_WARNING;
        }

        return self::SEVERITY_INFO;
    }

    /**
     * @param  array<string, mixed>  $layers
     * @return array<string, mixed>
     */
    private function summarizeLayers(array $layers): array
    {
        $total = 0;
        $critical = 0;
        $warning = 0;
        $info = 0;
        $byLayer = [];

        foreach ($layers as $key => $layer) {
            $rows = $layer['mismatches'] ?? collect();
            $count = $rows instanceof Collection ? $rows->count() : 0;
            $layerCritical = 0;
            $layerWarning = 0;
            $layerInfo = 0;

            if ($rows instanceof Collection) {
                foreach ($rows as $row) {
                    match ($row['severity'] ?? self::SEVERITY_INFO) {
                        self::SEVERITY_CRITICAL => $layerCritical++,
                        self::SEVERITY_WARNING => $layerWarning++,
                        default => $layerInfo++,
                    };
                }
            }

            $total += $count;
            $critical += $layerCritical;
            $warning += $layerWarning;
            $info += $layerInfo;

            $byLayer[$key] = [
                'count' => $count,
                'critical' => $layerCritical,
                'warning' => $layerWarning,
                'info' => $layerInfo,
            ];
        }

        return [
            'total_mismatches' => $total,
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'by_layer' => $byLayer,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'total_mismatches' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'by_layer' => [],
        ];
    }

    /**
     * @param  Collection<int|string, float|int|string>  $operational
     * @param  Collection<int|string, float|int|string>  $gl
     * @param  array<string, mixed>  $meta
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeTenantDriftRows(
        Collection $operational,
        Collection $gl,
        string $opsKey,
        string $glKey,
        array $meta,
        int $limit,
    ): Collection {
        $tenantIds = collect()
            ->merge($operational->keys())
            ->merge($gl->keys())
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values();

        $messageFn = $meta['message'] ?? null;

        return $tenantIds
            ->map(function ($id) use ($operational, $gl, $opsKey, $glKey, $meta, $messageFn) {
                $tenantId = (int) $id;
                $ops = round((float) ($operational[$tenantId] ?? 0), 2);
                $glVal = round((float) ($gl[$tenantId] ?? 0), 2);
                $drift = round($ops - $glVal, 2);
                $message = is_callable($messageFn)
                    ? $messageFn($tenantId, $drift, $ops, $glVal)
                    : 'Tenant #'.$tenantId.' drift KES '.number_format($drift, 2);

                return [
                    'entity_type' => (string) ($meta['entity_type'] ?? 'pm_tenant'),
                    'entity_id' => $tenantId,
                    'tenant_id' => $tenantId,
                    $opsKey => $ops,
                    $glKey => $glVal,
                    'drift' => $drift,
                    'severity' => $this->severityForDrift($drift),
                    'repair_recommendation' => (string) ($meta['repair_recommendation'] ?? 'Review accounting reconciliation dashboard.'),
                    'message' => $message,
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['drift']) > self::TOLERANCE)
            ->sortByDesc(fn (array $row) => abs((float) $row['drift']))
            ->take($limit)
            ->values();
    }

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    private function accountIdsFor(array $codes): array
    {
        return array_values(array_filter(array_map(fn (string $code) => $this->accountId($code), $codes)));
    }

    private function accountId(string $code): ?int
    {
        if ($this->accountIds === null) {
            $this->accountIds = AccountingChartAccount::query()
                ->whereIn('code', [
                    self::ACC_AR,
                    self::ACC_UTILITY_AR,
                    self::ACC_SUSPENSE,
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
