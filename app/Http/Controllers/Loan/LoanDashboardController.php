<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingRequisition;
use App\Models\AccountingSalaryAdvance;
use App\Models\Employee;
use App\Models\LoanBookApplication;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\LoanSupportTicket;
use App\Models\PropertyPortalSetting;
use App\Models\StaffLeave;
use App\Services\BulkSmsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanDashboardController extends Controller
{
    use ScopesLoanPortfolioAccess;

    private const PERFORMANCE_DEFAULT_TARGETS = [
        'new_target' => 20.0,
        'repeat_target' => 10.0,
        'arrears_target' => 0.0,
        'performing_target' => 70.0,
        'gross_target' => 500000.0,
        'revenue_target' => 170000.0,
    ];

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
        $performanceIndicators = $bookReady ? $this->buildPerformanceIndicators() : collect();
        $profileCard = $this->buildProfileCard();
        $summaryStrip = $this->buildSummaryStrip($bookReady, $clientsReady);

        $currencyCode = 'KES';
        if (Schema::hasTable('property_portal_settings')) {
            $currencyCode = trim((string) PropertyPortalSetting::getValue('loan_currency_code', 'KES')) ?: 'KES';
        }

        return view('loan_dashboard', [
            'kpis' => $kpis,
            'charts' => $charts,
            'topArrears' => $topArrears,
            'recentApplications' => $recentApplications,
            'opsStrip' => $opsStrip,
            'bookReady' => $bookReady,
            'paymentsReady' => $paymentsReady,
            'disbursementsReady' => $disbursementsReady,
            'applicationsReady' => $applicationsReady,
            'clientsReady' => $clientsReady,
            'currencyCode' => $currencyCode,
            'generatedAt' => now(),
            'performanceIndicators' => $performanceIndicators,
            'profileCard' => $profileCard,
            'summaryStrip' => $summaryStrip,
        ]);
    }

    public function smsWalletTopupFromDashboard(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $bulkSms->topup((float) $validated['amount'], $validated['reference'] ?? null, $validated['notes'] ?? null);
        } catch (\Throwable $e) {
            return redirect()
                ->route('loan.dashboard')
                ->withInput()
                ->withErrors(['sms_topup' => 'Could not process SMS topup right now.']);
        }

        return redirect()
            ->route('loan.dashboard')
            ->with('status', 'SMS wallet topped up successfully.');
    }

    private function buildProfileCard(): array
    {
        $user = auth()->user();
        $employee = null;
        if ($user && Schema::hasTable('employees') && ! empty($user->email)) {
            $employee = Employee::query()
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $user->email)])
                ->first();
        }

        $leaveDays = 0;
        if ($employee && Schema::hasTable('staff_leaves')) {
            $leaveDays = (int) StaffLeave::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereYear('start_date', now()->year)
                ->sum('days');
        }

        $smsBalance = 0.0;
        try {
            $smsBalance = (float) app(BulkSmsService::class)->dashboardBalance();
        } catch (\Throwable) {
            $smsBalance = 0.0;
        }

        return [
            'name' => (string) ($user->name ?? 'User'),
            'role' => ucfirst((string) ($user?->effectiveLoanRole() ?: 'user')),
            'branch' => (string) ($employee?->branch ?? 'N/A'),
            'job_title' => (string) ($employee?->job_title ?? 'Staff'),
            'leave_days' => $leaveDays,
            'sms_balance' => $smsBalance,
        ];
    }

    private function buildSummaryStrip(bool $bookReady, bool $clientsReady): array
    {
        if (! $bookReady || ! $clientsReady) {
            return [
                'total_clients' => 0,
                'active_clients' => 0,
                'dormant_clients' => 0,
                'performing_loans' => 0,
                'loan_arrears' => 0.0,
                'par_percent' => 0.0,
            ];
        }

        $totalClients = (int) $this->clientQuery()->where('kind', LoanClient::KIND_CLIENT)->count();
        $activeClients = (int) $this->clientQuery()
            ->where('kind', LoanClient::KIND_CLIENT)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('loan_book_loans as l')
                    ->whereColumn('l.loan_client_id', 'loan_clients.id')
                    ->whereIn('l.status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED]);
            })
            ->count();
        $dormantClients = max(0, $totalClients - $activeClients);

        $performingLoans = (int) $this->loanQuery()
            ->where('status', LoanBookLoan::STATUS_ACTIVE)
            ->where('dpd', '<=', 5)
            ->count();

        $arrears = (float) $this->loanQuery()
            ->where('dpd', '>', 0)
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->sum('balance');

        $outstanding = (float) $this->loanQuery()
            ->whereIn('status', [
                LoanBookLoan::STATUS_ACTIVE,
                LoanBookLoan::STATUS_RESTRUCTURED,
                LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
            ])
            ->sum('balance');
        $parPercent = $outstanding > 0 ? ($arrears / $outstanding) * 100 : 0.0;

        return [
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'dormant_clients' => $dormantClients,
            'performing_loans' => $performingLoans,
            'loan_arrears' => $arrears,
            'par_percent' => $parPercent,
        ];
    }

    public function performanceTargets(Request $request): View
    {
        $monthRaw = trim((string) $request->query('month', now()->format('Y-m')));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthRaw)->startOfMonth();
        } catch (\Throwable) {
            $monthDate = now()->startOfMonth();
        }

        $defaults = self::PERFORMANCE_DEFAULT_TARGETS;
        $employees = Employee::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $existing = Schema::hasTable('loan_performance_indicator_targets')
            ? DB::table('loan_performance_indicator_targets')
                ->where('year', (int) $monthDate->format('Y'))
                ->where('month', (int) $monthDate->format('m'))
                ->get()
                ->keyBy('employee_id')
            : collect();

        $rows = $employees->map(function (Employee $employee) use ($existing, $defaults) {
            $target = $existing->get((int) $employee->id);

            return [
                'employee_id' => (int) $employee->id,
                'staff_name' => trim((string) $employee->full_name),
                'new_target' => (float) ($target->new_target ?? $defaults['new_target']),
                'repeat_target' => (float) ($target->repeat_target ?? $defaults['repeat_target']),
                'arrears_target' => (float) ($target->arrears_target ?? $defaults['arrears_target']),
                'performing_target' => (float) ($target->performing_target ?? $defaults['performing_target']),
                'gross_target' => (float) ($target->gross_target ?? $defaults['gross_target']),
                'revenue_target' => (float) ($target->revenue_target ?? $defaults['revenue_target']),
            ];
        });

        return view('loan.dashboard.performance_targets', [
            'title' => 'Performance Indicator Targets',
            'subtitle' => 'Set monthly targets per staff member.',
            'month' => $monthDate->format('Y-m'),
            'monthLabel' => $monthDate->format('F Y'),
            'rows' => $rows,
            'defaults' => $defaults,
        ]);
    }

    public function performanceTargetsUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'targets' => ['required', 'array'],
            'targets.*.employee_id' => ['required', 'exists:employees,id'],
            'targets.*.new_target' => ['required', 'numeric', 'min:0'],
            'targets.*.repeat_target' => ['required', 'numeric', 'min:0'],
            'targets.*.arrears_target' => ['required', 'numeric', 'min:0'],
            'targets.*.performing_target' => ['required', 'numeric', 'min:0'],
            'targets.*.gross_target' => ['required', 'numeric', 'min:0'],
            'targets.*.revenue_target' => ['required', 'numeric', 'min:0'],
        ]);

        $monthDate = Carbon::createFromFormat('Y-m', (string) $validated['month'])->startOfMonth();
        $year = (int) $monthDate->format('Y');
        $month = (int) $monthDate->format('m');

        if (! Schema::hasTable('loan_performance_indicator_targets')) {
            return redirect()
                ->route('loan.dashboard.performance_targets', ['month' => $monthDate->format('Y-m')])
                ->withErrors(['targets' => 'Run migrations first to enable saved performance targets.']);
        }

        DB::transaction(function () use ($validated, $year, $month) {
            foreach ((array) $validated['targets'] as $row) {
                DB::table('loan_performance_indicator_targets')->updateOrInsert(
                    [
                        'employee_id' => (int) $row['employee_id'],
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'new_target' => (float) $row['new_target'],
                        'repeat_target' => (float) $row['repeat_target'],
                        'arrears_target' => (float) $row['arrears_target'],
                        'performing_target' => (float) $row['performing_target'],
                        'gross_target' => (float) $row['gross_target'],
                        'revenue_target' => (float) $row['revenue_target'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });

        return redirect()
            ->route('loan.dashboard.performance_targets', ['month' => $monthDate->format('Y-m')])
            ->with('status', 'Performance targets saved.');
    }

    private function buildPerformanceIndicators(): Collection
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $scopedLoanIds = $this->loanQuery()->select('id');

        $newLoans = DB::table('loan_book_loans as l')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->whereBetween('l.created_at', [$monthStart, $monthEnd])
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COUNT(*) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $repeatLoans = DB::table('loan_book_loans as l')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->whereBetween('l.created_at', [$monthStart, $monthEnd])
            ->whereNotNull('c.assigned_employee_id')
            ->whereExists(function ($query) use ($monthStart) {
                $query->selectRaw('1')
                    ->from('loan_book_loans as old')
                    ->whereColumn('old.loan_client_id', 'l.loan_client_id')
                    ->where('old.created_at', '<', $monthStart);
            })
            ->selectRaw('c.assigned_employee_id as employee_id, COUNT(*) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $arrears = DB::table('loan_book_loans as l')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->whereIn('l.status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->where('l.dpd', '>', 0)
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COALESCE(SUM(l.balance), 0) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $performing = DB::table('loan_book_loans as l')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->where('l.status', LoanBookLoan::STATUS_ACTIVE)
            ->where('l.dpd', '<=', 5)
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COUNT(*) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $grossDisbursement = DB::table('loan_book_disbursements as d')
            ->join('loan_book_loans as l', 'l.id', '=', 'd.loan_book_loan_id')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->whereBetween('d.disbursed_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COALESCE(SUM(d.amount), 0) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $revenue = DB::table('loan_book_payments as p')
            ->join('loan_book_loans as l', 'l.id', '=', 'p.loan_book_loan_id')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereIn('l.id', $scopedLoanIds)
            ->where('p.status', LoanBookPayment::STATUS_PROCESSED)
            ->whereBetween('p.transaction_at', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COALESCE(SUM(p.amount), 0) as total')
            ->groupBy('c.assigned_employee_id')
            ->pluck('total', 'employee_id');

        $ids = collect()
            ->merge($newLoans->keys())
            ->merge($repeatLoans->keys())
            ->merge($arrears->keys())
            ->merge($performing->keys())
            ->merge($grossDisbursement->keys())
            ->merge($revenue->keys())
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $names = Employee::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Employee $e) => [(int) $e->id => trim((string) $e->full_name)]);

        $targetOverrides = Schema::hasTable('loan_performance_indicator_targets')
            ? DB::table('loan_performance_indicator_targets')
                ->where('year', (int) $monthStart->format('Y'))
                ->where('month', (int) $monthStart->format('m'))
                ->get()
                ->keyBy('employee_id')
            : collect();

        $rows = $ids->map(function (int $employeeId) use ($names, $newLoans, $repeatLoans, $arrears, $performing, $grossDisbursement, $revenue, $targetOverrides) {
            $new = (int) ($newLoans[$employeeId] ?? 0);
            $repeat = (int) ($repeatLoans[$employeeId] ?? 0);
            $arr = (float) ($arrears[$employeeId] ?? 0);
            $perf = (int) ($performing[$employeeId] ?? 0);
            $gross = (float) ($grossDisbursement[$employeeId] ?? 0);
            $rev = (float) ($revenue[$employeeId] ?? 0);
            $target = $targetOverrides->get($employeeId);
            $newTarget = (float) ($target->new_target ?? self::PERFORMANCE_DEFAULT_TARGETS['new_target']);
            $repeatTarget = (float) ($target->repeat_target ?? self::PERFORMANCE_DEFAULT_TARGETS['repeat_target']);
            $arrearsTarget = (float) ($target->arrears_target ?? self::PERFORMANCE_DEFAULT_TARGETS['arrears_target']);
            $performingTarget = (float) ($target->performing_target ?? self::PERFORMANCE_DEFAULT_TARGETS['performing_target']);
            $grossTarget = (float) ($target->gross_target ?? self::PERFORMANCE_DEFAULT_TARGETS['gross_target']);
            $revenueTarget = (float) ($target->revenue_target ?? self::PERFORMANCE_DEFAULT_TARGETS['revenue_target']);
            $arrearsBase = max(1.0, $arrearsTarget > 0 ? $arrearsTarget : 300000.0);

            return [
                'employee_id' => $employeeId,
                'staff_name' => (string) ($names[$employeeId] ?? 'Unassigned'),
                'new_target' => $newTarget,
                'new_actual' => $new,
                'new_score' => $new > 0 ? ($new / max(1.0, $newTarget)) * 100 : 0.0,
                'repeat_target' => $repeatTarget,
                'repeat_actual' => $repeat,
                'repeat_score' => $repeat > 0 ? ($repeat / max(1.0, $repeatTarget)) * 100 : 0.0,
                'arrears_target' => $arrearsTarget,
                'arrears_actual' => $arr,
                'arrears_score' => $arr > 0 ? -min(100, ($arr / $arrearsBase) * 100) : 0.0,
                'performing_target' => $performingTarget,
                'performing_actual' => $perf,
                'performing_score' => $perf > 0 ? ($perf / max(1.0, $performingTarget)) * 100 : 0.0,
                'gross_target' => $grossTarget,
                'gross_actual' => $gross,
                'gross_score' => $gross > 0 ? ($gross / max(1.0, $grossTarget)) * 100 : 0.0,
                'revenue_target' => $revenueTarget,
                'revenue_actual' => $rev,
                'revenue_score' => $rev > 0 ? ($rev / max(1.0, $revenueTarget)) * 100 : 0.0,
            ];
        })->values();

        $rankBy = function (Collection $source, string $key, bool $asc = false): array {
            $sorted = $asc
                ? $source->sortBy($key)->values()
                : $source->sortByDesc($key)->values();
            $ranks = [];
            foreach ($sorted as $idx => $row) {
                $ranks[(int) $row['employee_id']] = $idx + 1;
            }

            return $ranks;
        };

        $newPos = $rankBy($rows, 'new_actual');
        $repeatPos = $rankBy($rows, 'repeat_actual');
        $arrearsPos = $rankBy($rows, 'arrears_actual', true);
        $performingPos = $rankBy($rows, 'performing_actual');
        $grossPos = $rankBy($rows, 'gross_actual');
        $revenuePos = $rankBy($rows, 'revenue_actual');

        return $rows
            ->map(function (array $row) use ($newPos, $repeatPos, $arrearsPos, $performingPos, $grossPos, $revenuePos) {
                $id = (int) $row['employee_id'];
                $row['new_pos'] = (int) ($newPos[$id] ?? 0);
                $row['repeat_pos'] = (int) ($repeatPos[$id] ?? 0);
                $row['arrears_pos'] = (int) ($arrearsPos[$id] ?? 0);
                $row['performing_pos'] = (int) ($performingPos[$id] ?? 0);
                $row['gross_pos'] = (int) ($grossPos[$id] ?? 0);
                $row['revenue_pos'] = (int) ($revenuePos[$id] ?? 0);

                return $row;
            })
            ->sortBy('revenue_pos')
            ->values();
    }

    private function buildKpis(bool $book, bool $payments, bool $clients, bool $applications): array
    {
        $activeLoans = $book
            ? $this->loanQuery()->where('status', LoanBookLoan::STATUS_ACTIVE)->count()
            : 0;

        $createdThisMonth = $book
            ? $this->loanQuery()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count()
            : 0;
        $createdLastMonth = $book
            ? $this->loanQuery()->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count()
            : 0;
        $loanDelta = $createdThisMonth - $createdLastMonth;

        $portfolioStatuses = [
            LoanBookLoan::STATUS_ACTIVE,
            LoanBookLoan::STATUS_RESTRUCTURED,
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
        ];
        $outstanding = $book
            ? (float) $this->loanQuery()->whereIn('status', $portfolioStatuses)->sum('balance')
            : 0.0;

        $pipeline = $applications
            ? $this->applicationQuery()
                ->whereNotIn('stage', [LoanBookApplication::STAGE_DISBURSED, LoanBookApplication::STAGE_DECLINED])
                ->count()
            : 0;

        $creditReview = $applications
            ? $this->applicationQuery()->where('stage', LoanBookApplication::STAGE_CREDIT_REVIEW)->count()
            : 0;

        $mtdCollections = 0.0;
        if ($payments) {
            $mtdCollections = (float) LoanBookPayment::query()
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient'))
                ->processedQueue()
                ->whereBetween('transaction_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
        }

        $mtdDisbursements = 0.0;
        if ($book && Schema::hasTable('loan_book_disbursements')) {
            $mtdDisbursements = (float) LoanBookDisbursement::query()
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient'))
                ->whereBetween('disbursed_at', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->sum('amount');
        }

        $pendingDisbursementLoans = $book
            ? $this->loanQuery()
                ->where('status', LoanBookLoan::STATUS_PENDING_DISBURSEMENT)
                ->count()
            : 0;

        $unposted = $payments
            ? $this->paymentQuery()->unpostedQueue()->count()
            : 0;

        $nplCount = $book
            ? $this->loanQuery()
                ->where('status', LoanBookLoan::STATUS_ACTIVE)
                ->where('dpd', '>', 30)
                ->count()
            : 0;

        $clientsCount = $clients
            ? $this->clientQuery()->where('kind', LoanClient::KIND_CLIENT)->count()
            : 0;
        $leadsCount = $clients
            ? $this->clientQuery()->where('kind', LoanClient::KIND_LEAD)->count()
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
            ? (float) $this->loanQuery()
                ->where('dpd', '>', 0)
                ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
                ->sum('balance')
            : 0.0;

        $arrearsAccounts = $book
            ? $this->loanQuery()
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
            'mtd_disbursements' => $mtdDisbursements,
            'pending_disbursement_loans' => $pendingDisbursementLoans,
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
        return $this->loanQuery()
            ->with('loanClient')
            ->where('dpd', '>', 0)
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_RESTRUCTURED])
            ->orderByDesc('balance')
            ->limit(5)
            ->get();
    }

    private function recentApplications(): Collection
    {
        return $this->applicationQuery()
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
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient'))
                ->processedQueue()
                ->whereBetween('transaction_at', [$start, $end])
                ->sum('amount');

            $sheetTotals[] = $hasCollectionTable
                ? (float) LoanBookCollectionEntry::query()
                    ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient'))
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
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient'))
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
        $base = $this->loanQuery()->where('status', LoanBookLoan::STATUS_ACTIVE);

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
        $rows = $this->loanQuery()
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
        $rows = $this->applicationQuery()
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

    private function loanQuery(): Builder
    {
        $query = LoanBookLoan::query();
        $this->scopeByAssignedLoanClient($query, auth()->user());

        return $query;
    }

    private function applicationQuery(): Builder
    {
        $query = LoanBookApplication::query();
        $this->scopeByAssignedLoanClient($query, auth()->user());

        return $query;
    }

    private function paymentQuery(): Builder
    {
        $query = LoanBookPayment::query();
        $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient');

        return $query;
    }

    private function clientQuery(): Builder
    {
        $query = LoanClient::query();
        $this->scopeLoanClientsToUser($query, auth()->user());

        return $query;
    }
}
