<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\AccountingBankReconciliation;
use App\Models\AccountingBudgetLine;
use App\Models\AccountingChartAccount;
use App\Models\AccountingCompanyAsset;
use App\Models\AccountingCompanyExpense;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingRule;
use App\Models\AccountingPayrollLine;
use App\Models\AccountingPayrollPeriod;
use App\Models\Employee;
use App\Support\CsvExport;
use App\Support\TabularExport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanAccountingBooksController extends Controller
{
    private function journalTotalsForAccounts(?Carbon $from, Carbon $to): Collection
    {
        $q = AccountingJournalLine::query()
            ->selectRaw('accounting_chart_account_id, COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
            ->whereHas('entry', function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()]);
                } else {
                    $q->where('entry_date', '<=', $to->toDateString());
                }
            })
            ->groupBy('accounting_chart_account_id');

        return $q->get()->keyBy('accounting_chart_account_id');
    }

    private function glNetForAccount(int $accountId, Carbon $asOf): float
    {
        $row = AccountingJournalLine::query()
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->where('accounting_chart_account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->where('entry_date', '<=', $asOf->toDateString()))
            ->first();

        return (float) ($row->net ?? 0);
    }

    /* ---------- Chart rules (static guidance + link to chart) ---------- */

    public function chartRules(): View
    {
        $hasAccountClass = Schema::hasColumn('accounting_chart_accounts', 'account_class');
        $accounts = AccountingChartAccount::query()
            ->with('parent')
            ->orderBy('account_type')
            ->orderBy('code')
            ->get();
        $headerAccounts = $hasAccountClass
            ? AccountingChartAccount::query()
                ->where('is_active', true)
                ->where('account_class', AccountingChartAccount::CLASS_HEADER)
                ->orderBy('code')
                ->get()
            : collect();

        $postingRules = AccountingPostingRule::query()
            ->with(['debitAccount', 'creditAccount'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $activeAccounts = $accounts->where('is_active', true)->count();
        $newAccounts30d = AccountingChartAccount::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $missingRules = $postingRules->filter(fn (AccountingPostingRule $r) => ! $r->debit_account_id || ! $r->credit_account_id)->count();
        $pendingApprovals = $postingRules->where('is_editable', true)->count();
        $isBalanced = abs((float) AccountingJournalLine::sum('debit') - (float) AccountingJournalLine::sum('credit')) < 0.01;
        $overdrawnAccounts = collect();
        if (Schema::hasColumn('accounting_chart_accounts', 'allow_overdraft')
            && Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
            $overdrawnAccounts = AccountingChartAccount::query()
                ->where('allow_overdraft', true)
                ->where('current_balance', '<', 0)
                ->orderBy('current_balance')
                ->get();
        }

        return view('loan.accounting.books.chart-rules', compact(
            'accounts',
            'headerAccounts',
            'postingRules',
            'activeAccounts',
            'newAccounts30d',
            'missingRules',
            'pendingApprovals',
            'isBalanced',
            'overdrawnAccounts'
        ));
    }

    /* ---------- Reports hub & financials ---------- */

    public function reportsHub(): View
    {
        return view('loan.accounting.books.reports-hub');
    }

    public function trialBalance(Request $request): View
    {
        $asOf = $request->date('as_of') ?: now();

        $totals = $this->journalTotalsForAccounts(null, $asOf);
        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();

        $rows = [];
        $sumDr = 0.0;
        $sumCr = 0.0;
        foreach ($accounts as $a) {
            $t = $totals->get($a->id);
            if (! $t || ((float) $t->total_debit <= 0 && (float) $t->total_credit <= 0)) {
                continue;
            }
            $dr = (float) $t->total_debit;
            $cr = (float) $t->total_credit;
            $tbDr = 0.0;
            $tbCr = 0.0;
            if (in_array($a->account_type, [AccountingChartAccount::TYPE_ASSET, AccountingChartAccount::TYPE_EXPENSE], true)) {
                $net = $dr - $cr;
                if ($net >= 0) {
                    $tbDr = $net;
                } else {
                    $tbCr = -$net;
                }
            } else {
                $netCr = $cr - $dr;
                if ($netCr >= 0) {
                    $tbCr = $netCr;
                } else {
                    $tbDr = -$netCr;
                }
            }
            if ($tbDr < 0.00001 && $tbCr < 0.00001) {
                continue;
            }
            $rows[] = ['account' => $a, 'debit' => $tbDr, 'credit' => $tbCr];
            $sumDr += $tbDr;
            $sumCr += $tbCr;
        }

        return view('loan.accounting.books.trial-balance', compact('asOf', 'rows', 'sumDr', 'sumCr'));
    }

    public function incomeStatement(Request $request): View
    {
        $from = $request->date('from') ?: now()->startOfYear();
        $to = $request->date('to') ?: now()->endOfMonth();

        $totals = $this->journalTotalsForAccounts($from, $to);

        $incomeTotal = 0.0;
        $expenseTotal = 0.0;
        $incomeRows = [];
        $expenseRows = [];

        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();
        foreach ($accounts as $a) {
            $t = $totals->get($a->id);
            if (! $t) {
                continue;
            }
            $dr = (float) $t->total_debit;
            $cr = (float) $t->total_credit;
            if ($a->account_type === AccountingChartAccount::TYPE_INCOME) {
                $amt = $cr - $dr;
                if (abs($amt) < 0.00001) {
                    continue;
                }
                $incomeRows[] = ['account' => $a, 'amount' => $amt];
                $incomeTotal += $amt;
            }
            if ($a->account_type === AccountingChartAccount::TYPE_EXPENSE) {
                $amt = $dr - $cr;
                if (abs($amt) < 0.00001) {
                    continue;
                }
                $expenseRows[] = ['account' => $a, 'amount' => $amt];
                $expenseTotal += $amt;
            }
        }

        $netIncome = $incomeTotal - $expenseTotal;

        return view('loan.accounting.books.income-statement', compact(
            'from', 'to', 'incomeRows', 'expenseRows', 'incomeTotal', 'expenseTotal', 'netIncome'
        ));
    }

    public function balanceSheet(Request $request): View
    {
        $asOf = $request->date('as_of') ?: now();

        $totals = $this->journalTotalsForAccounts(null, $asOf);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();
        foreach ($accounts as $a) {
            $t = $totals->get($a->id);
            if (! $t) {
                continue;
            }
            $dr = (float) $t->total_debit;
            $cr = (float) $t->total_credit;
            $net = $dr - $cr;
            if (abs($net) < 0.00001) {
                continue;
            }
            if ($a->account_type === AccountingChartAccount::TYPE_ASSET) {
                $assets[] = ['account' => $a, 'amount' => $net];
                $totalAssets += $net;
            }
            if ($a->account_type === AccountingChartAccount::TYPE_LIABILITY) {
                $liab = $cr - $dr;
                if (abs($liab) < 0.00001) {
                    continue;
                }
                $liabilities[] = ['account' => $a, 'amount' => $liab];
                $totalLiabilities += $liab;
            }
            if ($a->account_type === AccountingChartAccount::TYPE_EQUITY) {
                $eq = $cr - $dr;
                if (abs($eq) < 0.00001) {
                    continue;
                }
                $equity[] = ['account' => $a, 'amount' => $eq];
                $totalEquity += $eq;
            }
        }

        $yearStart = Carbon::parse($asOf->format('Y').'-01-01');
        $pAndL = $this->journalTotalsForAccounts($yearStart, $asOf);
        $netIncomeYtd = 0.0;
        foreach ($accounts as $a) {
            $t = $pAndL->get($a->id);
            if (! $t) {
                continue;
            }
            $dr = (float) $t->total_debit;
            $cr = (float) $t->total_credit;
            if ($a->account_type === AccountingChartAccount::TYPE_INCOME) {
                $netIncomeYtd += $cr - $dr;
            }
            if ($a->account_type === AccountingChartAccount::TYPE_EXPENSE) {
                $netIncomeYtd -= $dr - $cr;
            }
        }

        return view('loan.accounting.books.balance-sheet', compact(
            'asOf', 'assets', 'liabilities', 'equity',
            'totalAssets', 'totalLiabilities', 'totalEquity', 'netIncomeYtd'
        ));
    }

    /* ---------- Company expenses ---------- */

    public function companyExpensesIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $category = request()->string('category')->toString();
        $search = request()->string('q')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingCompanyExpense::query()
            ->with('recordedByUser')
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        if ($from !== '') {
            $q->where('expense_date', '>=', $from);
        }
        if ($to !== '') {
            $q->where('expense_date', '<=', $to);
        }
        if ($category !== '') {
            $q->where('category', $category);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'like', '%'.$search.'%')
                    ->orWhere('reference', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('company-expenses', [
                'Expense Date', 'Title', 'Category', 'Amount', 'Currency', 'Payment Method', 'Reference', 'Recorded By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingCompanyExpense $r) {
                    return [
                        optional($r->expense_date)->format('Y-m-d'),
                        (string) ($r->title ?? ''),
                        (string) ($r->category ?? ''),
                        (string) $r->amount,
                        (string) ($r->currency ?? ''),
                        (string) ($r->payment_method ?? ''),
                        (string) ($r->reference ?? ''),
                        (string) ($r->recordedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $rows = $q->paginate(20)->withQueryString();

        $categories = AccountingCompanyExpense::query()
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        return view('loan.accounting.books.company-expenses.index', compact('rows', 'from', 'to', 'category', 'search', 'categories'));
    }

    public function companyExpensesCreate(): View
    {
        return view('loan.accounting.books.company-expenses.create');
    }

    public function companyExpensesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        AccountingCompanyExpense::create([
            ...$validated,
            'currency' => $validated['currency'] ?? 'KES',
            'recorded_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.company_expenses.index')->with('status', 'Expense recorded.');
    }

    public function companyExpensesEdit(AccountingCompanyExpense $accounting_company_expense): View
    {
        return view('loan.accounting.books.company-expenses.edit', ['row' => $accounting_company_expense]);
    }

    public function companyExpensesUpdate(Request $request, AccountingCompanyExpense $accounting_company_expense): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:8'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $accounting_company_expense->update($validated);

        return redirect()->route('loan.accounting.company_expenses.index')->with('status', 'Expense updated.');
    }

    public function companyExpensesDestroy(AccountingCompanyExpense $accounting_company_expense): RedirectResponse
    {
        $accounting_company_expense->delete();

        return redirect()->route('loan.accounting.company_expenses.index')->with('status', 'Removed.');
    }

    /* ---------- Company assets ---------- */

    public function assetsIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $branch = request()->string('branch')->toString();
        $search = request()->string('q')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingCompanyAsset::query()
            ->orderByDesc('acquired_on')
            ->orderBy('name');

        if ($status !== '') {
            $q->where('status', $status);
        }
        if ($branch !== '') {
            $q->where('branch', $branch);
        }
        if ($from !== '') {
            $q->whereDate('acquired_on', '>=', $from);
        }
        if ($to !== '') {
            $q->whereDate('acquired_on', '<=', $to);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', '%'.$search.'%')
                    ->orWhere('asset_code', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%')
                    ->orWhere('notes', 'like', '%'.$search.'%');
            });
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('company-assets', [
                'Asset Code', 'Name', 'Category', 'Location', 'Branch', 'Acquired On', 'Cost', 'Net Book Value', 'Status', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingCompanyAsset $r) {
                    return [
                        (string) ($r->asset_code ?? ''),
                        (string) ($r->name ?? ''),
                        (string) ($r->category ?? ''),
                        (string) ($r->location ?? ''),
                        (string) ($r->branch ?? ''),
                        optional($r->acquired_on)->format('Y-m-d'),
                        (string) ($r->cost ?? ''),
                        (string) ($r->net_book_value ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $rows = $q->paginate(20)->withQueryString();

        $statuses = AccountingCompanyAsset::query()
            ->select('status')
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->values();

        $branches = AccountingCompanyAsset::query()
            ->select('branch')
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch')
            ->values();

        return view('loan.accounting.books.assets.index', compact('rows', 'status', 'branch', 'search', 'from', 'to', 'statuses', 'branches'));
    }

    public function assetsCreate(): View
    {
        return view('loan.accounting.books.assets.create');
    }

    public function assetsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:120'],
            'acquired_on' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'net_book_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        AccountingCompanyAsset::create($validated);

        return redirect()->route('loan.accounting.company_assets.index')->with('status', 'Asset registered.');
    }

    public function assetsEdit(AccountingCompanyAsset $accounting_company_asset): View
    {
        return view('loan.accounting.books.assets.edit', ['row' => $accounting_company_asset]);
    }

    public function assetsUpdate(Request $request, AccountingCompanyAsset $accounting_company_asset): RedirectResponse
    {
        $validated = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:120'],
            'acquired_on' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'net_book_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $accounting_company_asset->update($validated);

        return redirect()->route('loan.accounting.company_assets.index')->with('status', 'Asset updated.');
    }

    public function assetsDestroy(AccountingCompanyAsset $accounting_company_asset): RedirectResponse
    {
        $accounting_company_asset->delete();

        return redirect()->route('loan.accounting.company_assets.index')->with('status', 'Removed.');
    }

    /* ---------- Payroll ---------- */

    public function payrollHub(): View
    {
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();
        $currentLabel = $now->format('F Y').' Payroll';

        return view('loan.accounting.books.payroll.hub', [
            'currentPayrollCreateUrl' => route('loan.accounting.payroll.create', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'label' => $currentLabel,
            ]),
            'currentMonthTitle' => $now->format('F Y').' Payroll',
        ]);
    }

    public function payrollPayslipsIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $employeeId = request()->integer('employee_id') ?: null;
        $periodId = request()->integer('accounting_payroll_period_id') ?: null;
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingPayrollLine::query()
            ->with(['period', 'employee'])
            ->orderByDesc('id');

        if ($employeeId !== null) {
            $q->where('employee_id', $employeeId);
        }
        if ($periodId !== null) {
            $q->where('accounting_payroll_period_id', $periodId);
        }
        if ($from !== '') {
            $q->whereHas('period', fn ($qq) => $qq->whereDate('period_start', '>=', $from));
        }
        if ($to !== '') {
            $q->whereHas('period', fn ($qq) => $qq->whereDate('period_end', '<=', $to));
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('payslips', [
                'Employee', 'Employee No', 'Period Start', 'Period End', 'Label', 'Gross Pay', 'Deductions', 'Net Pay', 'Payslip No',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingPayrollLine $line) {
                    return [
                        (string) ($line->employee?->full_name ?? ''),
                        (string) ($line->employee?->employee_number ?? ''),
                        optional($line->period?->period_start)->format('Y-m-d'),
                        optional($line->period?->period_end)->format('Y-m-d'),
                        (string) ($line->period?->label ?? ''),
                        (string) ($line->gross_pay ?? ''),
                        (string) ($line->deductions ?? ''),
                        (string) ($line->net_pay ?? ''),
                        (string) ($line->payslip_number ?? ''),
                    ];
                });
            }, $export);
        }

        $lines = $q->paginate(25)->withQueryString();

        $employees = Employee::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'employee_number']);

        $periods = AccountingPayrollPeriod::query()
            ->orderByDesc('period_start')
            ->get(['id', 'period_start', 'period_end', 'label']);

        return view('loan.accounting.books.payroll.payslips-index', compact('lines', 'employeeId', 'periodId', 'from', 'to', 'employees', 'periods'));
    }

    public function payrollStatutorySettings(): View
    {
        return view('loan.accounting.books.payroll.settings-placeholder', [
            'title' => 'Statutory deductions',
            'intro' => 'Review and configure statutory deductions (e.g. NSSF, NHIF, PAYE).',
        ]);
    }

    public function payrollOtherDeductionsSettings(): View
    {
        return view('loan.accounting.books.payroll.settings-placeholder', [
            'title' => 'Other salary deductions',
            'intro' => 'Configure other salary deductions (e.g. welfare, loans, union fees).',
        ]);
    }

    public function payrollBonusesAllowancesSettings(): View
    {
        return view('loan.accounting.books.payroll.settings-placeholder', [
            'title' => 'Bonuses & allowances',
            'intro' => 'Configure additional payroll income (e.g. incentives, transport, bonuses).',
        ]);
    }

    public function payrollIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $export = request()->string('export')->toString();

        $q = AccountingPayrollPeriod::query()
            ->orderByDesc('period_start');

        if ($status !== '') {
            $q->where('status', $status);
        }
        if ($from !== '') {
            $q->whereDate('period_start', '>=', $from);
        }
        if ($to !== '') {
            $q->whereDate('period_end', '<=', $to);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('payroll-periods', [
                'Period Start', 'Period End', 'Label', 'Status', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingPayrollPeriod $p) {
                    return [
                        optional($p->period_start)->format('Y-m-d'),
                        optional($p->period_end)->format('Y-m-d'),
                        (string) ($p->label ?? ''),
                        (string) ($p->status ?? ''),
                        (string) ($p->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $periods = $q->paginate(15)->withQueryString();

        $statuses = AccountingPayrollPeriod::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->values();

        return view('loan.accounting.books.payroll.index', compact('periods', 'status', 'from', 'to', 'statuses'));
    }

    public function payrollCreate(): View
    {
        return view('loan.accounting.books.payroll.create');
    }

    public function payrollStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        AccountingPayrollPeriod::create([
            ...$validated,
            'status' => AccountingPayrollPeriod::STATUS_DRAFT,
        ]);

        return redirect()->route('loan.accounting.payroll.index')->with('status', 'Payroll period created.');
    }

    public function payrollShow(AccountingPayrollPeriod $accounting_payroll_period): View
    {
        $accounting_payroll_period->load(['lines.employee']);
        $employees = Employee::query()->orderBy('first_name')->orderBy('last_name')->get();

        return view('loan.accounting.books.payroll.show', compact('accounting_payroll_period', 'employees'));
    }

    public function payrollEdit(AccountingPayrollPeriod $accounting_payroll_period): View
    {
        return view('loan.accounting.books.payroll.edit', ['period' => $accounting_payroll_period]);
    }

    public function payrollUpdate(Request $request, AccountingPayrollPeriod $accounting_payroll_period): RedirectResponse
    {
        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'label' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:draft,processed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $accounting_payroll_period->update($validated);

        return redirect()->route('loan.accounting.payroll.show', $accounting_payroll_period)->with('status', 'Period updated.');
    }

    public function payrollDestroy(AccountingPayrollPeriod $accounting_payroll_period): RedirectResponse
    {
        $accounting_payroll_period->delete();

        return redirect()->route('loan.accounting.payroll.index')->with('status', 'Period deleted.');
    }

    public function payrollLineStore(Request $request, AccountingPayrollPeriod $accounting_payroll_period): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'gross_pay' => ['required', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'net_pay' => ['required', 'numeric', 'min:0'],
            'payslip_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $ded = (float) ($validated['deductions'] ?? 0);
        $validated['deductions'] = $ded;
        AccountingPayrollLine::create([
            ...$validated,
            'accounting_payroll_period_id' => $accounting_payroll_period->id,
        ]);

        return redirect()->route('loan.accounting.payroll.show', $accounting_payroll_period)->with('status', 'Payroll line added.');
    }

    public function payrollLineEdit(AccountingPayrollPeriod $accounting_payroll_period, AccountingPayrollLine $accounting_payroll_line): View
    {
        abort_unless($accounting_payroll_line->accounting_payroll_period_id === $accounting_payroll_period->id, 404);

        return view('loan.accounting.books.payroll.line-edit', [
            'period' => $accounting_payroll_period,
            'line' => $accounting_payroll_line,
        ]);
    }

    public function payrollLineUpdate(Request $request, AccountingPayrollPeriod $accounting_payroll_period, AccountingPayrollLine $line): RedirectResponse
    {
        abort_unless($line->accounting_payroll_period_id === $accounting_payroll_period->id, 404);
        $validated = $request->validate([
            'gross_pay' => ['required', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'net_pay' => ['required', 'numeric', 'min:0'],
            'payslip_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $line->update($validated);

        return redirect()->route('loan.accounting.payroll.show', $accounting_payroll_period)->with('status', 'Line updated.');
    }

    public function payrollLineDestroy(AccountingPayrollPeriod $accounting_payroll_period, AccountingPayrollLine $line): RedirectResponse
    {
        abort_unless($line->accounting_payroll_period_id === $accounting_payroll_period->id, 404);
        $line->delete();

        return redirect()->route('loan.accounting.payroll.show', $accounting_payroll_period)->with('status', 'Line removed.');
    }

    public function payslip(AccountingPayrollPeriod $accounting_payroll_period, AccountingPayrollLine $line): View
    {
        abort_unless($line->accounting_payroll_period_id === $accounting_payroll_period->id, 404);
        $line->load('employee');

        return view('loan.accounting.books.payroll.payslip', [
            'period' => $accounting_payroll_period,
            'line' => $line,
        ]);
    }

    /* ---------- Budget ---------- */

    public function budgetIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $year = request()->integer('fiscal_year') ?: null;
        $month = request()->integer('month') ?: null;
        $branch = request()->string('branch')->toString();
        $accountId = request()->integer('accounting_chart_account_id') ?: null;
        $export = request()->string('export')->toString();

        $q = AccountingBudgetLine::query()
            ->with('account')
            ->orderByDesc('fiscal_year')
            ->orderBy('month');

        if ($year !== null) {
            $q->where('fiscal_year', $year);
        }
        if ($month !== null) {
            $q->where('month', $month);
        }
        if ($branch !== '') {
            $q->where('branch', $branch);
        }
        if ($accountId !== null) {
            $q->where('accounting_chart_account_id', $accountId);
        }

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('budget-lines', [
                'Fiscal Year', 'Month', 'Branch', 'Account Code', 'Account Name', 'Label', 'Amount', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingBudgetLine $r) {
                    return [
                        (string) ($r->fiscal_year ?? ''),
                        (string) ($r->month ?? ''),
                        (string) ($r->branch ?? ''),
                        (string) ($r->account?->code ?? ''),
                        (string) ($r->account?->name ?? ''),
                        (string) ($r->label ?? ''),
                        (string) $r->amount,
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $rows = $q->paginate(25)->withQueryString();

        $years = AccountingBudgetLine::query()
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->values();

        $branches = AccountingBudgetLine::query()
            ->select('branch')
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch')
            ->values();

        $accounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('loan.accounting.books.budget.index', compact('rows', 'year', 'month', 'branch', 'accountId', 'years', 'branches', 'accounts'));
    }

    public function budgetCreate(): View
    {
        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();

        return view('loan.accounting.books.budget.create', compact('accounts'));
    }

    public function budgetStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'accounting_chart_account_id' => ['nullable', 'exists:accounting_chart_accounts,id'],
            'branch' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        AccountingBudgetLine::create($validated);

        return redirect()->route('loan.accounting.budget.index')->with('status', 'Budget line saved.');
    }

    public function budgetEdit(AccountingBudgetLine $accounting_budget_line): View
    {
        $accounts = AccountingChartAccount::query()->where('is_active', true)->orderBy('code')->get();

        return view('loan.accounting.books.budget.edit', ['row' => $accounting_budget_line, 'accounts' => $accounts]);
    }

    public function budgetUpdate(Request $request, AccountingBudgetLine $accounting_budget_line): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'accounting_chart_account_id' => ['nullable', 'exists:accounting_chart_accounts,id'],
            'branch' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $accounting_budget_line->update($validated);

        return redirect()->route('loan.accounting.budget.index')->with('status', 'Updated.');
    }

    public function budgetDestroy(AccountingBudgetLine $accounting_budget_line): RedirectResponse
    {
        $accounting_budget_line->delete();

        return redirect()->route('loan.accounting.budget.index')->with('status', 'Removed.');
    }

    public function budgetReport(Request $request): View
    {
        $year = (int) $request->input('fiscal_year', now()->year);
        $branch = $request->string('branch')->toString() ?: null;

        $budgetQuery = AccountingBudgetLine::query()->where('fiscal_year', $year);
        if ($branch !== null && $branch !== '') {
            $budgetQuery->where('branch', $branch);
        }
        $budgetLines = $budgetQuery->with('account')->get();

        $rows = [];
        foreach ($budgetLines as $bl) {
            $from = Carbon::create($year, $bl->month ?? 1, 1)->startOfMonth();
            $to = $bl->month
                ? $from->copy()->endOfMonth()
                : Carbon::create($year, 12, 31);

            $actual = 0.0;
            if ($bl->accounting_chart_account_id) {
                $t = $this->journalTotalsForAccounts($from, $to)->get($bl->accounting_chart_account_id);
                if ($t) {
                    $a = AccountingChartAccount::query()->find($bl->accounting_chart_account_id);
                    if ($a) {
                        $dr = (float) $t->total_debit;
                        $cr = (float) $t->total_credit;
                        if ($a->account_type === AccountingChartAccount::TYPE_EXPENSE) {
                            $actual = $dr - $cr;
                        } elseif ($a->account_type === AccountingChartAccount::TYPE_INCOME) {
                            $actual = $cr - $dr;
                        } else {
                            $actual = abs($dr - $cr);
                        }
                    }
                }
            }
            $rows[] = [
                'budget' => $bl,
                'actual' => $actual,
                'variance' => (float) $bl->amount - $actual,
            ];
        }

        return view('loan.accounting.books.budget.report', compact('year', 'branch', 'rows'));
    }

    /* ---------- Bank reconciliation ---------- */

    public function reconciliationIndex(): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $status = request()->string('status')->toString();
        $accountId = request()->integer('accounting_chart_account_id') ?: null;
        $from = request()->string('from')->toString();
        $to = request()->string('to')->toString();
        $search = trim(request()->string('q')->toString());
        $perPage = min(200, max(10, (int) request()->integer('per_page', 15)));
        $sort = strtolower(trim(request()->string('sort')->toString() ?: 'statement_date'));
        $dir = strtolower(trim(request()->string('dir')->toString() ?: 'desc'));
        $export = request()->string('export')->toString();

        $q = AccountingBankReconciliation::query()
            ->with(['account', 'preparedByUser']);

        if ($status !== '') {
            $q->where('status', $status);
        }
        if ($accountId !== null) {
            $q->where('accounting_chart_account_id', $accountId);
        }
        if ($from !== '') {
            $q->whereDate('statement_date', '>=', $from);
        }
        if ($to !== '') {
            $q->whereDate('statement_date', '<=', $to);
        }
        if ($search !== '') {
            $q->where(function ($qq) use ($search) {
                $qq->where('notes', 'like', '%'.$search.'%')
                    ->orWhere('outstanding_items', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhereHas('account', function ($qa) use ($search) {
                        $qa->where('code', 'like', '%'.$search.'%')
                            ->orWhere('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('preparedByUser', fn ($qu) => $qu->where('name', 'like', '%'.$search.'%'));
            });
        }
        $sortMap = [
            'statement_date' => 'statement_date',
            'statement_balance' => 'statement_balance',
            'status' => 'status',
            'id' => 'id',
        ];
        $sortBy = $sortMap[$sort] ?? 'statement_date';
        $sortDir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
        $q->orderBy($sortBy, $sortDir)->orderByDesc('id');

        if (in_array($export, ['csv', 'pdf', 'word'], true)) {
            return TabularExport::stream('reconciliations', [
                'Account Code', 'Account Name', 'Statement Date', 'Statement Balance', 'Adjustment Amount', 'Status', 'Prepared By', 'Notes',
            ], function () use ($q) {
                return $q->get()->map(function (AccountingBankReconciliation $r) {
                    return [
                        (string) ($r->account?->code ?? ''),
                        (string) ($r->account?->name ?? ''),
                        optional($r->statement_date)->format('Y-m-d'),
                        (string) ($r->statement_balance ?? ''),
                        (string) ($r->adjustment_amount ?? ''),
                        (string) ($r->status ?? ''),
                        (string) ($r->preparedByUser?->name ?? ''),
                        (string) ($r->notes ?? ''),
                    ];
                });
            }, $export);
        }

        $rows = $q->paginate($perPage)->withQueryString();

        $statuses = AccountingBankReconciliation::query()
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->values();

        $cashAccounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('loan.accounting.books.reconciliation.index', compact('rows', 'status', 'accountId', 'from', 'to', 'search', 'perPage', 'sort', 'dir', 'statuses', 'cashAccounts'));
    }

    public function reconciliationCreate(): View
    {
        $cashAccounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->orderBy('code')
            ->get();

        return view('loan.accounting.books.reconciliation.create', compact('cashAccounts'));
    }

    public function reconciliationStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'accounting_chart_account_id' => [
                'required',
                Rule::exists('accounting_chart_accounts', 'id')->where(fn ($q) => $q->where('is_cash_account', true)),
            ],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'outstanding_items' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,reconciled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        AccountingBankReconciliation::create([
            ...$validated,
            'adjustment_amount' => $validated['adjustment_amount'] ?? 0,
            'prepared_by' => $request->user()->id,
        ]);

        return redirect()->route('loan.accounting.reconciliation.index')->with('status', 'Reconciliation saved.');
    }

    public function reconciliationEdit(AccountingBankReconciliation $accounting_bank_reconciliation): View
    {
        $cashAccounts = AccountingChartAccount::query()
            ->where('is_active', true)
            ->where('is_cash_account', true)
            ->orderBy('code')
            ->get();
        $glBalance = $this->glNetForAccount(
            $accounting_bank_reconciliation->accounting_chart_account_id,
            Carbon::parse($accounting_bank_reconciliation->statement_date)
        );

        return view('loan.accounting.books.reconciliation.edit', [
            'row' => $accounting_bank_reconciliation,
            'cashAccounts' => $cashAccounts,
            'glBalance' => $glBalance,
        ]);
    }

    public function reconciliationUpdate(Request $request, AccountingBankReconciliation $accounting_bank_reconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'accounting_chart_account_id' => [
                'required',
                Rule::exists('accounting_chart_accounts', 'id')->where(fn ($q) => $q->where('is_cash_account', true)),
            ],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'adjustment_amount' => ['nullable', 'numeric'],
            'outstanding_items' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,reconciled'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $accounting_bank_reconciliation->update([
            ...$validated,
            'adjustment_amount' => $validated['adjustment_amount'] ?? 0,
        ]);

        return redirect()->route('loan.accounting.reconciliation.index')->with('status', 'Updated.');
    }

    public function reconciliationDestroy(AccountingBankReconciliation $accounting_bank_reconciliation): RedirectResponse
    {
        $accounting_bank_reconciliation->delete();

        return redirect()->route('loan.accounting.reconciliation.index')->with('status', 'Removed.');
    }
}
