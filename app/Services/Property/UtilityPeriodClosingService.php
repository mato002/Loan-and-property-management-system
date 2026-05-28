<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenantCreditBalance;
use App\Models\PmWaterReading;
use App\Models\User;
use App\Models\UtilityAuditLog;
use App\Models\UtilityBillingPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UtilityPeriodClosingService
{
    public function __construct(
        private readonly UtilityPeriodGuardService $guard,
        private readonly UtilityReconciliationService $reconciliation,
    ) {}

    /**
     * @return list<array{key: string, label: string, passed: bool, severity: string, detail: string}>
     */
    public function reconciliationChecklist(string $billingMonth, ?int $agentUserId = null): array
    {
        $agentUserId = $agentUserId ?? (int) Auth::id();
        $from = $billingMonth.'-01';
        $to = now()->parse($billingMonth.'-01')->endOfMonth()->toDateString();

        $dashboard = $this->reconciliation->dashboard($from, $to, null, $agentUserId);
        $totals = $dashboard['totals'];

        $billedInvoices = (float) PmInvoice::query()
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->where('billing_period', $billingMonth)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->sum('amount');

        $billedReadings = (float) PmWaterReading::query()
            ->where('billing_month', $billingMonth)
            ->whereNotNull('pm_invoice_id')
            ->sum('amount');

        $billedVariance = abs($billedInvoices - $billedReadings);
        $billedPassed = $billedVariance <= 0.05 || ($billedInvoices <= 0 && $billedReadings <= 0);

        $allocationIssues = PmInvoice::query()
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->where('billing_period', $billingMonth)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->get()
            ->filter(function (PmInvoice $invoice) {
                $invoice->syncAmountPaidFromAllocations();
                $allocated = (float) $invoice->allocations()->where('is_reversed', false)->sum('amount');

                return abs((float) $invoice->amount_paid - $allocated) > 0.01;
            })
            ->count();

        $penaltyCount = PmInvoicePenaltyApplication::query()
            ->whereNull('reversed_at')
            ->whereHas('invoice', fn ($q) => $q
                ->where('billing_period', $billingMonth)
                ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED]))
            ->count();

        $penaltyWithoutGl = PmInvoicePenaltyApplication::query()
            ->whereNull('reversed_at')
            ->whereHas('invoice', fn ($q) => $q
                ->where('billing_period', $billingMonth)
                ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED]))
            ->get()
            ->filter(fn (PmInvoicePenaltyApplication $app) => ! AccountingJournalBatch::query()
                ->where('source_type', 'pm_invoice_penalty_application')
                ->where('source_id', $app->id)
                ->where('event_type', 'water_penalty_applied')
                ->where('status', AccountingJournalBatch::STATUS_POSTED)
                ->exists())
            ->count();

        $uninvoicedReadings = PmWaterReading::query()
            ->where('billing_month', $billingMonth)
            ->whereNull('pm_invoice_id')
            ->count();

        $glVariance = abs((float) ($totals['gl_subledger_variance'] ?? 0));

        return [
            [
                'key' => 'billed_accounted',
                'label' => 'Billed = accounted',
                'passed' => $billedPassed,
                'severity' => 'critical',
                'detail' => $billedPassed
                    ? 'Invoice total '.PropertyMoney::kes($billedInvoices).' matches invoiced readings '.PropertyMoney::kes($billedReadings).'.'
                    : 'Variance '.PropertyMoney::kes($billedVariance).' between invoices ('.PropertyMoney::kes($billedInvoices).') and readings ('.PropertyMoney::kes($billedReadings).').',
            ],
            [
                'key' => 'allocations_balanced',
                'label' => 'Allocations balanced',
                'passed' => $allocationIssues === 0,
                'severity' => 'critical',
                'detail' => $allocationIssues === 0
                    ? 'All utility invoice amount_paid values match allocation sums.'
                    : $allocationIssues.' invoice(s) have allocation drift — run payments:repair-allocations.',
            ],
            [
                'key' => 'penalties_reconciled',
                'label' => 'Penalties reconciled',
                'passed' => $penaltyWithoutGl === 0,
                'severity' => 'critical',
                'detail' => $penaltyCount === 0
                    ? 'No active penalties on this period.'
                    : ($penaltyWithoutGl === 0
                        ? $penaltyCount.' penalty application(s) have GL batches.'
                        : $penaltyWithoutGl.' penalty application(s) missing GL posting.'),
            ],
            [
                'key' => 'readings_invoiced',
                'label' => 'Readings invoiced',
                'passed' => $uninvoicedReadings === 0,
                'severity' => 'critical',
                'detail' => $uninvoicedReadings === 0
                    ? 'All readings for this month are invoiced.'
                    : $uninvoicedReadings.' uninvoiced reading(s) remain for '.$billingMonth.'.',
            ],
            [
                'key' => 'gl_subledger',
                'label' => 'GL subledger tie-out',
                'passed' => $glVariance <= 1.0,
                'severity' => 'warning',
                'detail' => '1210 vs open AR variance: '.PropertyMoney::kes($glVariance).'.',
            ],
            [
                'key' => 'suspense_reviewed',
                'label' => 'Suspense reviewed',
                'passed' => abs((float) ($totals['suspense_balance'] ?? 0)) < 0.01,
                'severity' => 'warning',
                'detail' => 'Suspense (1250) balance: '.PropertyMoney::kes((float) ($totals['suspense_balance'] ?? 0)).'.',
            ],
        ];
    }

    public function canClose(string $billingMonth, bool $acknowledgeSuspense = false, ?int $agentUserId = null): bool
    {
        $checks = $this->reconciliationChecklist($billingMonth, $agentUserId);

        foreach ($checks as $check) {
            if (($check['severity'] ?? '') === 'critical' && ! ($check['passed'] ?? false)) {
                return false;
            }
        }

        if (! $acknowledgeSuspense) {
            foreach ($checks as $check) {
                if (($check['key'] ?? '') === 'suspense_reviewed' && ! ($check['passed'] ?? false)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCloseReport(string $billingMonth, ?int $agentUserId = null): array
    {
        $agentUserId = $agentUserId ?? (int) Auth::id();
        $from = $billingMonth.'-01';
        $to = now()->parse($billingMonth.'-01')->endOfMonth()->toDateString();
        $dashboard = $this->reconciliation->dashboard($from, $to, null, $agentUserId);

        $outstanding = PmInvoice::query()
            ->with('tenant', 'unit.property')
            ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
            ->where('billing_period', $billingMonth)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->get()
            ->map(fn (PmInvoice $inv) => [
                'invoice_no' => $inv->invoice_no,
                'tenant' => $inv->tenant?->name ?? '—',
                'property' => $inv->unit?->property?->name ?? '—',
                'balance' => max(0.0, (float) $inv->amount - (float) $inv->amount_paid),
            ])
            ->all();

        $creditsTotal = (float) PmTenantCreditBalance::query()->sum('balance');

        $adjustments = UtilityAuditLog::query()
            ->where('billing_month', $billingMonth)
            ->whereIn('action', [
                'penalty_applied', 'penalty_reversed', 'period_closed', 'period_override_executed',
            ])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($log) => [
                'action' => $log->action,
                'at' => $log->created_at?->toDateTimeString(),
                'notes' => $log->notes,
            ])
            ->all();

        return [
            'billing_month' => $billingMonth,
            'generated_at' => now()->toDateTimeString(),
            'totals' => $dashboard['totals'],
            'kpis' => $dashboard['kpis'],
            'outstanding_balances' => $outstanding,
            'credits_summary' => [
                'total_unapplied' => $creditsTotal,
                'total_unapplied_display' => PropertyMoney::kes($creditsTotal),
            ],
            'adjustments' => $adjustments,
            'reading_count' => PmWaterReading::query()->where('billing_month', $billingMonth)->count(),
            'invoice_count' => PmInvoice::query()
                ->where('billing_period', $billingMonth)
                ->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED])
                ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
                ->count(),
        ];
    }

    public function closePeriod(string $billingMonth, User $actor, ?string $notes = null, bool $acknowledgeSuspense = false): UtilityBillingPeriod
    {
        if (! $this->canClose($billingMonth, $acknowledgeSuspense, (int) $actor->id)) {
            throw new RuntimeException('Reconciliation checks failed. Resolve critical items before closing this period.');
        }

        $period = $this->guard->ensurePeriod($billingMonth, (int) $actor->id);
        if ($period->isClosed()) {
            throw new RuntimeException('Period '.$billingMonth.' is already closed.');
        }

        $checklist = $this->reconciliationChecklist($billingMonth, (int) $actor->id);
        $closeReport = $this->buildCloseReport($billingMonth, (int) $actor->id);

        return DB::transaction(function () use ($period, $actor, $notes, $acknowledgeSuspense, $checklist, $closeReport, $billingMonth) {
            $period->update([
                'status' => UtilityBillingPeriod::STATUS_CLOSED,
                'closed_at' => now(),
                'closed_by_user_id' => $actor->id,
                'close_notes' => $notes,
                'reconciliation_snapshot' => $checklist,
                'close_report' => $closeReport,
                'suspense_acknowledged' => $acknowledgeSuspense,
            ]);

            UtilityAuditLog::record('period_closed', 'utility_billing_period', (int) $period->id, [
                'billing_month' => $billingMonth,
                'actor_user_id' => $actor->id,
                'payload' => [
                    'close_report_summary' => [
                        'total_billed' => $closeReport['totals']['total_billed'] ?? 0,
                        'open_ar' => $closeReport['totals']['open_ar'] ?? 0,
                    ],
                ],
                'notes' => $notes,
            ]);

            return $period->fresh();
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, UtilityBillingPeriod>
     */
    public function recentPeriods(int $months = 18, ?int $agentUserId = null): \Illuminate\Support\Collection
    {
        $agentUserId = $agentUserId ?? (int) Auth::id();
        $keys = collect();
        for ($i = 0; $i < $months; $i++) {
            $keys->push(now()->subMonths($i)->format('Y-m'));
        }

        foreach ($keys as $month) {
            $this->guard->ensurePeriod((string) $month, $agentUserId);
        }

        return UtilityBillingPeriod::query()
            ->where('agent_user_id', $agentUserId)
            ->whereIn('billing_month', $keys->all())
            ->orderByDesc('billing_month')
            ->get();
    }
}
