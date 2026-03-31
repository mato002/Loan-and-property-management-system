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
use App\Models\PropertyPortalSetting;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmPortalAction;
use App\Models\PmTenant;
use App\Models\PmUnitMovement;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
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

    public function tenantStatements(Request $request)
    {
        $from = $this->filterDateFrom();
        $to = $this->filterDateTo();
        $tenantId = (int) $request->query('tenant_id', 0);
        $search = trim((string) $request->query('q', ''));

        $tenantsQuery = PmTenant::query()
            ->with([
                'invoices' => function ($q) use ($from, $to) {
                    $q->select(['id', 'pm_tenant_id', 'amount', 'amount_paid', 'issue_date'])
                        ->when($from, fn ($qq, $dateFrom) => $qq->whereDate('issue_date', '>=', $dateFrom))
                        ->when($to, fn ($qq, $dateTo) => $qq->whereDate('issue_date', '<=', $dateTo));
                },
                'payments' => function ($q) use ($from, $to) {
                    $q->select(['id', 'pm_tenant_id', 'amount', 'status', 'paid_at', 'created_at'])
                        ->when($from, fn ($qq, $dateFrom) => $qq->whereDate('paid_at', '>=', $dateFrom))
                        ->when($to, fn ($qq, $dateTo) => $qq->whereDate('paid_at', '<=', $dateTo));
                },
            ])
            ->orderBy('name');

        if ($tenantId > 0) {
            $tenantsQuery->where('id', $tenantId);
        }
        if ($search !== '') {
            $tenantsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('account_number', 'like', '%'.$search.'%')
                    ->orWhere('national_id', 'like', '%'.$search.'%');
            });
        }

        $tenants = $tenantsQuery->limit(500)->get();

        $rows = [];
        $exportRows = [];
        $totalInvoiced = 0.0;
        $totalCollected = 0.0;
        $totalOutstanding = 0.0;
        $totalPending = 0.0;

        foreach ($tenants as $tenant) {
            $invoiced = (float) $tenant->invoices->sum('amount');
            $collected = (float) $tenant->payments
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->sum('amount');
            $pendingAmount = (float) $tenant->payments
                ->where('status', PmPayment::STATUS_PENDING)
                ->sum('amount');
            $outstanding = max(0.0, $invoiced - $collected);

            $invoiceCount = (int) $tenant->invoices->count();
            $paymentCount = (int) $tenant->payments->count();
            $lastInvoiceDate = $tenant->invoices->max('issue_date');
            $lastPaymentDate = $tenant->payments->max('paid_at');

            $baseStatementParams = array_filter([
                'from' => $from,
                'to' => $to,
            ]);
            $statementUrl = route('property.tenants.statement', $tenant, false).'?'.http_build_query($baseStatementParams);
            $statementEmbedUrl = route('property.tenants.statement', $tenant, false).'?'.http_build_query(array_merge($baseStatementParams, ['embed' => 1]));

            $actions = new HtmlString(
                '<button type="button" class="text-indigo-600 hover:text-indigo-700 font-medium" data-statement-open="1" data-tenant="'.e((string) $tenant->name).'" data-url="'.e($statementEmbedUrl).'">View</button>'.
                ' <span class="text-slate-300">|</span> '.
                '<a href="'.$statementUrl.'" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">Open page</a>'
            );

            $rows[] = [
                (string) $tenant->name,
                (string) ($tenant->account_number ?? '—'),
                (string) ($tenant->phone ?? '—'),
                (string) ($tenant->email ?? '—'),
                (string) $invoiceCount,
                (string) $paymentCount,
                'KES '.number_format($invoiced, 2),
                'KES '.number_format($collected, 2),
                'KES '.number_format($pendingAmount, 2),
                'KES '.number_format($outstanding, 2),
                $lastInvoiceDate?->format('Y-m-d') ?? '—',
                $lastPaymentDate?->format('Y-m-d') ?? '—',
                $actions,
            ];

            $exportRows[] = [
                (string) $tenant->name,
                (string) ($tenant->account_number ?? ''),
                (string) ($tenant->phone ?? ''),
                (string) ($tenant->email ?? ''),
                (string) $invoiceCount,
                (string) $paymentCount,
                number_format($invoiced, 2, '.', ''),
                number_format($collected, 2, '.', ''),
                number_format($pendingAmount, 2, '.', ''),
                number_format($outstanding, 2, '.', ''),
                $lastInvoiceDate?->format('Y-m-d') ?? '',
                $lastPaymentDate?->format('Y-m-d') ?? '',
            ];

            $totalInvoiced += $invoiced;
            $totalCollected += $collected;
            $totalOutstanding += $outstanding;
            $totalPending += $pendingAmount;
        }

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return $this->exportTenantStatements($exportRows, $export);
        }

        return view('property.agent.reports.tenant.statements', [
            'title' => 'Tenant Statements',
            'subtitle' => 'Tenant Reports',
            'backRoute' => 'property.reports.tenant',
            'stats' => [
                ['label' => 'Tenants', 'value' => (string) count($rows), 'hint' => 'In current filter'],
                ['label' => 'Invoiced', 'value' => 'KES '.number_format($totalInvoiced, 2), 'hint' => 'Charges'],
                ['label' => 'Collected', 'value' => 'KES '.number_format($totalCollected, 2), 'hint' => 'Completed payments'],
                ['label' => 'Outstanding', 'value' => 'KES '.number_format($totalOutstanding, 2), 'hint' => 'Invoiced - collected'],
                ['label' => 'Pending payments', 'value' => 'KES '.number_format($totalPending, 2), 'hint' => 'Awaiting completion'],
            ],
            'columns' => ['Tenant', 'Account #', 'Phone', 'Email', 'Invoices', 'Payments', 'Invoiced', 'Collected', 'Pending', 'Outstanding', 'Last invoice', 'Last payment', 'Actions'],
            'tableRows' => $rows,
            'emptyTitle' => 'No tenant statement records',
            'emptyHint' => 'Tenant statement data will appear once invoices and payments are recorded.',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'tenant_id' => $tenantId > 0 ? (string) $tenantId : '',
                'q' => $search,
            ],
            'tenantOptions' => PmTenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    /**
     * @param array<int,array<int,string>> $rows
     */
    private function exportTenantStatements(array $rows, string $format): StreamedResponse
    {
        $columns = ['Tenant', 'Account Number', 'Phone', 'Email', 'Invoice Count', 'Payment Count', 'Invoiced', 'Collected', 'Pending Payments', 'Outstanding', 'Last Invoice Date', 'Last Payment Date'];
        $stamp = now()->format('Ymd_His');

        if ($format === 'pdf') {
            $filename = 'tenant-statements-'.$stamp.'.pdf';

            return response()->streamDownload(function () use ($columns, $rows) {
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Tenant statements</title>';
                echo '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:12px;margin:24px;}table{width:100%;border-collapse:collapse;margin-top:12px;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;}th{background:#f8fafc;font-weight:600;font-size:11px;text-transform:uppercase;}</style>';
                echo '</head><body>';
                echo '<h1>Tenant Statements</h1>';
                echo '<table><thead><tr>';
                foreach ($columns as $col) {
                    echo '<th>'.e($col).'</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>'.e((string) $cell).'</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $delimiter = $format === 'xls' ? "\t" : ',';
        $filename = 'tenant-statements-'.$stamp.($format === 'xls' ? '.xls' : '.csv');
        $contentType = $format === 'xls'
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($columns, $rows, $delimiter) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $columns, $delimiter);
            foreach ($rows as $row) {
                fputcsv($out, $row, $delimiter);
            }
            fclose($out);
        }, $filename, ['Content-Type' => $contentType]);
    }

    public function landlordStatements(Request $request)
    {
        $from = $this->filterDateFrom();
        $to = $this->filterDateTo();
        $landlordId = (int) $request->query('landlord_id', 0);
        $propertySearch = $this->filterPropertySearch();

        $commissionDefaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $commissionDefaultPct = is_numeric($commissionDefaultRaw) ? (float) $commissionDefaultRaw : 10.0;
        if ($commissionDefaultPct < 0) {
            $commissionDefaultPct = 0.0;
        }

        $commissionOverridesRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $commissionOverrides = [];
        $decodedOverrides = json_decode($commissionOverridesRaw, true);
        if (is_array($decodedOverrides)) {
            foreach ($decodedOverrides as $propertyId => $pct) {
                $pid = (int) $propertyId;
                if ($pid <= 0) {
                    continue;
                }
                if (! is_numeric($pct)) {
                    continue;
                }
                $rate = max(0.0, (float) $pct);
                $commissionOverrides[$pid] = $rate;
            }
        }

        $collectedByPropertyQ = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total');
        $this->applyDateRange($collectedByPropertyQ, 'pay.paid_at');
        $collectedByProperty = $collectedByPropertyQ->pluck('total', 'property_id');

        $expenseByPropertyPostedQ = DB::table('pm_accounting_entries as ae')
            ->where('ae.category', PmAccountingEntry::CATEGORY_EXPENSE)
            ->whereNotNull('ae.property_id')
            ->groupBy('ae.property_id')
            ->selectRaw('ae.property_id as property_id, COALESCE(SUM(ae.amount),0) as total');
        $this->applyDateRange($expenseByPropertyPostedQ, 'ae.entry_date');
        $expenseByPropertyPosted = $expenseByPropertyPostedQ->pluck('total', 'property_id');

        $utilityByPropertyQ = DB::table('pm_unit_utility_charges as uc')
            ->join('property_units as pu', 'pu.id', '=', 'uc.property_unit_id')
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(uc.amount),0) as total');
        if ($from !== null) {
            $utilityByPropertyQ->where('uc.billing_month', '>=', substr($from, 0, 7));
        }
        if ($to !== null) {
            $utilityByPropertyQ->where('uc.billing_month', '<=', substr($to, 0, 7));
        }
        $utilityByProperty = $utilityByPropertyQ->pluck('total', 'property_id');

        $maintenanceByPropertyQ = DB::table('pm_maintenance_jobs as mj')
            ->join('pm_maintenance_requests as mr', 'mr.id', '=', 'mj.pm_maintenance_request_id')
            ->join('property_units as pu', 'pu.id', '=', 'mr.property_unit_id')
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(mj.quote_amount),0) as total');
        if ($from !== null) {
            $maintenanceByPropertyQ->whereDate('mj.created_at', '>=', $from);
        }
        if ($to !== null) {
            $maintenanceByPropertyQ->whereDate('mj.created_at', '<=', $to);
        }
        $maintenanceByProperty = $maintenanceByPropertyQ->pluck('total', 'property_id');

        $links = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->select([
                'pl.user_id as landlord_id',
                'pl.property_id',
                'pl.ownership_percent',
                'u.name as landlord_name',
                'p.name as property_name',
            ])
            ->orderBy('u.name')
            ->orderBy('p.name');

        if ($landlordId > 0) {
            $links->where('pl.user_id', $landlordId);
        }
        if ($propertySearch !== null) {
            $links->where('p.name', 'like', '%'.$propertySearch.'%');
        }

        $linkRows = $links->get();

        $perLandlord = [];
        $exportRows = [];
        $propertySeen = [];
        $expenseBreakdown = [];

        foreach ($linkRows as $link) {
            $pid = (int) $link->property_id;
            $lid = (int) $link->landlord_id;
            $ownership = (float) ($link->ownership_percent ?? 0);
            $totalCollectedForProperty = (float) ($collectedByProperty[$pid] ?? 0);
            if ($totalCollectedForProperty <= 0 || $ownership <= 0) {
                continue;
            }

            $income = $totalCollectedForProperty * ($ownership / 100);
            if ($income <= 0) {
                continue;
            }

            $propertyCommissionPct = $commissionOverrides[$pid] ?? $commissionDefaultPct;
            $commission = $income * ($propertyCommissionPct / 100);
            $ownerShare = max(0.0, $income - $commission);

            if (! isset($perLandlord[$lid])) {
                $perLandlord[$lid] = [
                    'name' => (string) ($link->landlord_name ?? '—'),
                    'properties' => 0,
                    'income' => 0.0,
                    'commission' => 0.0,
                    'owner_share' => 0.0,
                    'expense_share' => 0.0,
                    'net_payable' => 0.0,
                ];
                $propertySeen[$lid] = [];
                $expenseBreakdown[$lid] = [];
            }

            if (! in_array($pid, $propertySeen[$lid], true)) {
                $perLandlord[$lid]['properties']++;
                $propertySeen[$lid][] = $pid;
            }

            $perLandlord[$lid]['income'] += $income;
            $perLandlord[$lid]['commission'] += $commission;
            $perLandlord[$lid]['owner_share'] += $ownerShare;
            $propertyExpensePosted = (float) ($expenseByPropertyPosted[$pid] ?? 0);
            $propertyExpenseUtility = (float) ($utilityByProperty[$pid] ?? 0);
            $propertyExpenseMaintenance = (float) ($maintenanceByProperty[$pid] ?? 0);
            $propertyExpenseOther = max(0.0, $propertyExpensePosted - $propertyExpenseUtility - $propertyExpenseMaintenance);
            $propertyExpense = $propertyExpenseUtility + $propertyExpenseMaintenance + $propertyExpenseOther;
            $ownerExpenseShare = $propertyExpense * ($ownership / 100);
            $perLandlord[$lid]['expense_share'] += $ownerExpenseShare;

            if ($ownerExpenseShare > 0) {
                $expenseBreakdown[$lid][] = [
                    'property' => (string) ($link->property_name ?? '—'),
                    'ownership_percent' => $ownership,
                    'utility_expense' => $propertyExpenseUtility,
                    'maintenance_expense' => $propertyExpenseMaintenance,
                    'other_expense' => $propertyExpenseOther,
                    'property_expense' => $propertyExpense,
                    'owner_share_expense' => $ownerExpenseShare,
                ];
            }
        }

        $rows = [];
        $totalIncome = 0.0;
        $totalCommission = 0.0;
        $totalOwnerShare = 0.0;
        $totalExpenseShare = 0.0;
        $totalNetPayable = 0.0;

        foreach ($perLandlord as $lid => $data) {
            $data['net_payable'] = (float) $data['owner_share'] - (float) $data['expense_share'];
            $totalIncome += $data['income'];
            $totalCommission += $data['commission'];
            $totalOwnerShare += $data['owner_share'];
            $totalExpenseShare += $data['expense_share'];
            $totalNetPayable += $data['net_payable'];

            $statementUrl = route('property.reports.landlord.detailed_statement', [
                'landlord_id' => $lid,
                'from' => $from,
                'to' => $to,
            ], false);
            $statementEmbedUrl = route('property.reports.landlord.detailed_statement', [
                'landlord_id' => $lid,
                'from' => $from,
                'to' => $to,
                'embed' => 1,
            ], false);

            $actions = new HtmlString(
                '<button type="button" class="text-indigo-600 hover:text-indigo-700 font-medium" data-landlord-statement-open="1" data-landlord="'.e((string) $data['name']).'" data-url="'.e($statementEmbedUrl).'">View</button>'.
                ' <span class="text-slate-300">|</span> '.
                '<button type="button" class="text-amber-700 hover:text-amber-800 font-medium" data-expense-open="1" data-landlord="'.e((string) $data['name']).'" data-expenses=\''.e((string) json_encode($expenseBreakdown[$lid] ?? [], JSON_UNESCAPED_UNICODE)).'\'>Expenses</button>'.
                ' <span class="text-slate-300">|</span> '.
                '<a href="'.$statementUrl.'" data-turbo-frame="property-main" class="text-indigo-600 hover:text-indigo-700 font-medium">Open page</a>'
            );

            $rows[] = [
                (string) $data['name'],
                (string) $data['properties'],
                'KES '.number_format($data['income'], 2),
                'KES '.number_format($data['commission'], 2),
                'KES '.number_format($data['owner_share'], 2),
                'KES '.number_format($data['expense_share'], 2),
                'KES '.number_format($data['net_payable'], 2),
                $actions,
            ];

            $exportRows[] = [
                (string) $data['name'],
                (string) $data['properties'],
                number_format($data['income'], 2, '.', ''),
                number_format($data['commission'], 2, '.', ''),
                number_format($data['owner_share'], 2, '.', ''),
                number_format($data['expense_share'], 2, '.', ''),
                number_format($data['net_payable'], 2, '.', ''),
            ];
        }

        $expenseExportLandlordId = (int) $request->query('expense_export_landlord_id', 0);
        $expenseExportFormat = strtolower((string) $request->query('expense_export', ''));
        if ($expenseExportLandlordId > 0 && in_array($expenseExportFormat, ['csv', 'pdf'], true)) {
            $landlordName = (string) ($perLandlord[$expenseExportLandlordId]['name'] ?? ('Landlord '.$expenseExportLandlordId));
            $rowsForExport = collect($expenseBreakdown[$expenseExportLandlordId] ?? [])->map(function (array $r) {
                return [
                    (string) ($r['property'] ?? '—'),
                    number_format((float) ($r['ownership_percent'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['utility_expense'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['maintenance_expense'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['other_expense'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['property_expense'] ?? 0), 2, '.', ''),
                    number_format((float) ($r['owner_share_expense'] ?? 0), 2, '.', ''),
                ];
            })->all();

            return $this->exportLandlordExpenseBreakdown($rowsForExport, $expenseExportFormat, $landlordName);
        }

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return $this->exportLandlordStatements($exportRows, $export);
        }

        return view('property.agent.reports.landlord.statements', [
            'title' => 'Landlord Statements',
            'subtitle' => 'Landlord Reports',
            'backRoute' => 'property.reports.landlord',
            'stats' => [
                ['label' => 'Landlords', 'value' => (string) count($rows), 'hint' => 'In current filter'],
                ['label' => 'Income (rent collected)', 'value' => 'KES '.number_format($totalIncome, 2), 'hint' => 'Landlord share basis'],
                ['label' => 'Agent commissions', 'value' => 'KES '.number_format($totalCommission, 2), 'hint' => 'Agent earnings'],
                ['label' => 'Gross payable', 'value' => 'KES '.number_format($totalOwnerShare, 2), 'hint' => 'Income - commission'],
                ['label' => 'Expenses (owner share)', 'value' => 'KES '.number_format($totalExpenseShare, 2), 'hint' => 'Utilities + maintenance + other'],
                ['label' => 'Net payable', 'value' => 'KES '.number_format($totalNetPayable, 2), 'hint' => 'Gross payable - operational expenses'],
            ],
            'columns' => ['Landlord', 'Properties', 'Income (collected)', 'Commission (agent earns)', 'Gross payable', 'Expenses (owner share)', 'Net payable', 'Actions'],
            'tableRows' => $rows,
            'emptyTitle' => 'No landlord statement records',
            'emptyHint' => 'Landlord statement data will appear once rent is collected.',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'landlord_id' => $landlordId > 0 ? (string) $landlordId : '',
                'property' => $propertySearch,
            ],
            'landlordOptions' => \App\Models\User::query()
                ->where('property_portal_role', 'landlord')
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name']),
        ]);
    }

    /**
     * @param array<int,array<int,string>> $rows
     */
    private function exportLandlordStatements(array $rows, string $format): StreamedResponse
    {
        $columns = ['Landlord', 'Properties', 'IncomeCollected', 'Commission', 'GrossPayable', 'ExpenseShare', 'NetPayable'];
        $stamp = now()->format('Ymd_His');

        if ($format === 'pdf') {
            $filename = 'landlord-statements-'.$stamp.'.pdf';

            return response()->streamDownload(function () use ($columns, $rows) {
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Landlord statements</title>';
                echo '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:12px;margin:24px;}table{width:100%;border-collapse:collapse;margin-top:12px;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;}th{background:#f8fafc;font-weight:600;font-size:11px;text-transform:uppercase;}</style>';
                echo '</head><body>';
                echo '<h1>Landlord Statements</h1>';
                echo '<table><thead><tr>';
                foreach ($columns as $col) {
                    echo '<th>'.e($col).'</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>'.e((string) $cell).'</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $delimiter = $format === 'xls' ? "\t" : ',';
        $filename = 'landlord-statements-'.$stamp.($format === 'xls' ? '.xls' : '.csv');
        $contentType = $format === 'xls'
            ? 'application/vnd.ms-excel; charset=UTF-8'
            : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($columns, $rows, $delimiter) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $columns, $delimiter);
            foreach ($rows as $row) {
                fputcsv($out, $row, $delimiter);
            }
            fclose($out);
        }, $filename, ['Content-Type' => $contentType]);
    }

    /**
     * @param array<int,array<int,string>> $rows
     */
    private function exportLandlordExpenseBreakdown(array $rows, string $format, string $landlordName): StreamedResponse
    {
        $columns = ['Property', 'OwnershipPercent', 'Utilities', 'Maintenance', 'Other', 'TotalPropertyExpenses', 'OwnerShareExpense'];
        $stamp = now()->format('Ymd_His');
        $safeName = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($landlordName)) ?: 'landlord';

        if ($format === 'pdf') {
            $filename = 'landlord-expenses-'.$safeName.'-'.$stamp.'.pdf';

            return response()->streamDownload(function () use ($columns, $rows, $landlordName) {
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Landlord expense breakdown</title>';
                echo '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:12px;margin:24px;}table{width:100%;border-collapse:collapse;margin-top:12px;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;}th{background:#f8fafc;font-weight:600;font-size:11px;text-transform:uppercase;}</style>';
                echo '</head><body>';
                echo '<h1>Landlord Expense Breakdown</h1>';
                echo '<p><strong>Landlord:</strong> '.e($landlordName).'</p>';
                echo '<table><thead><tr>';
                foreach ($columns as $col) {
                    echo '<th>'.e($col).'</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>'.e((string) $cell).'</td>';
                    }
                    echo '</tr>';
                }
                if (count($rows) === 0) {
                    echo '<tr><td colspan="4">No expense rows in this period.</td></tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $filename = 'landlord-expenses-'.$safeName.'-'.$stamp.'.csv';
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reportPage(Request $request, string $reportKey): View
    {
        $reports = $this->reportDefinitions();
        abort_unless(isset($reports[$reportKey]), 404);

        $report = $reports[$reportKey];
        $payload = ($report['builder'])();

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true) && ! empty($payload['exportRows'])) {
            $columns = (array) ($payload['exportColumns'] ?? $payload['columns'] ?? []);
            $rows = (array) ($payload['exportRows'] ?? []);
            $safeTitle = preg_replace('/[^a-z0-9\-]+/i', '-', (string) ($report['title'] ?? 'report'));
            $baseName = strtolower(trim((string) $safeTitle, '-'));

            if ($export === 'pdf') {
                return response()->streamDownload(function () use ($columns, $rows, $report) {
                    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.e((string) ($report['title'] ?? 'Report')).'</title>';
                    echo '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;font-size:12px;margin:24px;}table{width:100%;border-collapse:collapse;margin-top:12px;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;}th{background:#f8fafc;font-weight:600;font-size:11px;text-transform:uppercase;}</style>';
                    echo '</head><body><h1>'.e((string) ($report['title'] ?? 'Report')).'</h1><table><thead><tr>';
                    foreach ($columns as $col) {
                        echo '<th>'.e((string) $col).'</th>';
                    }
                    echo '</tr></thead><tbody>';
                    foreach ($rows as $row) {
                        echo '<tr>';
                        foreach ((array) $row as $cell) {
                            echo '<td>'.e((string) $cell).'</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table></body></html>';
                }, $baseName.'-'.now()->format('Ymd_His').'.pdf', [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }

            $delimiter = $export === 'xls' ? "\t" : ',';
            $filename = $baseName.'-'.now()->format('Ymd_His').($export === 'xls' ? '.xls' : '.csv');
            $contentType = $export === 'xls'
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8';

            return response()->streamDownload(function () use ($columns, $rows, $delimiter) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fputcsv($out, $columns, $delimiter);
                foreach ($rows as $row) {
                    fputcsv($out, (array) $row, $delimiter);
                }
                fclose($out);
            }, $filename, ['Content-Type' => $contentType]);
        }

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
                'view' => 'property.agent.reports.landlord.rent_collection',
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

