<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\LoanBookApplication;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanSupportTicket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanDashboardController extends Controller
{
    public function index(): View
    {
        $bookReady = Schema::hasTable('loan_book_loans');
        $paymentsReady = Schema::hasTable('loan_book_payments');
        $clientsReady = Schema::hasTable('loan_clients');
        $applicationsReady = Schema::hasTable('loan_book_applications');
        $disbursementsReady = Schema::hasTable('loan_book_disbursements');

        $kpis = $this->buildKpis($bookReady, $paymentsReady, $clientsReady, $applicationsReady);

        $topArrears = $bookReady ? $this->topArrears() : collect();
        $recentApplications = $applicationsReady ? $this->recentApplications() : collect();

        $emptyTrendMeta = [
            'total' => 0.0,
            'average' => 0.0,
            'peak_month' => null,
            'peak_value' => 0.0,
            'is_empty' => true,
            'payments_6mo' => 0.0,
            'sheet_6mo' => 0.0,
        ];

        $charts = [
            'collections' => $paymentsReady ? $this->monthlyPaymentTotals() : [
                'labels' => [],
                'values' => [],
                'meta' => $emptyTrendMeta,
            ],
            'disbursements' => $disbursementsReady ? $this->monthlyDisbursementTotals() : [
                'labels' => [],
                'values' => [],
                'meta' => $emptyTrendMeta,
            ],
            'dpd' => $bookReady ? $this->dpdBuckets() : ['labels' => [], 'values' => []],
            'loanStatus' => $bookReady ? $this->loansByStatus() : ['labels' => [], 'values' => []],
            'applicationStages' => $applicationsReady ? $this->applicationsByStage() : ['labels' => [], 'values' => []],
        ];

        $opsStrip = $this->buildOpsStrip();

        return view('loan_dashboard', [
            'kpis' => $kpis,
            'charts' => $charts,
            'topArrears' => $topArrears,
            'recentApplications' => $recentApplications,
            'opsStrip' => $opsStrip,
            'bookReady' => $bookReady,
            'paymentsReady' => $paymentsReady,
        ]);
    }

    private function buildKpis(bool $book, bool $payments, bool $clients, bool $applications): array
    {
        $activeLoans = $book
            ? LoanBookLoan::query()->where('status', LoanBookLoan::STATUS_ACTIVE)->count()
            : 0;

        $createdThisMonth = $book
            ? LoanBookLoan::query()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count()
            : 0;
        $createdLastMonth = $book
            ? LoanBookLoan::query()->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count()
            : 0;
        $loanDelta = $createdThisMonth - $createdLastMonth;

        $portfolioStatuses = [
            LoanBookLoan::STATUS_ACTIVE,
            LoanBookLoan::STATUS_RESTRUCTURED,
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
        ];
        $outstanding = $book
            ? (float) LoanBookLoan::query()->whereIn('status', $portfolioStatuses)->sum('balance')
            : 0.0;

        $pipeline = $applications
            ? LoanBookApplication::query()
                ->whereNotIn('stage', [LoanBookApplication::STAGE_DISBURSED, LoanBookApplication::STAGE_DECLINED])
                ->count()
            : 0;

        $creditReview = $applications
            ? LoanBookApplication::query()->where('stage', LoanBookApplication::STAGE_CREDIT_REVIEW)->count()
            : 0;

        $mtdCollections = 0.0;
        if ($payments) {
            $mtdCollections = (float) LoanBookPayment::query()
                ->where('status', LoanBookPayment::STATUS_PROCESSED)
                ->whereNull('merged_into_payment_id')
                ->whereBetween('transaction_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
        }

        $unposted = $payments
            ? LoanBookPayment::query()->unpostedQueue()->count()
            : 0;

        $nplCount = $book
            ? LoanBookLoan::query()
                ->where('status', LoanBookLoan::STATUS_ACTIVE)
                ->where('dpd', '>', 30)
                ->count()
            : 0;

        $clientsCount = $clients
            ? LoanClient::query()->where('kind', LoanClient::KIND_CLIENT)->count()
            : 0;
        $leadsCount = $clients
            ? LoanClient::query()->where('kind', LoanClient::KIND_LEAD)->count()
            : 0;

        $openTickets = Schema::hasTable('loan_support_tickets')
            ? LoanSupportTicket::query()->whereIn('status', [
                LoanSupportTicket::STATUS_OPEN,
                LoanSupportTicket::STATUS_IN_PROGRESS,
            ])->count()
            : 0;

        $pendingAdvances = Schema::hasTable('accounting_salary_advances')
            ? AccountingSalaryAdvance::query()->where('status', AccountingSalaryAdvance::STATUS_PENDING)->count()
            : 0;

        $arrearsTotal = $book
            ? (float) LoanBookLoan::query()
                ->where('dpd', '>', 0)
                ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
                ->sum('balance')
            : 0.0;

        $arrearsAccounts = $book
            ? LoanBookLoan::query()
                ->where('dpd', '>', 0)
                ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
                ->count()
            : 0;

        return [
            'active_loans' => $activeLoans,
            'loan_delta' => $loanDelta,
            'created_this_month' => $createdThisMonth,
            'outstanding' => $outstanding,
            'pipeline' => $pipeline,
            'credit_review' => $creditReview,
            'mtd_collections' => $mtdCollections,
            'unposted_payments' => $unposted,
            'npl_count' => $nplCount,
            'clients' => $clientsCount,
            'leads' => $leadsCount,
            'open_tickets' => $openTickets,
            'pending_advances' => $pendingAdvances,
            'arrears_total' => $arrearsTotal,
            'arrears_accounts' => $arrearsAccounts,
        ];
    }

    private function topArrears(): Collection
    {
        return LoanBookLoan::query()
            ->with('loanClient')
            ->where('dpd', '>', 0)
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->orderByDesc('balance')
            ->limit(5)
            ->get();
    }

    private function recentApplications(): Collection
    {
        return LoanBookApplication::query()
            ->with('loanClient')
            ->whereNotIn('stage', [LoanBookApplication::STAGE_DISBURSED, LoanBookApplication::STAGE_DECLINED])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>, meta: array<string, mixed>}
     */
    private function monthlyPaymentTotals(): array
    {
        $labels = [];
        $paymentTotals = [];
        $sheetTotals = [];
        $hasCollectionTable = Schema::hasTable('loan_book_collection_entries');

        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $labels[] = $d->format('M Y');
            $start = $d->copy()->startOfMonth()->startOfDay();
            $end = $d->copy()->endOfMonth()->endOfDay();

            $paymentTotals[] = (float) LoanBookPayment::query()
                ->where('status', LoanBookPayment::STATUS_PROCESSED)
                ->whereNull('merged_into_payment_id')
                ->whereBetween('transaction_at', [$start, $end])
                ->sum('amount');

            $sheetTotals[] = $hasCollectionTable
                ? (float) LoanBookCollectionEntry::query()
                    ->whereBetween('collected_on', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount')
                : 0.0;
        }

        // IMPORTANT: avoid double counting when teams record the same receipt both as a processed pay-in
        // and a collection sheet line. We treat processed payments as the canonical source for the chart.
        $values = $paymentTotals;

        $meta = $this->trendMeta($labels, $values);
        $meta['payments_6mo'] = array_sum($paymentTotals);
        $meta['sheet_6mo'] = array_sum($sheetTotals);

        return [
            'labels' => $labels,
            'values' => $values,
            'meta' => $meta,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, float>, meta: array<string, mixed>}
     */
    private function monthlyDisbursementTotals(): array
    {
        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $labels[] = $d->format('M Y');
            $from = $d->copy()->startOfMonth()->toDateString();
            $to = $d->copy()->endOfMonth()->toDateString();
            $values[] = (float) LoanBookDisbursement::query()
                ->whereBetween('disbursed_at', [$from, $to])
                ->sum('amount');
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'meta' => $this->trendMeta($labels, $values),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, float>  $values
     * @return array{total: float, average: float, peak_month: ?string, peak_value: float, is_empty: bool}
     */
    private function trendMeta(array $labels, array $values): array
    {
        $values = array_map(static fn ($v): float => (float) $v, $values);
        $total = array_sum($values);
        $count = count($values);
        $peakValue = $count > 0 ? max($values) : 0.0;
        $peakIdx = $peakValue > 0 ? array_search($peakValue, $values, true) : false;
        $peakMonth = $peakIdx !== false ? $labels[$peakIdx] : null;

        return [
            'total' => $total,
            'average' => $count > 0 ? $total / $count : 0.0,
            'peak_month' => $peakMonth,
            'peak_value' => $peakValue,
            'is_empty' => $total <= 0.0,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function dpdBuckets(): array
    {
        $base = LoanBookLoan::query()->where('status', LoanBookLoan::STATUS_ACTIVE);

        $current = (clone $base)->whereBetween('dpd', [0, 5])->count();
        $watch = (clone $base)->whereBetween('dpd', [6, 30])->count();
        $npl = (clone $base)->where('dpd', '>', 30)->count();

        return [
            'labels' => ['Performing (0–5 DPD)', 'Watchlist (6–30 DPD)', 'NPL (31+ DPD)'],
            'values' => [$current, $watch, $npl],
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function loansByStatus(): array
    {
        $rows = LoanBookLoan::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->orderByDesc('c')
            ->get();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $this->humanizeLoanStatus((string) $row->status);
            $values[] = (int) $row->c;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    private function applicationsByStage(): array
    {
        $rows = LoanBookApplication::query()
            ->selectRaw('stage, COUNT(*) as c')
            ->groupBy('stage')
            ->orderByDesc('c')
            ->get();

        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $this->humanizeApplicationStage((string) $row->stage);
            $values[] = (int) $row->c;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{pending_requisitions: int, journal_last_30: int}
     */
    private function buildOpsStrip(): array
    {
        $pendingReq = Schema::hasTable('accounting_requisitions')
            ? AccountingRequisition::query()->where('status', AccountingRequisition::STATUS_PENDING)->count()
            : 0;

        $journal30 = Schema::hasTable('accounting_journal_entries')
            ? AccountingJournalEntry::query()->where('entry_date', '>=', now()->subDays(30)->toDateString())->count()
            : 0;

        return [
            'pending_requisitions' => $pendingReq,
            'journal_last_30' => $journal30,
        ];
    }

    private function humanizeLoanStatus(string $status): string
    {
        return match ($status) {
            LoanBookLoan::STATUS_ACTIVE => 'Active',
            LoanBookLoan::STATUS_CLOSED => 'Closed',
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'Pending disbursement',
            LoanBookLoan::STATUS_WRITTEN_OFF => 'Written off',
            LoanBookLoan::STATUS_RESTRUCTURED => 'Restructured',
            default => str_replace('_', ' ', ucfirst($status)),
        };
    }

    private function humanizeApplicationStage(string $stage): string
    {
        return match ($stage) {
            LoanBookApplication::STAGE_SUBMITTED => 'Submitted',
            LoanBookApplication::STAGE_CREDIT_REVIEW => 'Credit review',
            LoanBookApplication::STAGE_APPROVED => 'Approved',
            LoanBookApplication::STAGE_DECLINED => 'Declined',
            LoanBookApplication::STAGE_DISBURSED => 'Disbursed',
            default => str_replace('_', ' ', ucfirst($stage)),
        };
    }
}
