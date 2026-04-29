<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\LoanBookApplication;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBranch;
use App\Models\LoanClient;
use App\Models\LoanProduct;
use App\Models\LoanRegion;
use App\Models\LoanSystemSetting;
use App\Services\BulkSmsService;
use App\Services\LoanBook\BorrowerClassificationService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanBookLoansController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function __construct(
        private readonly LoanBookLoanUpdateService $loanMath,
        private readonly BorrowerClassificationService $borrowerClassifier
    )
    {
    }

    public function index(Request $request)
    {
        $exportColumnMap = [
            'loanNo' => 'Loan #',
            'client' => 'Client',
            'contact' => 'Contact',
            'officer' => 'Loan officer',
            'disbursement' => 'Disbursement',
            'product' => 'Product',
            'loan' => 'Loan',
            'toPay' => 'To-pay',
            'paid' => 'Paid',
            'percent' => 'Percent',
            'balance' => 'Balance',
            'dpd' => 'DPD',
            'status' => 'Status',
            'maturity' => 'Maturity',
        ];

        $query = LoanBookLoan::query()
            ->with(['loanClient.assignedEmployee', 'application']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $branch = trim((string) $request->query('branch', ''));
        $repayment = trim((string) $request->query('repayment', ''));
        $nextStep = trim((string) $request->query('next_step', ''));
        $disbursedFrom = trim((string) $request->query('disbursed_from', ''));
        $disbursedTo = trim((string) $request->query('disbursed_to', ''));
        $maturityFrom = trim((string) $request->query('maturity_from', ''));
        $maturityTo = trim((string) $request->query('maturity_to', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 15)));

        $query
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('checkoff_employer', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereRaw("DATE_FORMAT(disbursed_at, '%d-%m-%Y') like ?", ['%'.$q.'%'])
                        ->orWhereRaw("DATE_FORMAT(maturity_date, '%d-%m-%Y') like ?", ['%'.$q.'%'])
                        ->orWhereRaw("DATE_FORMAT(maturity_date, '%Y-%m-%d') like ?", ['%'.$q.'%'])
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%')
                                ->orWhere('phone', 'like', '%'.$q.'%')
                                ->orWhereHas('assignedEmployee', function (Builder $employee) use ($q): void {
                                    $employee->where(function (Builder $employeeInner) use ($q): void {
                                        $employeeInner
                                            ->whereRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) like ?", ['%'.$q.'%'])
                                            ->orWhere('first_name', 'like', '%'.$q.'%')
                                            ->orWhere('last_name', 'like', '%'.$q.'%');
                                    });
                                });
                        });
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch))
            ->when($repayment === 'fully_paid', fn (Builder $builder) => $builder->where('balance', '<=', 0.01))
            ->when($repayment === 'has_balance', fn (Builder $builder) => $builder->where('balance', '>', 0.01))
            ->when($nextStep === 'disburse', fn (Builder $builder) => $builder->where('status', LoanBookLoan::STATUS_PENDING_DISBURSEMENT))
            ->when($nextStep === 'record_payment', fn (Builder $builder) => $builder
                ->where('status', LoanBookLoan::STATUS_ACTIVE)
                ->where('balance', '>', 0.01))
            ->when($nextStep === 'sync_schedule', fn (Builder $builder) => $builder->whereNotNull('loan_book_application_id'))
            ->when($nextStep === 'arrears', fn (Builder $builder) => $builder
                ->where('status', LoanBookLoan::STATUS_ACTIVE)
                ->where('dpd', '>', 0))
            ->when($disbursedFrom !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '>=', $disbursedFrom))
            ->when($disbursedTo !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '<=', $disbursedTo))
            ->when($maturityFrom !== '', fn (Builder $builder) => $builder->whereDate('maturity_date', '>=', $maturityFrom))
            ->when($maturityTo !== '', fn (Builder $builder) => $builder->whereDate('maturity_date', '<=', $maturityTo));

        $portfolioRollup = (clone $query)
            ->selectRaw('
                COALESCE(SUM(principal), 0) as gross_loan_portfolio,
                COALESCE(SUM(balance), 0) as outstanding_balance,
                COALESCE(SUM(interest_outstanding), 0) as interest_outstanding,
                COALESCE(SUM(fees_outstanding), 0) as fees_outstanding,
                COALESCE(SUM(CASE WHEN status = ? THEN balance ELSE 0 END), 0) as active_balance,
                COALESCE(SUM(CASE WHEN status = ? AND dpd > 30 THEN balance ELSE 0 END), 0) as par30_balance,
                COALESCE(SUM(CASE WHEN status = ? THEN principal ELSE 0 END), 0) as written_off_amount
            ', [
                LoanBookLoan::STATUS_ACTIVE,
                LoanBookLoan::STATUS_ACTIVE,
                LoanBookLoan::STATUS_WRITTEN_OFF,
            ])
            ->first();

        $grossLoanPortfolio = max(0.0, (float) ($portfolioRollup->gross_loan_portfolio ?? 0));
        $outstandingBalance = max(0.0, (float) ($portfolioRollup->outstanding_balance ?? 0));
        $interestOutstanding = max(0.0, (float) ($portfolioRollup->interest_outstanding ?? 0));
        $feesOutstanding = max(0.0, (float) ($portfolioRollup->fees_outstanding ?? 0));
        $activeBalance = max(0.0, (float) ($portfolioRollup->active_balance ?? 0));
        $par30Balance = max(0.0, (float) ($portfolioRollup->par30_balance ?? 0));
        $writtenOffAmount = max(0.0, (float) ($portfolioRollup->written_off_amount ?? 0));

        $provisionAmount = $interestOutstanding + $feesOutstanding;
        $netLoanPortfolio = max(0.0, $grossLoanPortfolio - $provisionAmount);
        $nplRatio = $activeBalance > 0 ? ($par30Balance / $activeBalance) * 100 : 0.0;
        $provisionCoverage = $par30Balance > 0 ? min(999.9, ($provisionAmount / $par30Balance) * 100) : 0.0;
        $par30Ratio = $activeBalance > 0 ? ($par30Balance / $activeBalance) * 100 : 0.0;
        $writeOffRatio = $grossLoanPortfolio > 0 ? ($writtenOffAmount / $grossLoanPortfolio) * 100 : 0.0;

        $agingRollup = (clone $query)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN dpd BETWEEN 0 AND 30 THEN balance ELSE 0 END), 0) as bucket_0_30,
                COALESCE(SUM(CASE WHEN dpd BETWEEN 31 AND 60 THEN balance ELSE 0 END), 0) as bucket_31_60,
                COALESCE(SUM(CASE WHEN dpd BETWEEN 61 AND 90 THEN balance ELSE 0 END), 0) as bucket_61_90,
                COALESCE(SUM(CASE WHEN dpd BETWEEN 91 AND 180 THEN balance ELSE 0 END), 0) as bucket_91_180,
                COALESCE(SUM(CASE WHEN dpd > 180 THEN balance ELSE 0 END), 0) as bucket_180_plus
            ')
            ->first();
        $agingTotal = max(0.01, $outstandingBalance);
        $agingRows = [
            ['label' => '0-30 days', 'amount' => max(0.0, (float) ($agingRollup->bucket_0_30 ?? 0))],
            ['label' => '31-60 days', 'amount' => max(0.0, (float) ($agingRollup->bucket_31_60 ?? 0))],
            ['label' => '61-90 days', 'amount' => max(0.0, (float) ($agingRollup->bucket_61_90 ?? 0))],
            ['label' => '91-180 days', 'amount' => max(0.0, (float) ($agingRollup->bucket_91_180 ?? 0))],
            ['label' => '180+ days', 'amount' => max(0.0, (float) ($agingRollup->bucket_180_plus ?? 0))],
        ];
        $agingRows = array_map(
            fn (array $row): array => $row + ['pct' => ($row['amount'] / $agingTotal) * 100],
            $agingRows
        );

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $requestedCols = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $request->query('cols', ''))
            )));
            $selectedCols = $requestedCols !== []
                ? array_values(array_intersect(array_keys($exportColumnMap), $requestedCols))
                : array_keys($exportColumnMap);
            if ($selectedCols === []) {
                $selectedCols = array_keys($exportColumnMap);
            }

            $rows = (clone $query)
                ->withSum('processedRepayments', 'amount')
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get();

            return TabularExport::stream(
                'loanbook-loans-'.now()->format('Ymd_His'),
                array_map(fn (string $key): string => $exportColumnMap[$key], $selectedCols),
                function () use ($rows, $selectedCols) {
                    $loanTotal = 0.0;
                    $toPayTotal = 0.0;
                    $paidTotal = 0.0;
                    $balanceTotal = 0.0;
                    foreach ($rows as $loan) {
                        $paid = (float) ($loan->processed_repayments_sum_amount ?? 0);
                        $remaining = max(0, (float) $loan->balance);
                        $toPay = $paid + $remaining;
                        $percent = $toPay > 0.00001 ? min(100, max(0, ($paid / $toPay) * 100)) : 0;
                        $officerName = trim((string) ($loan->loanClient?->assignedEmployee?->full_name ?? ''));

                        $loanTotal += (float) $loan->principal;
                        $toPayTotal += $toPay;
                        $paidTotal += $paid;
                        $balanceTotal += $remaining;

                        $rowByKey = [
                            'loanNo' => (string) $loan->loan_number,
                            'client' => (string) ($loan->loanClient?->full_name ?? ''),
                            'contact' => (string) ($loan->loanClient?->phone ?? ''),
                            'officer' => $officerName,
                            'disbursement' => (string) (optional($loan->disbursed_at)->format('d-m-Y') ?? ''),
                            'product' => (string) $loan->product_name,
                            'loan' => number_format((float) $loan->principal, 2, '.', ''),
                            'toPay' => number_format($toPay, 2, '.', ''),
                            'paid' => number_format($paid, 2, '.', ''),
                            'percent' => number_format($percent, 1, '.', '').'%',
                            'balance' => number_format($remaining, 2, '.', ''),
                            'dpd' => (string) $loan->dpd,
                            'status' => (string) $loan->status,
                            'maturity' => (string) (optional($loan->maturity_date)->format('d-m-Y') ?? ''),
                        ];

                        yield array_map(fn (string $key): string => (string) ($rowByKey[$key] ?? ''), $selectedCols);
                    }

                    $totalsByKey = [
                        'loanNo' => 'TOTAL',
                        'loan' => number_format($loanTotal, 2, '.', ''),
                        'toPay' => number_format($toPayTotal, 2, '.', ''),
                        'paid' => number_format($paidTotal, 2, '.', ''),
                        'balance' => number_format($balanceTotal, 2, '.', ''),
                    ];
                    yield array_map(fn (string $key): string => (string) ($totalsByKey[$key] ?? ''), $selectedCols);
                },
                $export
            );
        }

        $loans = (clone $query)
            ->withSum('processedRepayments', 'amount')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
        $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.book.loans.index', [
            'title' => 'View loans',
            'subtitle' => 'Active and closed facilities in LoanBook.',
            'loans' => $loans,
            'q' => $q,
            'status' => $status,
            'branch' => $branch,
            'repayment' => $repayment,
            'nextStep' => $nextStep,
            'disbursedFrom' => $disbursedFrom,
            'disbursedTo' => $disbursedTo,
            'maturityFrom' => $maturityFrom,
            'maturityTo' => $maturityTo,
            'perPage' => $perPage,
            'statuses' => $this->statusOptions(),
            'branches' => $branches,
            'portfolioIndicators' => [
                'netLoanPortfolio' => $netLoanPortfolio,
                'grossLoanPortfolio' => $grossLoanPortfolio,
                'provisionAmount' => $provisionAmount,
                'outstandingBalance' => $outstandingBalance,
                'nplRatio' => $nplRatio,
                'provisionCoverage' => $provisionCoverage,
                'par30Ratio' => $par30Ratio,
                'writeOffRatio' => $writeOffRatio,
                'agingRows' => $agingRows,
            ],
        ]);
    }

    public function arrears(Request $request)
    {
        try {
            $query = LoanBookLoan::query()
                ->with(['loanClient.assignedEmployee', 'loanBranch.region'])
                ->withSum('processedRepayments', 'amount');
            $this->scopeByAssignedLoanClient($query, auth()->user());
            $q = trim((string) $request->query('q', ''));
            $branch = trim((string) $request->query('branch', ''));
            $region = trim((string) $request->query('region', ''));
            $officer = trim((string) $request->query('officer', ''));
            $product = trim((string) $request->query('product', ''));
            $status = trim((string) $request->query('status', LoanBookLoan::STATUS_ACTIVE));
            $dpdMin = max(1, (int) $request->query('dpd_min', 1));
            $dpdMaxRaw = trim((string) $request->query('dpd_max', ''));
            $dpdMax = $dpdMaxRaw !== '' ? max($dpdMin, (int) $dpdMaxRaw) : null;
            $from = trim((string) $request->query('from', ''));
            $to = trim((string) $request->query('to', ''));
            $perPage = min(200, max(10, (int) $request->query('per_page', 20)));
            $query = $query
                ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
                ->where('dpd', '>=', $dpdMin)
                ->when($dpdMax !== null, fn (Builder $builder) => $builder->where('dpd', '<=', $dpdMax))
                ->when($q !== '', function (Builder $builder) use ($q): void {
                    $builder->where(function (Builder $inner) use ($q): void {
                        $inner->where('loan_number', 'like', '%'.$q.'%')
                            ->orWhere('product_name', 'like', '%'.$q.'%')
                            ->orWhere('branch', 'like', '%'.$q.'%')
                            ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                                $client->where('client_number', 'like', '%'.$q.'%')
                                    ->orWhere('first_name', 'like', '%'.$q.'%')
                                    ->orWhere('last_name', 'like', '%'.$q.'%');
                            });
                    });
                })
                ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch))
                ->when($region !== '', fn (Builder $builder) => $builder->whereHas('loanBranch.region', fn (Builder $regionQuery) => $regionQuery->where('name', $region)))
                ->when($officer !== '', fn (Builder $builder) => $builder->whereHas('loanClient.assignedEmployee', function (Builder $employee) use ($officer): void {
                    $employee->whereRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) = ?", [$officer]);
                }))
                ->when($product !== '', fn (Builder $builder) => $builder->where('product_name', $product))
                ->when($from !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '>=', $from))
                ->when($to !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '<=', $to));

            $export = strtolower((string) $request->query('export', ''));
            if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
                $rows = (clone $query)->orderByDesc('dpd')->limit(5000)->get();

                return TabularExport::stream(
                    'loanbook-arrears-'.now()->format('Ymd_His'),
                    ['Client', 'Contact', 'Branch', 'Loan Officer', 'Loan', 'Disbursement', 'Cycles', 'P.Arrears', 'Accumulated', 'Installment', 'Fall Date', 'Days', 'T.Bal'],
                    function () use ($rows) {
                    $loanTotal = 0.0;
                    $periodicArrearsTotal = 0.0;
                    $paidTotal = 0.0;
                    $balanceTotal = 0.0;
                        foreach ($rows as $loan) {
                            $termValue = max(1, (int) ($loan->term_value ?? 1));
                            $paid = (float) ($loan->processed_repayments_sum_amount ?? 0);
                            $totalBalance = (float) ($loan->balance ?? 0);
                            $periodicArrears = $termValue > 0 ? max(0, $totalBalance / $termValue) : $totalBalance;
                            $loanAmount = (float) ($loan->principal ?? 0);
                        $loanTotal += $loanAmount;
                        $periodicArrearsTotal += $periodicArrears;
                        $paidTotal += $paid;
                        $balanceTotal += $totalBalance;
                            $totalRepayable = max(0.01, $loanAmount + max(0, $totalBalance));
                            $installmentNo = min($termValue, max(1, (int) floor(($paid / $totalRepayable) * $termValue) + 1));
                            $fallDate = $loan->disbursed_at ? $loan->disbursed_at->copy()->addDays((int) ($loan->dpd ?? 0)) : null;
                            yield [
                                (string) ($loan->loanClient?->full_name ?? ''),
                                (string) ($loan->loanClient?->phone ?? ''),
                                (string) ($loan->branch ?? ''),
                                (string) ($loan->loanClient?->assignedEmployee?->full_name ?? ''),
                                number_format($loanAmount, 2, '.', ''),
                                (string) optional($loan->disbursed_at)->format('d-m-Y'),
                                (string) $termValue,
                                number_format($periodicArrears, 2, '.', ''),
                                number_format($paid, 2, '.', ''),
                                $installmentNo.'/'.$termValue,
                                (string) optional($fallDate)->format('d-m-Y'),
                                (string) $loan->dpd,
                                number_format($totalBalance, 2, '.', ''),
                            ];
                        }
                    yield ['TOTAL', '', '', '', number_format($loanTotal, 2, '.', ''), '', '', number_format($periodicArrearsTotal, 2, '.', ''), number_format($paidTotal, 2, '.', ''), '', '', '', number_format($balanceTotal, 2, '.', '')];
                    },
                    $export
                );
            }

            $loans = $query->orderByDesc('dpd')->paginate($perPage)->withQueryString();
            $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');
            $regions = \App\Models\LoanRegion::query()->orderBy('name')->pluck('name');
            $officers = \App\Models\Employee::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (\App\Models\Employee $employee): string => trim((string) $employee->full_name))
                ->filter()
                ->values();
            $products = LoanBookLoan::query()->whereNotNull('product_name')->where('product_name', '!=', '')->distinct()->orderBy('product_name')->pluck('product_name');
            $sampleRecipients = (clone $query)
                ->whereHas('loanClient', fn (Builder $clientQuery) => $clientQuery->whereNotNull('phone')->where('phone', '!=', ''))
                ->limit(500)
                ->get()
                ->map(function (LoanBookLoan $loan): ?array {
                    $client = $loan->loanClient;
                    $phone = trim((string) ($client?->phone ?? ''));
                    if ($phone === '') {
                        return null;
                    }

                    return [
                        'name' => (string) ($client?->full_name ?? 'Client'),
                        'phone' => $phone,
                    ];
                })
                ->filter()
                ->unique(fn (array $row) => $row['phone'])
                ->values();

            return view('loan.book.loans.arrears', [
                'title' => 'Loan arrears',
                'subtitle' => 'Active accounts with days past due.',
                'loans' => $loans,
                'q' => $q,
                'branch' => $branch,
                'region' => $region,
                'officer' => $officer,
                'product' => $product,
                'status' => $status,
                'dpdMin' => $dpdMin,
                'dpdMax' => $dpdMaxRaw,
                'from' => $from,
                'to' => $to,
                'perPage' => $perPage,
                'branches' => $branches,
                'regions' => $regions,
                'officers' => $officers,
                'products' => $products,
                'statuses' => $this->statusOptions(),
                'sampleRecipients' => $sampleRecipients,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('loan.book.loans.index')
                ->withErrors([
                    'arrears' => 'Could not load Loan Arrears right now. Please try again shortly.',
                ]);
        }
    }

    public function arrearsSendSms(Request $request, BulkSmsService $bulkSms): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'arrears_pick' => ['nullable', 'in:period,accumulated,total'],
            'sample_to' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'officer' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'dpd_min' => ['nullable', 'integer', 'min:1'],
            'dpd_max' => ['nullable', 'integer', 'min:1'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $branch = trim((string) ($validated['branch'] ?? ''));
        $region = trim((string) ($validated['region'] ?? ''));
        $officer = trim((string) ($validated['officer'] ?? ''));
        $product = trim((string) ($validated['product'] ?? ''));
        $status = trim((string) ($validated['status'] ?? LoanBookLoan::STATUS_ACTIVE));
        $dpdMin = max(1, (int) ($validated['dpd_min'] ?? 1));
        $dpdMax = isset($validated['dpd_max']) ? max($dpdMin, (int) $validated['dpd_max']) : null;
        $from = trim((string) ($validated['from'] ?? ''));
        $to = trim((string) ($validated['to'] ?? ''));
        $arrearsPick = (string) ($validated['arrears_pick'] ?? 'period');
        $sampleToRaw = trim((string) ($validated['sample_to'] ?? ''));

        $query = LoanBookLoan::query()
            ->with(['loanClient.assignedEmployee', 'loanBranch.region'])
            ->withSum('processedRepayments', 'amount');
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $query
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->where('dpd', '>=', $dpdMin)
            ->when($dpdMax !== null, fn (Builder $builder) => $builder->where('dpd', '<=', $dpdMax))
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('product_name', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch))
            ->when($region !== '', fn (Builder $builder) => $builder->whereHas('loanBranch.region', fn (Builder $regionQuery) => $regionQuery->where('name', $region)))
            ->when($officer !== '', fn (Builder $builder) => $builder->whereHas('loanClient.assignedEmployee', function (Builder $employee) use ($officer): void {
                $employee->whereRaw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) = ?", [$officer]);
            }))
            ->when($product !== '', fn (Builder $builder) => $builder->where('product_name', $product))
            ->when($from !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '>=', $from))
            ->when($to !== '', fn (Builder $builder) => $builder->whereDate('disbursed_at', '<=', $to));

        $loans = $query->orderByDesc('dpd')->limit(3000)->get();

        $sent = 0;
        $charged = 0.0;
        $failed = 0;
        $template = (string) $validated['message'];
        $userId = $request->user()?->id;
        $samplePhone = null;
        if ($sampleToRaw !== '') {
            $normalized = $bulkSms->normalizeRecipientList($sampleToRaw);
            $samplePhone = $normalized[0] ?? null;
            if ($samplePhone === null) {
                return redirect()
                    ->route('loan.book.loan_arrears', $request->only(['q', 'branch', 'region', 'officer', 'product', 'status', 'from', 'to', 'dpd_min', 'dpd_max']))
                    ->withErrors(['sms' => 'Selected sample phone is invalid.']);
            }
        }

        foreach ($loans as $loan) {
            $rawPhone = (string) ($loan->loanClient?->phone ?? '');
            $phones = $bulkSms->normalizeRecipientList($rawPhone);
            if ($phones === []) {
                continue;
            }
            if ($samplePhone !== null && $phones[0] !== $samplePhone) {
                continue;
            }

            $termValue = max(1, (int) ($loan->term_value ?? 1));
            $paid = (float) ($loan->processed_repayments_sum_amount ?? 0);
            $totalBalance = (float) ($loan->balance ?? 0);
            $periodicArrears = $termValue > 0 ? max(0, $totalBalance / $termValue) : $totalBalance;
            $fallDate = $loan->disbursed_at ? $loan->disbursed_at->copy()->addDays((int) ($loan->dpd ?? 0)) : null;
            $idNo = (string) ($loan->loanClient?->id_number ?? $loan->loanClient?->client_number ?? $loan->loan_number);

            $arrearsValue = match ($arrearsPick) {
                'accumulated' => $paid,
                'total' => $totalBalance,
                default => $periodicArrears,
            };

            $message = str_replace(
                ['CLIENT', 'IDNO', 'ARREARS', 'STARTDAY'],
                [
                    (string) ($loan->loanClient?->full_name ?? 'Client'),
                    $idNo,
                    number_format($arrearsValue, 2),
                    (string) optional($fallDate)->format('d-m-Y'),
                ],
                $template
            );

            $result = $bulkSms->sendNow($message, [$phones[0]], $userId, null);
            if (! ($result['ok'] ?? false)) {
                $failed++;
                continue;
            }

            $sent += (int) ($result['sent'] ?? 1);
            $charged += (float) ($result['charged'] ?? 0);
        }

        if ($sent === 0) {
            return redirect()
                ->route('loan.book.loan_arrears', $request->only(['q', 'branch', 'region', 'officer', 'product', 'status', 'from', 'to', 'dpd_min', 'dpd_max']))
                ->withErrors(['sms' => 'No SMS were sent. Check recipient phone numbers and SMS wallet/provider setup.']);
        }

        $statusMessage = sprintf('Sent %d message(s). Charged %s %s.', $sent, number_format($charged, 2), $bulkSms->currency());
        if ($failed > 0) {
            $statusMessage .= ' Failed: '.$failed.'.';
        }

        return redirect()
            ->route('loan.book.loan_arrears', $request->only(['q', 'branch', 'region', 'officer', 'product', 'status', 'from', 'to', 'dpd_min', 'dpd_max']))
            ->with('status', $statusMessage);
    }

    public function checkoff(Request $request)
    {
        $query = LoanBookLoan::query()->with(['loanClient.assignedEmployee']);
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $branch = trim((string) $request->query('branch', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $query = $query
            ->where('is_checkoff', true)
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('loan_number', 'like', '%'.$q.'%')
                        ->orWhere('checkoff_employer', 'like', '%'.$q.'%')
                        ->orWhere('branch', 'like', '%'.$q.'%')
                        ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                            $client->where('client_number', 'like', '%'.$q.'%')
                                ->orWhere('first_name', 'like', '%'.$q.'%')
                                ->orWhere('last_name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($branch !== '', fn (Builder $builder) => $builder->where('branch', $branch));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('balance')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-checkoff-'.now()->format('Ymd_His'),
                ['Client', 'ID No', 'Checkoff', 'Loan Officer', 'Confirmed By', 'Date', 'Receipt'],
                function () use ($rows) {
                    $checkoffTotal = 0.0;
                    foreach ($rows as $loan) {
                        $checkoff = (float) $loan->balance;
                        $checkoffTotal += $checkoff;
                        yield [
                            (string) ($loan->loanClient->full_name ?? ''),
                            (string) ($loan->loanClient->id_number ?? ''),
                            number_format($checkoff, 2, '.', ''),
                            (string) ($loan->loanClient?->assignedEmployee?->full_name ?? ''),
                            'System',
                            (string) (optional($loan->disbursed_at)->format('d-m-Y H:i') ?? ''),
                            (string) $loan->loan_number,
                        ];
                    }
                    yield ['TOTAL', '', number_format($checkoffTotal, 2, '.', ''), '', '', '', ''];
                },
                $export
            );
        }

        $loans = $query->orderByDesc('balance')->paginate($perPage)->withQueryString();
        $branches = LoanBookLoan::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch');

        return view('loan.book.loans.checkoff', [
            'title' => 'Checkoff loans',
            'subtitle' => 'Salary-checkoff and employer-deduct facilities.',
            'loans' => $loans,
            'q' => $q,
            'status' => $status,
            'branch' => $branch,
            'perPage' => $perPage,
            'statuses' => $this->statusOptions(),
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): View
    {
        $clients = LoanClient::query()
            ->clients()
            ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $applications = LoanBookApplication::query()
            ->whereIn('stage', [LoanBookApplication::STAGE_APPROVED, LoanBookApplication::STAGE_DISBURSED])
            ->whereDoesntHave('loan')
            ->with('loanClient')
            ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user()))
            ->orderByDesc('created_at')
            ->get();

        $prefillApplicationId = null;
        $rawApp = $request->query('application');
        if ($rawApp !== null && $rawApp !== '') {
            $candidate = (int) $rawApp;
            if ($candidate > 0 && $applications->contains(fn (LoanBookApplication $a): bool => (int) $a->id === $candidate)) {
                $prefillApplicationId = $candidate;
            }
        }

        return view('loan.book.loans.create', [
            'title' => 'Create loan',
            'subtitle' => 'Book a new facility (manual or from an approved application).',
            'clients' => $clients,
            'applications' => $applications,
            'prefillApplicationId' => $prefillApplicationId,
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
            'productOptions' => $this->productOptions(),
            'regions' => LoanRegion::query()->where('is_active', true)->orderBy('name')->get(),
            'clientBranchById' => $clients
                ->pluck('branch', 'id')
                ->map(fn ($branch) => trim((string) $branch))
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedLoan($request);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $classification = $this->borrowerClassifier->classify($client, (float) ($validated['principal'] ?? 0));
        $decision = (array) ($classification['borrower_decision'] ?? []);
        if ((string) ($decision['borrower_category'] ?? '') === 'blocked') {
            return redirect()
                ->back()
                ->withErrors([
                    'loan_client_id' => 'Borrower is blocked: '.implode(', ', (array) ($decision['blocking_reasons'] ?? ['risk_policy'])),
                ])
                ->withInput();
        }

        $validated['borrower_category'] = (string) ($decision['borrower_category'] ?? 'repeat_normal');
        $validated['client_loan_sequence'] = (int) ($decision['client_loan_sequence'] ?? 1);
        $validated['suggested_limit'] = (float) ($decision['suggested_max_limit'] ?? 0);
        $validated['risk_flags_json'] = (array) ($decision['risk_flags'] ?? []);
        $validated['classification_reason_json'] = [
            'blocking_reasons' => (array) ($decision['blocking_reasons'] ?? []),
            'warnings' => (array) ($decision['warnings'] ?? []),
            'approval_level_required' => (string) ($decision['approval_level_required'] ?? 'standard'),
            'graduation_allowed' => (bool) ($decision['graduation_allowed'] ?? false),
            'capacity' => (array) ($classification['client_capacity'] ?? []),
        ];
        $this->applyDirectoryBranch($validated);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }

        $next = (LoanBookLoan::query()->max('id') ?? 0) + 1;
        $validated['loan_number'] = 'LN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $this->initializeRepaymentBuckets($validated);
        if (($validated['status'] ?? null) === LoanBookLoan::STATUS_CLOSED && (float) ($validated['balance'] ?? 0) > 0.01) {
            return redirect()
                ->back()
                ->withErrors(['status' => 'Cannot set status to Closed while remaining balance is greater than zero.'])
                ->withInput();
        }

        $loan = LoanBookLoan::query()->create($validated);

        if ($loan->loan_book_application_id) {
            LoanBookApplication::query()
                ->whereKey($loan->loan_book_application_id)
                ->update(['stage' => LoanBookApplication::STAGE_APPROVED]);
        }

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan booked.'));
    }

    public function edit(LoanBookLoan $loan_book_loan): View
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);
        $clients = LoanClient::query()
            ->clients()
            ->when(! $this->canAccessAllLoanData(auth()->user()), fn (Builder $query) => $query->where('assigned_employee_id', $this->resolveLoanEmployeeId(auth()->user())))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loan.book.loans.edit', [
            'title' => 'Edit loan',
            'subtitle' => $loan_book_loan->loan_number,
            'loan' => $loan_book_loan,
            'clients' => $clients,
            'applications' => LoanBookApplication::query()
                ->with('loanClient')
                ->tap(fn (Builder $query) => $this->scopeByAssignedLoanClient($query, auth()->user()))
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
            'productOptions' => $this->productOptions($loan_book_loan->product_name),
            'regions' => LoanRegion::query()->where('is_active', true)->orderBy('name')->get(),
            'clientBranchById' => $clients
                ->pluck('branch', 'id')
                ->map(fn ($branch) => trim((string) $branch))
                ->all(),
        ]);
    }

    public function quickBranchStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'loan_region_id' => ['required', 'exists:loan_regions,id'],
            'code' => ['nullable', 'string', 'max:40', 'unique:loan_branches,code'],
        ]);

        $branch = LoanBranch::query()->create([
            'loan_region_id' => (int) $validated['loan_region_id'],
            'name' => trim((string) $validated['name']),
            'code' => filled($validated['code'] ?? null) ? trim((string) $validated['code']) : null,
            'is_active' => true,
        ]);
        $branch->load('region');

        return response()->json([
            'ok' => true,
            'branch' => [
                'id' => (int) $branch->id,
                'name' => (string) $branch->name,
                'region_name' => (string) ($branch->region?->name ?? ''),
            ],
        ]);
    }

    public function show(LoanBookLoan $loan_book_loan): View
    {
        $loan_book_loan->load([
            'loanClient',
            'application',
            'disbursements' => fn ($query) => $query->orderByDesc('disbursed_at')->orderByDesc('id'),
            'payments' => fn ($query) => $query->processedQueue()->orderByDesc('transaction_at')->orderByDesc('id')->limit(50),
        ]);
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        $recentCollections = LoanBookCollectionEntry::query()
            ->whereHas('loan', function (Builder $query) use ($loan_book_loan): void {
                $query->where('loan_client_id', $loan_book_loan->loan_client_id);
            })
            ->orderByDesc('collected_on')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('loan.book.loans.show', [
            'title' => 'Loan details',
            'subtitle' => $loan_book_loan->loan_number.' · '.($loan_book_loan->loanClient?->full_name ?? 'Unknown client'),
            'loan' => $loan_book_loan,
            'recentCollections' => $recentCollections,
        ]);
    }

    public function rebuildSnapshot(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $loan_book_loan->load('loanClient');
        $this->ensureLoanClientOwner($loan_book_loan->loanClient, $request->user());

        DB::transaction(function () use ($loan_book_loan, $request): void {
            /** @var LoanBookLoan $loan */
            $loan = LoanBookLoan::query()->lockForUpdate()->findOrFail($loan_book_loan->id);

            $disbursedPrincipal = (float) $loan->disbursements()->sum('amount');
            if ($disbursedPrincipal <= 0.0) {
                $disbursedPrincipal = max(0.0, (float) $loan->principal);
            }

            $principalOutstanding = $disbursedPrincipal;
            $interestOutstanding = $this->estimateInterestOutstanding(
                $loan,
                $disbursedPrincipal,
                max(0.0, (float) $loan->interest_rate)
            );
            $feesOutstanding = max(0.0, (float) $loan->fees_outstanding);

            $payments = LoanBookPayment::query()
                ->where('loan_book_loan_id', $loan->id)
                ->processedQueue()
                ->orderBy('transaction_at')
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $delta = abs((float) $payment->amount);
                if ($delta <= 0.0) {
                    continue;
                }

                if ($payment->payment_kind === LoanBookPayment::KIND_C2B_REVERSAL) {
                    $principalOutstanding = round($principalOutstanding + $delta, 2);
                    continue;
                }

                $remaining = $delta;
                foreach ($this->repaymentOrder() as $bucket) {
                    if ($remaining <= 0.0) {
                        break;
                    }
                    if ($bucket === 'fees' || $bucket === 'penalty') {
                        $apply = min($remaining, max(0.0, $feesOutstanding));
                        $feesOutstanding = round($feesOutstanding - $apply, 2);
                        $remaining -= $apply;
                        continue;
                    }
                    if ($bucket === 'interest') {
                        $apply = min($remaining, max(0.0, $interestOutstanding));
                        $interestOutstanding = round($interestOutstanding - $apply, 2);
                        $remaining -= $apply;
                        continue;
                    }
                    if ($bucket === 'principal') {
                        $apply = min($remaining, max(0.0, $principalOutstanding));
                        $principalOutstanding = round($principalOutstanding - $apply, 2);
                        $remaining -= $apply;
                    }
                }
            }

            $balance = round(max(0.0, $principalOutstanding + $interestOutstanding + $feesOutstanding), 2);

            $audit = '[Snapshot rebuild '.now()->format('Y-m-d H:i').'] Recomputed from disbursements + processed payments by '
                .trim((string) ($request->user()?->name ?? 'System')).'.';
            $existingNotes = trim((string) ($loan->notes ?? ''));

            $loan->update([
                'principal' => $disbursedPrincipal,
                'principal_outstanding' => $principalOutstanding,
                'interest_outstanding' => $interestOutstanding,
                'fees_outstanding' => $feesOutstanding,
                'balance' => $balance,
                'status' => $balance <= 0.0
                    ? LoanBookLoan::STATUS_CLOSED
                    : (in_array($loan->status, [LoanBookLoan::STATUS_PENDING_DISBURSEMENT, LoanBookLoan::STATUS_CLOSED], true)
                        ? LoanBookLoan::STATUS_ACTIVE
                        : $loan->status),
                'notes' => $existingNotes !== '' ? $existingNotes."\n".$audit : $audit,
            ]);
        });

        return redirect()
            ->route('loan.book.loans.show', $loan_book_loan)
            ->with('status', 'Repayment snapshot rebuilt from disbursements and processed payments.');
    }

    public function syncScheduleFromApplication(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $loan_book_loan->load(['loanClient', 'application']);
        $this->ensureLoanClientOwner($loan_book_loan->loanClient, $request->user());

        if (! $loan_book_loan->application) {
            return redirect()
                ->route('loan.book.loans.show', $loan_book_loan)
                ->withErrors(['loan' => 'This loan has no linked application to sync from.']);
        }

        DB::transaction(function () use ($loan_book_loan, $request): void {
            /** @var LoanBookLoan $loan */
            $loan = LoanBookLoan::query()
                ->with('application')
                ->lockForUpdate()
                ->findOrFail($loan_book_loan->id);
            $app = $loan->application;
            if (! $app) {
                return;
            }

            $termValue = $app->term_value !== null ? (int) $app->term_value : (int) ($loan->term_value ?? 12);
            $termUnit = strtolower(trim((string) ($app->term_unit ?? $loan->term_unit ?? 'monthly')));
            $ratePeriod = strtolower(trim((string) ($app->interest_rate_period ?? $loan->interest_rate_period ?? 'annual')));

            $principalOutstanding = max(0.0, (float) $loan->principal_outstanding);
            if ($principalOutstanding <= 0.0) {
                $principalOutstanding = max(0.0, (float) $loan->principal);
            }

            $loan->term_value = $termValue;
            $loan->term_unit = in_array($termUnit, ['daily', 'weekly', 'monthly'], true) ? $termUnit : 'monthly';
            $loan->interest_rate_period = in_array($ratePeriod, ['daily', 'weekly', 'monthly', 'annual'], true) ? $ratePeriod : 'annual';

            $loan->interest_outstanding = $this->loanMath->estimateInterestForLoan(
                $loan,
                $principalOutstanding,
                max(0.0, (float) $loan->interest_rate)
            );
            $loan->balance = round(max(0.0, $principalOutstanding + (float) $loan->interest_outstanding + (float) $loan->fees_outstanding), 2);
            if ($loan->balance > 0.0 && $loan->status === LoanBookLoan::STATUS_CLOSED) {
                $loan->status = LoanBookLoan::STATUS_ACTIVE;
            }

            $audit = '[Schedule sync '.now()->format('Y-m-d H:i').'] Synced term/rate period from application '
                .$app->reference.' by '.trim((string) ($request->user()?->name ?? 'System')).'.';
            $existingNotes = trim((string) ($loan->notes ?? ''));
            $loan->notes = $existingNotes !== '' ? $existingNotes."\n".$audit : $audit;

            $loan->save();
        });

        return redirect()
            ->route('loan.book.loans.show', $loan_book_loan)
            ->with('status', 'Loan schedule synced from linked application and repayment snapshot refreshed.');
    }

    public function update(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        $validated = $this->validatedLoan($request, false);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        if (($validated['status'] ?? null) === LoanBookLoan::STATUS_CLOSED && (float) ($validated['balance'] ?? 0) > 0.01) {
            return redirect()
                ->back()
                ->withErrors(['status' => 'Cannot set status to Closed while remaining balance is greater than zero.'])
                ->withInput();
        }
        $loan_book_loan->update($validated);

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan updated.'));
    }

    public function destroy(LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $this->ensureLoanClientOwner($loan_book_loan->loanClient);

        if ($loan_book_loan->disbursements()->exists() || $loan_book_loan->collectionEntries()->exists()) {
            return redirect()
                ->route('loan.book.loans.index')
                ->with('error', __('Remove disbursements and collection lines before deleting this loan.'));
        }

        $loan_book_loan->delete();

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan removed.'));
    }

    private function validatedLoan(Request $request, bool $isCreate = true): array
    {
        $rules = [
            'loan_book_application_id' => ['nullable', 'exists:loan_book_applications,id'],
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'principal' => ['required', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_value' => ['nullable', 'integer', 'min:1', 'max:3660'],
            'term_unit' => ['nullable', 'string', 'in:daily,weekly,monthly'],
            'interest_rate_period' => ['nullable', 'string', 'in:daily,weekly,monthly,annual'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys($this->statusOptions()))],
            'dpd' => $isCreate
                ? ['nullable', 'integer', 'min:0', 'max:9999']
                : ['required', 'integer', 'min:0', 'max:9999'],
            'disbursed_at' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date'],
            'checkoff_employer' => ['nullable', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (Schema::hasTable('loan_branches')) {
            $rules['loan_branch_id'] = ['nullable', 'exists:loan_branches,id'];
        }

        $validated = $request->validate($rules);
        if (isset($validated['interest_rate_period'])) {
            $validated['interest_rate_period'] = strtolower((string) $validated['interest_rate_period']);
        }
        if (isset($validated['term_unit'])) {
            $validated['term_unit'] = strtolower((string) $validated['term_unit']);
        }

        if (! empty($validated['loan_book_application_id'])) {
            $app = LoanBookApplication::query()->find($validated['loan_book_application_id']);
            if ($app) {
                // When a loan is booked from an application, the schedule/rate-period
                // should follow the approved application values as source of truth.
                if ($app->term_value !== null) {
                    $validated['term_value'] = (int) $app->term_value;
                }
                if ($app->term_unit !== null) {
                    $validated['term_unit'] = strtolower((string) $app->term_unit);
                }
                if ($app->interest_rate_period !== null) {
                    $validated['interest_rate_period'] = strtolower((string) $app->interest_rate_period);
                }
            }
        }

        $validated['interest_rate_period'] = strtolower((string) ($validated['interest_rate_period'] ?? 'annual'));
        $validated['term_unit'] = strtolower((string) ($validated['term_unit'] ?? 'monthly'));
        if ($isCreate) {
            $validated['dpd'] = 0;
        }

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function productOptions(?string $currentProduct = null): array
    {
        $saved = Schema::hasTable('loan_products')
            ? \App\Models\LoanProduct::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all()
            : [];

        $historicLoans = LoanBookLoan::query()
            ->select('product_name')
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->distinct()
            ->pluck('product_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $historicApplications = LoanBookApplication::query()
            ->select('product_name')
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->distinct()
            ->pluck('product_name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $all = array_values(array_unique(array_merge($saved, $historicLoans, $historicApplications)));
        sort($all, SORT_NATURAL | SORT_FLAG_CASE);

        $current = trim((string) ($currentProduct ?? ''));
        if ($current !== '' && ! in_array($current, $all, true)) {
            $all[] = $current;
            sort($all, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $all;
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'Pending disbursement',
            LoanBookLoan::STATUS_ACTIVE => 'Active',
            LoanBookLoan::STATUS_CLOSED => 'Closed',
            LoanBookLoan::STATUS_WRITTEN_OFF => 'Written off',
            LoanBookLoan::STATUS_RESTRUCTURED => 'Restructured',
        ];
    }

    private function branchOptions(): Collection
    {
        if (! Schema::hasTable('loan_branches')) {
            return collect();
        }

        return LoanBranch::query()->with('region')->orderBy('name')->get();
    }

    private function applyDirectoryBranch(array &$validated): void
    {
        if (! Schema::hasTable('loan_branches')) {
            unset($validated['loan_branch_id']);

            return;
        }

        if (empty($validated['loan_branch_id'])) {
            $validated['loan_branch_id'] = null;

            return;
        }

        $branch = LoanBranch::query()->find($validated['loan_branch_id']);
        if ($branch) {
            $validated['branch'] = $branch->name;
        }
    }

    private function initializeRepaymentBuckets(array &$validated): void
    {
        $principal = max(0.0, (float) ($validated['principal'] ?? 0));
        $interestRate = max(0.0, (float) ($validated['interest_rate'] ?? 0));
        $application = null;
        if (! empty($validated['loan_book_application_id'])) {
            $application = LoanBookApplication::query()->find($validated['loan_book_application_id']);
        }
        $interestOutstanding = $this->loanMath->estimateInterestOutstanding(
            principal: $principal,
            ratePercent: $interestRate,
            ratePeriod: strtolower((string) ($application?->interest_rate_period ?? 'annual')),
            termValue: $application?->term_value !== null ? (int) $application->term_value : null,
            termUnit: $application?->term_unit !== null ? (string) $application->term_unit : null,
            disbursedAt: $validated['disbursed_at'] ?? null,
            maturityDate: $validated['maturity_date'] ?? null
        );

        if (empty($validated['principal_outstanding'])) {
            $validated['principal_outstanding'] = $principal;
        }
        if (empty($validated['interest_outstanding'])) {
            $validated['interest_outstanding'] = $interestOutstanding;
        }
        if (! array_key_exists('fees_outstanding', $validated)) {
            $validated['fees_outstanding'] = $this->initialProductChargesForLoan(
                productName: (string) ($validated['product_name'] ?? ''),
                principal: $principal,
                isCheckoff: (bool) ($validated['is_checkoff'] ?? false)
            );
        }
        if (empty($validated['balance'])) {
            $validated['balance'] = round(
                (float) $validated['principal_outstanding']
                + (float) $validated['interest_outstanding']
                + (float) $validated['fees_outstanding'],
                2
            );
        }
    }

    private function initialProductChargesForLoan(string $productName, float $principal, bool $isCheckoff): float
    {
        $name = trim($productName);
        if ($name === '' || ! Schema::hasTable('loan_product_charges')) {
            return 0.0;
        }

        $product = LoanProduct::query()
            ->where('name', $name)
            ->with(['charges' => fn ($q) => $q->where('is_active', true)->where('applies_to_stage', 'loan')])
            ->first();
        if (! $product) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($product->charges as $charge) {
            $scope = (string) ($charge->applies_to_client_scope ?? 'all');
            if ($scope === 'checkoff_only' && ! $isCheckoff) {
                continue;
            }
            if ($scope === 'non_checkoff' && $isCheckoff) {
                continue;
            }
            // "new_clients" and "existing_clients" are informational scopes for now.

            if ((string) $charge->amount_type === 'percent') {
                $total += $principal * ((float) $charge->amount / 100);
            } else {
                $total += (float) $charge->amount;
            }
        }

        return round(max(0.0, $total), 2);
    }

    private function estimateInterestOutstanding(LoanBookLoan $loan, float $principal, float $ratePercent): float
    {
        return $this->loanMath->estimateInterestForLoan($loan, $principal, $ratePercent);
    }

    /**
     * @return list<'principal'|'interest'|'fees'|'penalty'>
     */
    private function repaymentOrder(): array
    {
        $raw = (string) (LoanSystemSetting::getValue('loan_repayment_allocation_order', 'principal,interest,fees,penalty,overpayment') ?? '');
        $parts = array_values(array_filter(array_map(
            static fn (string $p) => strtolower(trim($p)),
            explode(',', $raw)
        )));
        $valid = ['principal', 'interest', 'fees', 'penalty'];
        $order = array_values(array_intersect($parts, $valid));
        foreach ($valid as $v) {
            if (! in_array($v, $order, true)) {
                $order[] = $v;
            }
        }

        return $order;
    }

}
