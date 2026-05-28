<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenantCreditBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UtilityReconciliationService
{
    private const ACC_SUSPENSE = '1250';

    private const ACC_UTILITY_AR = '1210';

    /**
     * @return array<string, mixed>
     */
    public function dashboard(?string $from = null, ?string $to = null, ?int $propertyId = null, ?int $agentUserId = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        $invoiceScope = fn ($q) => $this->scopeUtilityInvoices($q, $propertyId, $agentUserId);
        $allocationScope = fn ($q) => $q->whereHas('invoice', fn ($iq) => $this->scopeUtilityInvoices($iq, $propertyId, $agentUserId));

        $totalBilled = (float) PmInvoice::query()
            ->where(fn ($q) => $invoiceScope($q))
            ->where('invoice_kind', PmInvoice::KIND_INVOICE)
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->when($fromDate, fn ($q) => $q->whereDate('issue_date', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('issue_date', '<=', $toDate->toDateString()))
            ->sum('amount');

        $totalPenalties = (float) PmInvoicePenaltyApplication::query()
            ->whereNull('reversed_at')
            ->whereHas('invoice', fn ($q) => $this->scopeUtilityInvoices($q, $propertyId, $agentUserId))
            ->when($fromDate, fn ($q) => $q->whereDate('applied_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('applied_at', '<=', $toDate->toDateString()))
            ->sum('amount');

        $totalCollected = (float) PmPaymentAllocation::query()
            ->where('is_reversed', false)
            ->where(fn ($q) => $allocationScope($q))
            ->whereHas('payment', fn ($pq) => $pq
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->where('channel', '!=', 'tenant_credit')
                ->when($fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $fromDate->toDateString()))
                ->when($toDate, fn ($q) => $q->whereDate('paid_at', '<=', $toDate->toDateString())))
            ->sum('amount');

        $totalCredited = (float) PmPaymentAllocation::query()
            ->where('is_reversed', false)
            ->where(fn ($q) => $allocationScope($q))
            ->whereHas('payment', fn ($pq) => $pq
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->where('channel', 'tenant_credit')
                ->when($fromDate, fn ($q) => $q->whereDate('paid_at', '>=', $fromDate->toDateString()))
                ->when($toDate, fn ($q) => $q->whereDate('paid_at', '<=', $toDate->toDateString())))
            ->sum('amount');

        $totalReversed = (float) PmPaymentAllocation::query()
            ->where('is_reversed', true)
            ->where(fn ($q) => $allocationScope($q))
            ->when($fromDate, fn ($q) => $q->whereDate('reversed_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('reversed_at', '<=', $toDate->toDateString()))
            ->sum('amount');

        $totalReversed += (float) PmInvoicePenaltyApplication::query()
            ->whereNotNull('reversed_at')
            ->whereHas('invoice', fn ($q) => $this->scopeUtilityInvoices($q, $propertyId, $agentUserId))
            ->when($fromDate, fn ($q) => $q->whereDate('reversed_at', '>=', $fromDate->toDateString()))
            ->when($toDate, fn ($q) => $q->whereDate('reversed_at', '<=', $toDate->toDateString()))
            ->sum('amount');

        $openAr = (float) PmInvoice::query()
            ->liveBalances()
            ->where(fn ($q) => $invoiceScope($q))
            ->whereColumn('amount_paid', '<', 'amount')
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as open_total')
            ->value('open_total');

        $unappliedFunds = (float) PmTenantCreditBalance::query()->sum('balance');
        $suspenseBalance = $this->accountNetBalance(self::ACC_SUSPENSE, $agentUserId);
        $utilityArGl = $this->accountNetBalance(self::ACC_UTILITY_AR, $agentUserId);

        $billedWithPenalties = $totalBilled + $totalPenalties;
        $totalSettled = $totalCollected + $totalCredited;

        $kpis = [
            'recovery_pct' => $billedWithPenalties > 0 ? round(($totalSettled / $billedWithPenalties) * 100, 1) : 0.0,
            'unpaid_pct' => $billedWithPenalties > 0 ? round(($openAr / $billedWithPenalties) * 100, 1) : 0.0,
            'penalty_ratio' => $totalBilled > 0 ? round(($totalPenalties / $totalBilled) * 100, 1) : 0.0,
            'collection_efficiency' => $billedWithPenalties > 0
                ? round(($totalCollected / $billedWithPenalties) * 100, 1)
                : 0.0,
        ];

        $aging = $this->agingSummary($propertyId, $agentUserId);

        return [
            'period' => [
                'from' => $fromDate?->toDateString(),
                'to' => $toDate?->toDateString(),
            ],
            'totals' => [
                'total_billed' => round($totalBilled, 2),
                'total_penalties' => round($totalPenalties, 2),
                'total_collected' => round($totalCollected, 2),
                'total_credited' => round($totalCredited, 2),
                'total_reversed' => round($totalReversed, 2),
                'open_ar' => round($openAr, 2),
                'unapplied_funds' => round($unappliedFunds, 2),
                'suspense_balance' => round($suspenseBalance, 2),
                'utility_ar_gl' => round($utilityArGl, 2),
                'gl_subledger_variance' => round($utilityArGl - $openAr, 2),
            ],
            'kpis' => $kpis,
            'aging' => $aging['buckets'],
            'aging_rows' => $aging['rows'],
        ];
    }

    /**
     * @return array{buckets: array<string, array{label: string, count: int, amount: float}>, rows: array<int, array<string, mixed>>}
     */
    public function agingSummary(?int $propertyId = null, ?int $agentUserId = null): array
    {
        $today = now()->startOfDay();

        $invoices = PmInvoice::query()
            ->with('unit.property', 'tenant')
            ->where(fn ($q) => $this->scopeUtilityInvoices($q, $propertyId, $agentUserId))
            ->where('status', '!=', PmInvoice::STATUS_CANCELLED)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->limit(500)
            ->get();

        $buckets = [
            'current' => ['label' => 'Current', 'count' => 0, 'amount' => 0.0],
            '1_30' => ['label' => '1–30 days', 'count' => 0, 'amount' => 0.0],
            '31_60' => ['label' => '31–60 days', 'count' => 0, 'amount' => 0.0],
            '61_90' => ['label' => '61–90 days', 'count' => 0, 'amount' => 0.0],
            '90_plus' => ['label' => '90+ days', 'count' => 0, 'amount' => 0.0],
        ];

        $rows = [];

        foreach ($invoices as $invoice) {
            $balance = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            $daysOverdue = $invoice->due_date ? $invoice->due_date->diffInDays($today, false) : 0;

            $bucketKey = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => '1_30',
                $daysOverdue <= 60 => '31_60',
                $daysOverdue <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$bucketKey]['count']++;
            $buckets[$bucketKey]['amount'] = round($buckets[$bucketKey]['amount'] + $balance, 2);

            $rows[] = [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) ($invoice->invoice_no ?: 'INV-'.$invoice->id),
                'tenant_id' => (int) $invoice->pm_tenant_id,
                'tenant' => (string) ($invoice->tenant?->name ?? '—'),
                'property' => (string) ($invoice->unit?->property?->name ?? '—'),
                'unit' => (string) ($invoice->unit?->label ?? '—'),
                'period' => (string) ($invoice->billing_period ?? '—'),
                'due_date' => $invoice->due_date?->toDateString() ?? '—',
                'balance' => $balance,
                'balance_display' => PropertyMoney::kes($balance),
                'days_overdue' => max(0, $daysOverdue),
                'bucket' => $buckets[$bucketKey]['label'],
                'bucket_key' => $bucketKey,
            ];
        }

        return [
            'buckets' => $buckets,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{stats: array<int, array{label: string, value: string, hint: string}>, columns: array<int, string>, tableRows: array<int, array<int, string>>}
     */
    public function agingReport(?int $propertyId = null): array
    {
        $aging = $this->agingSummary($propertyId);
        $totalOpen = array_sum(array_column($aging['rows'], 'balance'));

        return [
            'stats' => [
                ['label' => 'Open utility AR', 'value' => PropertyMoney::kes($totalOpen), 'hint' => 'Outstanding'],
                ['label' => 'Current', 'value' => PropertyMoney::kes($aging['buckets']['current']['amount']), 'hint' => (string) $aging['buckets']['current']['count'].' invoices'],
                ['label' => '90+ days', 'value' => PropertyMoney::kes($aging['buckets']['90_plus']['amount']), 'hint' => (string) $aging['buckets']['90_plus']['count'].' invoices'],
                ['label' => 'Invoices', 'value' => (string) count($aging['rows']), 'hint' => 'Open items'],
            ],
            'columns' => ['Invoice', 'Tenant', 'Property / Unit', 'Period', 'Due', 'Days', 'Bucket', 'Balance'],
            'tableRows' => collect($aging['rows'])->map(fn (array $row) => [
                $row['invoice_no'],
                $row['tenant'],
                trim($row['property'].' / '.$row['unit'], ' /'),
                $row['period'],
                $row['due_date'],
                (string) $row['days_overdue'],
                $row['bucket'],
                $row['balance_display'],
            ])->all(),
        ];
    }

    private function scopeUtilityInvoices($query, ?int $propertyId, ?int $agentUserId): void
    {
        $query->whereIn('invoice_type', [PmInvoice::TYPE_WATER, PmInvoice::TYPE_MIXED]);

        if ($propertyId) {
            $query->whereHas('unit', fn ($u) => $u->where('property_id', $propertyId));
        }

        if ($agentUserId) {
            $query->where(function ($w) use ($agentUserId) {
                $w->where('agent_user_id', $agentUserId)
                    ->orWhereHas('unit.property', fn ($p) => $p->where('agent_user_id', $agentUserId));
            });
        }
    }

    private function accountNetBalance(string $accountCode, ?int $agentUserId = null): float
    {
        $accountId = AccountingChartAccount::query()->where('code', $accountCode)->value('id');
        if (! $accountId) {
            return 0.0;
        }

        $query = AccountingJournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('batch', fn ($b) => $b->where('status', AccountingJournalBatch::STATUS_POSTED));

        if ($agentUserId) {
            $query->where(function ($w) use ($agentUserId) {
                $w->where('agent_user_id', $agentUserId)
                    ->orWhereHas('batch', fn ($b) => $b->where('agent_user_id', $agentUserId));
            });
        }

        $debits = (float) (clone $query)->sum('debit');
        $credits = (float) (clone $query)->sum('credit');

        return round($debits - $credits, 2);
    }
}
