<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\CsvExporter;
use App\Modules\Reporting\Landlord\LandlordReportService;
use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Tenant\AgingBalanceReportBuilder;
use App\Modules\Reporting\Tenant\TenantReportService;
use App\Models\PmAccountingEntry;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmPortalAction;
use App\Models\PmUnitMovement;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyReportsController extends Controller
{
	use ReportFilters;

	/**
	 * @var CsvExporter
	 */
	private CsvExporter $csvExporter;

	/**
	 * @var AgingBalanceReportBuilder
	 */
	private AgingBalanceReportBuilder $agingBalanceReport;

	/**
	 * @var TenantReportService
	 */
	private TenantReportService $tenantReports;

	/**
	 * @var LandlordReportService
	 */
	private LandlordReportService $landlordReports;

	public function __construct(
		CsvExporter $csvExporter,
		AgingBalanceReportBuilder $agingBalanceReport,
		TenantReportService $tenantReports,
		LandlordReportService $landlordReports
	)
	{
		$this->csvExporter = $csvExporter;
		$this->agingBalanceReport = $agingBalanceReport;
		$this->tenantReports = $tenantReports;
		$this->landlordReports = $landlordReports;
	}

    public function tenantStatements(): View
    {
        $invoices = PmInvoice::query()
            ->with('tenant')
            ->when($this->filterDateFrom(), fn ($q, $from) => $q->whereDate('issue_date', '>=', $from))
            ->when($this->filterDateTo(), fn ($q, $to) => $q->whereDate('issue_date', '<=', $to))
            ->get();

        $payments = PmPayment::query()
            ->with('tenant')
            ->when($this->filterDateFrom(), fn ($q, $from) => $q->whereDate('paid_at', '>=', $from))
            ->when($this->filterDateTo(), fn ($q, $to) => $q->whereDate('paid_at', '<=', $to))
            ->get();

        $entries = collect();

        foreach ($invoices as $invoice) {
            $entries->push([
                'date' => $invoice->issue_date?->toDateString(),
                'timestamp' => $invoice->issue_date?->startOfDay()?->timestamp ?? 0,
                'type' => 'Invoice',
                'id' => ($invoice->invoice_no ?: '#INV-'.$invoice->id).' / '.($invoice->tenant?->name ?? 'Unknown tenant'),
                'debit' => (float) $invoice->amount,
                'credit' => 0.0,
            ]);
        }

        foreach ($payments as $payment) {
            $entries->push([
                'date' => $payment->paid_at?->toDateString(),
                'timestamp' => $payment->paid_at?->timestamp ?? 0,
                'type' => 'Payment',
                'id' => ($payment->external_ref ?: '#PAY-'.$payment->id).' / '.($payment->tenant?->name ?? 'Unknown tenant'),
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);
        }

        $entries = $entries
            ->sortBy([
                ['timestamp', 'asc'],
                ['type', 'asc'],
            ])
            ->values();

        $runningBalance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = $entries->map(function (array $entry) use (&$runningBalance, &$totalDebit, &$totalCredit) {
            $debit = (float) $entry['debit'];
            $credit = (float) $entry['credit'];

            $totalDebit += $debit;
            $totalCredit += $credit;
            $runningBalance += $debit - $credit;

            return [
                $entry['date'] ?: '—',
                $entry['type'],
                $entry['id'],
                'KES '.number_format($debit, 2),
                'KES '.number_format($credit, 2),
                'KES '.number_format($runningBalance, 2),
            ];
        })->all();

        return view('property.agent.reports.tenant.statements', [
            'title' => 'Tenant Statements',
            'subtitle' => 'Tenant Reports',
            'backRoute' => 'property.reports.tenant',
            'stats' => [
                ['label' => 'Transactions', 'value' => (string) count($rows), 'hint' => 'Invoices + payments'],
                ['label' => 'Total debit', 'value' => 'KES '.number_format($totalDebit, 2), 'hint' => 'Charges'],
                ['label' => 'Total credit', 'value' => 'KES '.number_format($totalCredit, 2), 'hint' => 'Payments'],
                ['label' => 'Closing balance', 'value' => 'KES '.number_format($runningBalance, 2), 'hint' => 'Debit - credit'],
            ],
            'columns' => ['Date', 'Transaction Type', 'Transaction ID', 'Debit', 'Credit', 'Balance'],
            'tableRows' => $rows,
            'emptyTitle' => 'No tenant statement records',
            'emptyHint' => 'Tenant statement data will appear once invoices and payments are recorded.',
            'filters' => [
                'from' => $this->filterDateFrom(),
                'to' => $this->filterDateTo(),
            ],
        ]);
    }

    public function reportPage(Request $request, string $reportKey): View
    {
        $reports = $this->reportDefinitions();
        abort_unless(isset($reports[$reportKey]), 404);

        $report = $reports[$reportKey];
        $payload = ($report['builder'])();

        $viewName = $report['view'] ?? 'property.agent.reports.table';

        return view($viewName, array_merge([
            'title' => $report['title'],
            'subtitle' => $report['group'],
            'backRoute' => $report['back_route'],
            'emptyTitle' => 'No records found',
            'emptyHint' => 'This report will populate once there is transactional data.',
            'filters' => [
                'from' => $this->filterDateFrom(),
                'to' => $this->filterDateTo(),
                'property' => $this->filterPropertySearch(),
            ],
        ], $payload));
    }

    public function exportReportCsv(Request $request, string $reportKey): StreamedResponse
    {
        $reports = $this->reportDefinitions();
        abort_unless(isset($reports[$reportKey]), 404);

        $report = $reports[$reportKey];
        $payload = ($report['builder'])();
        $columns = $payload['columns'] ?? [];
        $rows = $payload['tableRows'] ?? [];

        $safeTitle = preg_replace('/[^a-z0-9\-]+/i', '-', (string) ($report['title'] ?? 'report'));
        $filename = strtolower(trim((string) $safeTitle, '-')).'.csv';

		return $this->csvExporter->stream($columns, $rows, $filename);
    }

    /**
     * @return array<string, array{title:string,group:string,back_route:string,builder:\Closure():array<string,mixed>,view?:string}>
     */
    private function reportDefinitions(): array
    {
        return [
            'tenant_rent_penalties' => [
                'title' => 'Rent Penalties Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->tenantReports->buildPenaltyRulesReport(),
                'view' => 'property.agent.reports.tenant.rent_penalties',
            ],
            'tenant_de_allocation' => [
                'title' => 'Tenant De-Allocation Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->tenantReports->buildDeAllocationReport(),
                'view' => 'property.agent.reports.tenant.de_allocation',
            ],
            'tenant_allocation' => [
                'title' => 'Tenant Allocation Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->tenantReports->buildAllocationReport(),
                'view' => 'property.agent.reports.tenant.allocation',
            ],
            'tenant_deposits' => [
                'title' => 'Tenant Deposits Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->tenantReports->buildLeaseDepositReport(),
            ],
            'tenant_aging_balance' => [
                'title' => 'Tenant Aging Balance Summary',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->agingBalanceReport->build(),
            ],
            'tenant_statements_by_allocation' => [
                'title' => 'Tenant Statements By Allocation',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->tenantReports->buildStatementsByAllocationReport(),
            ],
            'landlord_statements' => [
                'title' => 'Landlord Statements',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildLandlordLedgerReport(),
            ],
            'landlord_detailed_statement' => [
                'title' => "Landlord's Detailed Statement",
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildLandlordDetailedStatementReport(),
                'view' => 'property.agent.reports.landlord.detailed_statement',
            ],
            'landlord_balance_summary' => [
                'title' => 'Landlords Balance Summary',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildLandlordBalanceSummaryReport(),
            ],
            'landlord_rental_income_commissions' => [
                'title' => 'Rental Income Commissions',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildLandlordCommissionsReport(),
            ],
            'landlord_rent_collection' => [
                'title' => 'Rent Collection Report',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildRentCollectionReport(),
            ],
            'landlord_property_statement' => [
                'title' => 'Property Statement',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->landlordReports->buildPropertyStatementReport(),
            ],
            'expense_income_expenses_summary' => [
                'title' => 'Income & Expenses Summary',
                'group' => 'Expense Reports',
                'back_route' => 'property.reports.expense',
                'builder' => fn () => $this->buildIncomeExpensesSummaryReport(),
            ],
            'expense_maintenance_expense' => [
                'title' => 'Maintenance Expense Report',
                'group' => 'Expense Reports',
                'back_route' => 'property.reports.expense',
                'builder' => fn () => $this->buildMaintenanceExpenseReport(),
            ],
            'expense_utility_billing' => [
                'title' => 'Utility Billing Expenses',
                'group' => 'Expense Reports',
                'back_route' => 'property.reports.expense',
                'builder' => fn () => $this->buildUtilityBillingReport(),
            ],
            'expense_vendor_expense_work' => [
                'title' => 'Vendor Expense Work Report',
                'group' => 'Expense Reports',
                'back_route' => 'property.reports.expense',
                'builder' => fn () => $this->buildVendorExpenseReport(),
            ],
            'expense_cash_book' => [
                'title' => 'Expense Cash Book View',
                'group' => 'Expense Reports',
                'back_route' => 'property.reports.expense',
                'builder' => fn () => $this->buildCashBookReport(),
            ],
            'maintenance_history' => [
                'title' => 'Maintenance History',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildMaintenanceHistoryReport(),
            ],
            'maintenance_cost' => [
                'title' => 'Maintenance Cost Report',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildMaintenanceExpenseReport(),
            ],
            'maintenance_frequency' => [
                'title' => 'Issue Frequency Report',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildIssueFrequencyReport(),
            ],
            'maintenance_audit_trail' => [
                'title' => 'Audit Trail',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildPortalActionsReport(),
            ],
            'maintenance_email_logs' => [
                'title' => 'Email Logs',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildEmailLogsReport(),
            ],
            'maintenance_login_logs' => [
                'title' => 'Log In/Out Logs',
                'group' => 'Maintenance Reports',
                'back_route' => 'property.reports.maintenance',
                'builder' => fn () => $this->buildPortalActionsReport(),
            ],
            'financial_profit_loss_summary' => [
                'title' => 'Profit and Loss Summary',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildProfitLossSummaryReport(),
            ],
            'financial_profit_loss_comparison' => [
                'title' => 'Profit and Loss Comparison',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildProfitLossByMonthReport(),
            ],
            'financial_profit_loss_department' => [
                'title' => 'Profit and Loss by Department',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildProfitLossByDepartmentReport(),
            ],
            'financial_profit_loss_months' => [
                'title' => 'Profit and Loss by Months',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildProfitLossByMonthReport(),
            ],
            'financial_manufacturing_account' => [
                'title' => 'Manufacturing Account',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildTrialBalanceLensReport(),
            ],
            'financial_balance_sheet_standard' => [
                'title' => 'Balance Sheet Standard',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildBalanceSheetReport(),
            ],
            'financial_balance_sheet_itemised' => [
                'title' => 'Balance Sheet Itemised',
                'group' => 'Financial Reports',
                'back_route' => 'property.reports.financial',
                'builder' => fn () => $this->buildBalanceSheetReport(),
            ],
        ];
    }

    private function buildMovementsReport(string $movementType): array
    {
        $query = PmUnitMovement::query()
            ->with('unit.property')
            ->where('movement_type', $movementType);
        $this->applyDateRange($query, 'scheduled_on');
        $rows = $query->latest('scheduled_on')->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Movements', 'value' => (string) $rows->count(), 'hint' => 'Recent'],
                ['label' => 'Completed', 'value' => (string) $rows->whereNotNull('completed_on')->count(), 'hint' => 'Done'],
            ],
            'columns' => ['Scheduled', 'Completed', 'Property / Unit', 'Type', 'Status', 'Notes'],
            'tableRows' => $rows->map(fn (PmUnitMovement $movement) => [
                $this->date((string) $movement->scheduled_on),
                $this->date((string) $movement->completed_on),
                trim(($movement->unit?->property?->name ?? '—').' / '.($movement->unit?->label ?? '—')),
                (string) $movement->movement_type,
                (string) ($movement->status ?? '—'),
                (string) ($movement->notes ?? '—'),
            ])->all(),
        ];
    }

	// filterPropertySearch handled by ReportFilters trait

    private function buildIncomeExpensesSummaryReport(): array
    {
        $query = PmAccountingEntry::query()
            ->select('category', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as records'))
            ->whereIn('category', [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::CATEGORY_EXPENSE])
            ->groupBy('category');
        $this->applyDateRange($query, 'entry_date');
        $rows = $query->get();

        $income = (float) ($rows->firstWhere('category', PmAccountingEntry::CATEGORY_INCOME)->total_amount ?? 0);
        $expense = (float) ($rows->firstWhere('category', PmAccountingEntry::CATEGORY_EXPENSE)->total_amount ?? 0);

        return [
            'stats' => [
                ['label' => 'Income', 'value' => $this->money($income), 'hint' => 'Recorded'],
                ['label' => 'Expenses', 'value' => $this->money($expense), 'hint' => 'Recorded'],
                ['label' => 'Net', 'value' => $this->money($income - $expense), 'hint' => 'Income - expense'],
            ],
            'columns' => ['Category', 'Records', 'Total amount'],
            'tableRows' => $rows->map(fn ($row) => [
                ucfirst((string) $row->category),
                (string) ($row->records ?? 0),
                $this->money((float) ($row->total_amount ?? 0)),
            ])->all(),
        ];
    }

    private function buildMaintenanceExpenseReport(): array
    {
        $query = PmMaintenanceJob::query()->with(['vendor', 'request.unit.property']);
        $this->applyDateRange($query, 'created_at');
        $jobs = $query->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Jobs', 'value' => (string) $jobs->count(), 'hint' => 'Recent'],
                ['label' => 'Quoted', 'value' => $this->money((float) $jobs->sum('quote_amount')), 'hint' => 'Total'],
            ],
            'columns' => ['Job', 'Property / Unit', 'Vendor', 'Quote', 'Status', 'Completed'],
            'tableRows' => $jobs->map(fn (PmMaintenanceJob $job) => [
                '#'.$job->id,
                trim(($job->request?->unit?->property?->name ?? '—').' / '.($job->request?->unit?->label ?? '—')),
                (string) ($job->vendor?->name ?? '—'),
                $this->money((float) ($job->quote_amount ?? 0)),
                ucfirst((string) ($job->status ?? '—')),
                $this->dateTime((string) $job->completed_at),
            ])->all(),
        ];
    }

    private function buildUtilityBillingReport(): array
    {
        $query = PmUnitUtilityCharge::query()->with('unit.property');
        $from = $this->filterDateFrom();
        $to = $this->filterDateTo();
        if ($from !== null) {
            $query->where('billing_month', '>=', substr($from, 0, 7));
        }
        if ($to !== null) {
            $query->where('billing_month', '<=', substr($to, 0, 7));
        }
        $charges = $query->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Charges', 'value' => (string) $charges->count(), 'hint' => 'Recent'],
                ['label' => 'Amount', 'value' => $this->money((float) $charges->sum('amount')), 'hint' => 'Billed'],
                ['label' => 'Invoiced', 'value' => (string) $charges->where('is_invoiced', true)->count(), 'hint' => 'Converted'],
            ],
            'columns' => ['Month', 'Type', 'Property / Unit', 'Label', 'Amount', 'Invoiced'],
            'tableRows' => $charges->map(fn (PmUnitUtilityCharge $charge) => [
                (string) ($charge->billing_month ?? '—'),
                ucfirst((string) ($charge->charge_type ?? '—')),
                trim(($charge->unit?->property?->name ?? '—').' / '.($charge->unit?->label ?? '—')),
                (string) ($charge->label ?? '—'),
                $this->money((float) ($charge->amount ?? 0)),
                $charge->is_invoiced ? 'Yes' : 'No',
            ])->all(),
        ];
    }

    private function buildVendorExpenseReport(): array
    {
        $vendors = PmVendor::query()
            ->withCount('maintenanceJobs')
            ->withSum('maintenanceJobs as quoted_total', 'quote_amount')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return [
            'stats' => [
                ['label' => 'Vendors', 'value' => (string) $vendors->count(), 'hint' => 'Active set'],
            ],
            'columns' => ['Vendor', 'Category', 'Jobs', 'Quoted total', 'Status', 'Rating'],
            'tableRows' => $vendors->map(fn (PmVendor $vendor) => [
                $vendor->name,
                (string) ($vendor->category ?? '—'),
                (string) $vendor->maintenance_jobs_count,
                $this->money((float) ($vendor->quoted_total ?? 0)),
                ucfirst((string) ($vendor->status ?? '—')),
                (string) ($vendor->rating ?? '—'),
            ])->all(),
        ];
    }

    private function buildCashBookReport(): array
    {
        $query = PmAccountingEntry::query()->with('property');
        $this->applyDateRange($query, 'entry_date');
        $entries = $query->latest('entry_date')->latest('id')->limit(300)->get();

        return [
            'stats' => [
                ['label' => 'Entries', 'value' => (string) $entries->count(), 'hint' => 'Recent'],
                ['label' => 'Debits', 'value' => $this->money((float) $entries->where('entry_type', PmAccountingEntry::TYPE_DEBIT)->sum('amount')), 'hint' => 'Total'],
                ['label' => 'Credits', 'value' => $this->money((float) $entries->where('entry_type', PmAccountingEntry::TYPE_CREDIT)->sum('amount')), 'hint' => 'Total'],
            ],
            'columns' => ['Date', 'Property', 'Account', 'Type', 'Category', 'Amount', 'Reference'],
            'tableRows' => $entries->map(fn (PmAccountingEntry $entry) => [
                $this->date((string) $entry->entry_date),
                (string) ($entry->property?->name ?? '—'),
                (string) $entry->account_name,
                ucfirst((string) $entry->entry_type),
                ucfirst((string) $entry->category),
                $this->money((float) $entry->amount),
                (string) ($entry->reference ?? '—'),
            ])->all(),
        ];
    }

    private function buildMaintenanceHistoryReport(): array
    {
        $query = PmMaintenanceRequest::query()->with('unit.property');
        $this->applyDateRange($query, 'created_at');
        $requests = $query->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Requests', 'value' => (string) $requests->count(), 'hint' => 'Recent'],
            ],
            'columns' => ['Reported', 'Property / Unit', 'Category', 'Urgency', 'Status', 'Description'],
            'tableRows' => $requests->map(fn (PmMaintenanceRequest $request) => [
                $this->dateTime((string) $request->created_at),
                trim(($request->unit?->property?->name ?? '—').' / '.($request->unit?->label ?? '—')),
                (string) ($request->category ?? '—'),
                ucfirst((string) ($request->urgency ?? '—')),
                ucfirst((string) ($request->status ?? '—')),
                (string) ($request->description ?? '—'),
            ])->all(),
        ];
    }

    private function buildIssueFrequencyReport(): array
    {
        $query = PmMaintenanceRequest::query()
            ->select('category', DB::raw('COUNT(*) as issues'))
            ->groupBy('category')
            ->orderByDesc('issues')
            ->limit(100);
        $this->applyDateRange($query, 'created_at');
        $rows = $query->get();

        return [
            'stats' => [
                ['label' => 'Issue categories', 'value' => (string) $rows->count(), 'hint' => 'Grouped'],
            ],
            'columns' => ['Category', 'Issue count'],
            'tableRows' => $rows->map(fn ($row) => [
                (string) ($row->category ?? '—'),
                (string) ($row->issues ?? 0),
            ])->all(),
        ];
    }

    private function buildPortalActionsReport(): array
    {
        $query = PmPortalAction::query()->with('user');
        $this->applyDateRange($query, 'created_at');
        $actions = $query->latest('id')->limit(300)->get();

        return [
            'stats' => [
                ['label' => 'Actions', 'value' => (string) $actions->count(), 'hint' => 'Recent'],
            ],
            'columns' => ['When', 'User', 'Role', 'Action', 'Notes'],
            'tableRows' => $actions->map(fn (PmPortalAction $action) => [
                $this->dateTime((string) $action->created_at),
                (string) ($action->user?->name ?? '—'),
                ucfirst((string) ($action->portal_role ?? '—')),
                (string) ($action->action_key ?? '—'),
                (string) ($action->notes ?? '—'),
            ])->all(),
        ];
    }

    private function buildEmailLogsReport(): array
    {
        $query = PmMessageLog::query();
        $this->applyDateRange($query, 'sent_at');
        $logs = $query->latest('sent_at')->latest('id')->limit(300)->get();

        return [
            'stats' => [
                ['label' => 'Messages', 'value' => (string) $logs->count(), 'hint' => 'Recent'],
                ['label' => 'Delivered', 'value' => (string) $logs->where('delivery_status', 'delivered')->count(), 'hint' => 'Success'],
                ['label' => 'Failed', 'value' => (string) $logs->where('delivery_status', 'failed')->count(), 'hint' => 'Errors'],
            ],
            'columns' => ['Sent', 'Channel', 'To', 'Subject', 'Status'],
            'tableRows' => $logs->map(fn (PmMessageLog $log) => [
                $this->dateTime((string) $log->sent_at),
                strtoupper((string) ($log->channel ?? '—')),
                (string) ($log->to_address ?? '—'),
                (string) ($log->subject ?? '—'),
                (string) ($log->delivery_status ?? '—'),
            ])->all(),
        ];
    }

    private function buildProfitLossSummaryReport(): array
    {
        return $this->buildIncomeExpensesSummaryReport();
    }

    private function buildProfitLossByDepartmentReport(): array
    {
        $query = PmAccountingEntry::query()
            ->select('account_name', 'category', DB::raw('SUM(amount) as total_amount'))
            ->whereIn('category', [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::CATEGORY_EXPENSE])
            ->groupBy('account_name', 'category')
            ->orderBy('account_name')
            ->limit(300);
        $this->applyDateRange($query, 'entry_date');
        $rows = $query->get();

        return [
            'stats' => [
                ['label' => 'Accounts', 'value' => (string) $rows->count(), 'hint' => 'Grouped'],
            ],
            'columns' => ['Account', 'Category', 'Amount'],
            'tableRows' => $rows->map(fn ($row) => [
                (string) ($row->account_name ?? '—'),
                ucfirst((string) ($row->category ?? '—')),
                $this->money((float) ($row->total_amount ?? 0)),
            ])->all(),
        ];
    }

    private function buildProfitLossByMonthReport(): array
    {
        $query = PmAccountingEntry::query()
            ->select(DB::raw("DATE_FORMAT(entry_date, '%Y-%m') as period"), 'category', DB::raw('SUM(amount) as total_amount'))
            ->whereIn('category', [PmAccountingEntry::CATEGORY_INCOME, PmAccountingEntry::CATEGORY_EXPENSE])
            ->groupBy(DB::raw("DATE_FORMAT(entry_date, '%Y-%m')"), 'category')
            ->orderByDesc('period')
            ->limit(240);
        $this->applyDateRange($query, 'entry_date');
        $rows = $query->get();

        return [
            'stats' => [
                ['label' => 'Periods', 'value' => (string) $rows->pluck('period')->unique()->count(), 'hint' => 'Months'],
            ],
            'columns' => ['Month', 'Category', 'Amount'],
            'tableRows' => $rows->map(fn ($row) => [
                (string) ($row->period ?? '—'),
                ucfirst((string) ($row->category ?? '—')),
                $this->money((float) ($row->total_amount ?? 0)),
            ])->all(),
        ];
    }

    private function buildTrialBalanceLensReport(): array
    {
        $query = PmAccountingEntry::query()
            ->select('account_name', 'entry_type', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('account_name', 'entry_type')
            ->orderBy('account_name')
            ->limit(300);
        $this->applyDateRange($query, 'entry_date');
        $rows = $query->get();

        return [
            'stats' => [
                ['label' => 'Account lines', 'value' => (string) $rows->count(), 'hint' => 'Grouped'],
            ],
            'columns' => ['Account', 'Type', 'Amount'],
            'tableRows' => $rows->map(fn ($row) => [
                (string) ($row->account_name ?? '—'),
                ucfirst((string) ($row->entry_type ?? '—')),
                $this->money((float) ($row->total_amount ?? 0)),
            ])->all(),
        ];
    }

    private function buildBalanceSheetReport(): array
    {
        $query = PmAccountingEntry::query()
            ->select('category', DB::raw('SUM(CASE WHEN entry_type="debit" THEN amount ELSE -amount END) as balance'))
            ->whereIn('category', [
                PmAccountingEntry::CATEGORY_ASSET,
                PmAccountingEntry::CATEGORY_LIABILITY,
                PmAccountingEntry::CATEGORY_EQUITY,
            ])
            ->groupBy('category');
        $this->applyDateRange($query, 'entry_date');
        $rows = $query->get();

        return [
            'stats' => [
                ['label' => 'Categories', 'value' => (string) $rows->count(), 'hint' => 'Balance sheet groups'],
            ],
            'columns' => ['Category', 'Balance'],
            'tableRows' => $rows->map(fn ($row) => [
                ucfirst((string) ($row->category ?? '—')),
                $this->money((float) ($row->balance ?? 0)),
            ])->all(),
        ];
    }

	// Shared helpers (money/date/dateTime/filterDateFrom/filterDateTo/applyDateRange) provided by ReportFilters trait
}

