<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmAccountingEntry;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PmMaintenanceJob;
use App\Models\PmMaintenanceRequest;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
use App\Models\PmPenaltyRule;
use App\Models\PmPortalAction;
use App\Models\PmTenant;
use App\Models\PmUnitMovement;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmVendor;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyReportsController extends Controller
{
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

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            if (is_array($columns) && count($columns) > 0) {
                fputcsv($out, $columns);
            }
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $clean = array_map(static function ($cell) {
                        if ($cell instanceof HtmlString) {
                            return strip_tags((string) $cell);
                        }

                        return (string) $cell;
                    }, $row);
                    fputcsv($out, $clean);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
                'builder' => fn () => $this->buildPenaltyRulesReport(),
                'view' => 'property.agent.reports.tenant.rent_penalties',
            ],
            'tenant_de_allocation' => [
                'title' => 'Tenant De-Allocation Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->buildDeAllocationReport(),
                'view' => 'property.agent.reports.tenant.de_allocation',
            ],
            'tenant_allocation' => [
                'title' => 'Tenant Allocation Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->buildAllocationReport(),
                'view' => 'property.agent.reports.tenant.allocation',
            ],
            'tenant_deposits' => [
                'title' => 'Tenant Deposits Report',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->buildLeaseDepositReport(),
            ],
            'tenant_aging_balance' => [
                'title' => 'Tenant Aging Balance Summary',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->buildAgingBalanceReport(),
            ],
            'tenant_statements_by_allocation' => [
                'title' => 'Tenant Statements By Allocation',
                'group' => 'Tenant Reports',
                'back_route' => 'property.reports.tenant',
                'builder' => fn () => $this->buildStatementsByAllocationReport(),
            ],
            'landlord_statements' => [
                'title' => 'Landlord Statements',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildLandlordLedgerReport(),
            ],
            'landlord_detailed_statement' => [
                'title' => "Landlord's Detailed Statement",
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildLandlordDetailedStatementReport(),
                'view' => 'property.agent.reports.landlord.detailed_statement',
            ],
            'landlord_balance_summary' => [
                'title' => 'Landlords Balance Summary',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildLandlordBalanceSummaryReport(),
            ],
            'landlord_rental_income_commissions' => [
                'title' => 'Rental Income Commissions',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildLandlordCommissionsReport(),
            ],
            'landlord_rent_collection' => [
                'title' => 'Rent Collection Report',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildRentCollectionReport(),
            ],
            'landlord_property_statement' => [
                'title' => 'Property Statement',
                'group' => 'Landlord Reports',
                'back_route' => 'property.reports.landlord',
                'builder' => fn () => $this->buildPropertyStatementReport(),
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

    private function buildPenaltyRulesReport(): array
    {
        $rules = PmPenaltyRule::query()
            ->where('is_active', true)
            ->where('scope', 'rent')
            ->orderBy('id')
            ->get();

        $invoiceQuery = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->where('invoice_type', PmInvoice::TYPE_RENT)
            ->whereColumn('amount_paid', '<', 'amount');
        $this->applyDateRange($invoiceQuery, 'due_date');
        $invoices = $invoiceQuery->orderBy('due_date')->limit(300)->get();

        $today = now()->startOfDay();
        $rows = collect();
        $totalPenalty = 0.0;

        foreach ($invoices as $invoice) {
            $dueDate = $invoice->due_date;
            if ($dueDate === null) {
                continue;
            }

            $daysLate = $today->diffInDays($dueDate, false) * -1;
            if ($daysLate <= 0) {
                continue;
            }

            $base = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($base <= 0) {
                continue;
            }

            $penalty = 0.0;
            foreach ($rules as $rule) {
                $grace = (int) ($rule->grace_days ?? 0);
                if ($daysLate <= $grace) {
                    continue;
                }

                $value = 0.0;
                if ($rule->formula === 'flat') {
                    $value = (float) ($rule->amount ?? 0);
                } elseif ($rule->formula === 'percent' || $rule->formula === 'percent_plus_flat') {
                    $value = $base * (((float) ($rule->percent ?? 0)) / 100);
                    if ($rule->formula === 'percent_plus_flat') {
                        $value += (float) ($rule->amount ?? 0);
                    }
                }

                if ($rule->cap !== null) {
                    $value = min($value, (float) $rule->cap);
                }
                $penalty += max(0.0, $value);
            }

            if ($penalty <= 0) {
                continue;
            }

            $unitLabel = trim(($invoice->unit?->property?->name ?? '—').' / '.($invoice->unit?->label ?? '—'));
            $rows->push([
                $this->date((string) $dueDate),
                (string) ($invoice->invoice_no ?? ('INV-'.$invoice->id)),
                (string) ($invoice->tenant?->name ?? '—'),
                $unitLabel,
                $this->money($penalty),
            ]);
            $totalPenalty += $penalty;
        }

        return [
            'stats' => [
                ['label' => 'Penalty rows', 'value' => (string) $rows->count(), 'hint' => 'Computed'],
                ['label' => 'Active rent rules', 'value' => (string) $rules->count(), 'hint' => 'Applied'],
                ['label' => 'Total penalty', 'value' => $this->money($totalPenalty), 'hint' => 'From report rows'],
            ],
            'columns' => ['Date', 'Transaction ID', 'Tenant Name', 'Unit', 'Penalty'],
            'tableRows' => $rows->all(),
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

    private function buildDeAllocationReport(): array
    {
        $query = PmUnitMovement::query()
            ->with([
                'unit.property',
                'unit.leases' => fn ($leaseQuery) => $leaseQuery->with('pmTenant')->orderByDesc('start_date'),
            ])
            ->where('movement_type', 'move_out');
        $this->applyDateRange($query, 'completed_on');
        $movements = $query->latest('completed_on')->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'De-allocations', 'value' => (string) $movements->count(), 'hint' => 'Recent'],
                ['label' => 'Completed', 'value' => (string) $movements->whereNotNull('completed_on')->count(), 'hint' => 'Closed'],
            ],
            'columns' => ['Date of De-allocation', 'Transaction ID', 'Allocation ID', 'Tenant Name', 'Property Name', 'Unit Name'],
            'tableRows' => $movements->map(function (PmUnitMovement $movement) {
                $lease = $movement->unit?->leases?->first();

                return [
                    $this->date((string) ($movement->completed_on ?? $movement->scheduled_on)),
                    'TXN-'.$movement->id,
                    $lease ? 'ALLOC-'.$lease->id : '—',
                    (string) ($lease?->pmTenant?->name ?? '—'),
                    (string) ($movement->unit?->property?->name ?? '—'),
                    (string) ($movement->unit?->label ?? '—'),
                ];
            })->all(),
        ];
    }

    private function buildAllocationReport(): array
    {
        $query = PmUnitMovement::query()
            ->with([
                'unit.property',
                'unit.leases' => fn ($leaseQuery) => $leaseQuery
                    ->withSum('invoices as invoices_paid_total', 'amount_paid')
                    ->orderByDesc('start_date'),
            ])
            ->where('movement_type', 'move_in');
        $this->applyDateRange($query, 'completed_on');
        $movements = $query->latest('completed_on')->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Allocations', 'value' => (string) $movements->count(), 'hint' => 'Recent'],
                ['label' => 'Completed', 'value' => (string) $movements->whereNotNull('completed_on')->count(), 'hint' => 'Closed'],
            ],
            'columns' => ['Unit Name', 'Tenant Name', 'Amount Paid (when entering)', 'Unit Type', 'Transaction ID'],
            'tableRows' => $movements->map(function (PmUnitMovement $movement) {
                $lease = $movement->unit?->leases?->first();
                $amountPaid = (float) ($lease?->invoices_paid_total ?? 0);

                return [
                    (string) ($movement->unit?->label ?? '—'),
                    (string) ($lease?->pmTenant?->name ?? '—'),
                    $this->money($amountPaid),
                    (string) ($movement->unit?->unitTypeLabel() ?? '—'),
                    'TXN-'.$movement->id,
                ];
            })->all(),
        ];
    }

    private function buildLeaseDepositReport(): array
    {
        $query = PmLease::query()->with(['pmTenant', 'units.property']);
        $this->applyDateRange($query, 'start_date');
        $leases = $query->latest('start_date')->limit(250)->get();
        $totalDepositPaid = (float) $leases->sum(fn (PmLease $lease) => (float) ($lease->deposit_amount ?? 0));
        $totalRefunded = 0.0; // Refund tracking not yet stored in a dedicated field/table.
        $totalBalance = max(0.0, $totalDepositPaid - $totalRefunded);

        return [
            'stats' => [
                ['label' => 'Tenants', 'value' => (string) $leases->pluck('pm_tenant_id')->filter()->unique()->count(), 'hint' => 'With leases'],
                ['label' => 'Deposit paid', 'value' => $this->money($totalDepositPaid), 'hint' => 'Recorded'],
                ['label' => 'Refunded amount', 'value' => $this->money($totalRefunded), 'hint' => 'Recorded'],
                ['label' => 'Balance', 'value' => $this->money($totalBalance), 'hint' => 'Deposit - refunded'],
            ],
            'columns' => ['Tenant', 'Property', 'Unit', 'Deposit Paid', 'Refunded Amount', 'Balance'],
            'tableRows' => $leases->map(function (PmLease $lease) {
                $units = $lease->units;
                $propertyNames = $units->map(fn ($u) => $u->property?->name)->filter()->unique()->implode(', ');
                $unitNames = $units->map(fn ($u) => $u->label)->filter()->implode(', ');
                $depositPaid = (float) ($lease->deposit_amount ?? 0);
                $refundedAmount = 0.0;
                $balance = max(0.0, $depositPaid - $refundedAmount);

                return [
                    (string) ($lease->pmTenant?->name ?? '—'),
                    $propertyNames !== '' ? $propertyNames : '—',
                    $unitNames !== '' ? $unitNames : '—',
                    $this->money($depositPaid),
                    $this->money($refundedAmount),
                    $this->money($balance),
                ];
            })->all(),
        ];
    }

    private function buildAgingBalanceReport(): array
    {
        $query = PmInvoice::query()->with(['tenant', 'unit']);
        $this->applyDateRange($query, 'due_date');
        $invoices = $query
            ->whereColumn('amount_paid', '<', 'amount')
            ->latest('due_date')
            ->limit(500)
            ->get();

        $today = now()->startOfDay();

        $groups = [];
        foreach ($invoices as $invoice) {
            $balance = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($balance <= 0) {
                continue;
            }

            $tenantName = (string) ($invoice->tenant?->name ?? '—');
            $unitNo = (string) ($invoice->unit?->label ?? '—');
            $key = $tenantName.'|'.$unitNo;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'tenant' => $tenantName,
                    'unit' => $unitNo,
                    'total_balance' => 0.0,
                    'has_overdue' => false,
                ];
            }

            $groups[$key]['total_balance'] += $balance;
            if ($invoice->due_date && $invoice->due_date->lt($today)) {
                $groups[$key]['has_overdue'] = true;
            }
        }

        $rows = collect(array_values($groups))
            ->sortByDesc('total_balance')
            ->map(fn (array $row) => [
                $row['tenant'],
                $row['unit'],
                $row['has_overdue'] ? 'Overdue' : 'Open',
                $this->money((float) $row['total_balance']),
            ])
            ->all();

        return [
            'stats' => [
                ['label' => 'Tenants with balance', 'value' => (string) count($rows), 'hint' => 'Outstanding'],
                ['label' => 'Overdue', 'value' => (string) collect($rows)->where(2, 'Overdue')->count(), 'hint' => 'Past due'],
                ['label' => 'Total balance', 'value' => $this->money((float) collect(array_values($groups))->sum('total_balance')), 'hint' => 'Amount owed'],
            ],
            'columns' => ['Tenant Name', 'Unit No', 'Status', 'Total Balance'],
            'tableRows' => $rows,
        ];
    }

    private function buildStatementsByAllocationReport(): array
    {
        $query = PmLease::query()
            ->with(['pmTenant', 'units.property'])
            ->withSum('invoices as invoices_amount_sum', 'amount')
            ->withSum('invoices as invoices_paid_sum', 'amount_paid');
        $this->applyDateRange($query, 'start_date');
        $leases = $query->latest('start_date')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Lease allocations', 'value' => (string) $leases->count(), 'hint' => 'Recent'],
            ],
            'columns' => ['Tenant', 'Allocation', 'Start', 'End', 'Invoiced', 'Paid', 'Outstanding'],
            'tableRows' => $leases->map(function (PmLease $lease) {
                $units = $lease->units->map(fn ($unit) => ($unit->property->name ?? '—').' / '.$unit->label)->implode(', ');
                $invoiced = (float) ($lease->invoices_amount_sum ?? 0);
                $paid = (float) ($lease->invoices_paid_sum ?? 0);

                return [
                    (string) ($lease->pmTenant?->name ?? '—'),
                    $units !== '' ? $units : '—',
                    $this->date((string) $lease->start_date),
                    $this->date((string) $lease->end_date),
                    $this->money($invoiced),
                    $this->money($paid),
                    $this->money(max(0.0, $invoiced - $paid)),
                ];
            })->all(),
        ];
    }

    private function buildLandlordLedgerReport(): array
    {
        $leaseQuery = PmLease::query()
            ->with(['pmTenant', 'units.property'])
            ->withSum('invoices as invoices_total', 'amount')
            ->withSum('invoices as invoices_paid_total', 'amount_paid');
        $this->applyDateRange($leaseQuery, 'start_date');
        $leases = $leaseQuery->latest('start_date')->limit(300)->get();

        $leaseIds = $leases->pluck('id')->all();
        $arrearsByLease = [];
        $carryForwardCutoff = now()->startOfMonth()->toDateString();

        if (count($leaseIds) > 0) {
            $arrearsByLease = PmInvoice::query()
                ->select('pm_lease_id', DB::raw('SUM(GREATEST(amount - amount_paid, 0)) as arrears_total'))
                ->whereIn('pm_lease_id', $leaseIds)
                // Carried-forward arrears: only balances due before current month.
                ->whereDate('due_date', '<', $carryForwardCutoff)
                ->groupBy('pm_lease_id')
                ->pluck('arrears_total', 'pm_lease_id')
                ->toArray();
        }

        return [
            'stats' => [
                ['label' => 'Lease rows', 'value' => (string) $leases->count(), 'hint' => 'Landlord statement lines'],
                ['label' => 'Monthly rent', 'value' => $this->money((float) $leases->sum('monthly_rent')), 'hint' => 'Current contract rent'],
                ['label' => 'Amount paid', 'value' => $this->money((float) $leases->sum('invoices_paid_total')), 'hint' => 'Invoice settlements'],
                ['label' => 'Total balance', 'value' => $this->money((float) $leases->sum(function (PmLease $lease) use ($arrearsByLease) {
                    $monthlyRent = (float) ($lease->monthly_rent ?? 0);
                    $arrears = (float) ($arrearsByLease[$lease->id] ?? 0);
                    $total = $monthlyRent + $arrears;
                    $paid = (float) ($lease->invoices_paid_total ?? 0);

                    return max(0.0, $total - $paid);
                })), 'hint' => 'Outstanding'],
            ],
            'columns' => ['Unit', 'Tenant Name', 'Monthly Rent', 'Arrears', 'Total', 'Amount Paid', 'Total Balance'],
            'tableRows' => $leases->map(function (PmLease $lease) use ($arrearsByLease) {
                $units = $lease->units->map(fn ($unit) => ($unit->property->name ?? '—').' / '.($unit->label ?? '—'))->implode(', ');
                $monthlyRent = (float) ($lease->monthly_rent ?? 0);
                $arrears = (float) ($arrearsByLease[$lease->id] ?? 0);
                $total = $monthlyRent + $arrears;
                $paid = (float) ($lease->invoices_paid_total ?? 0);
                $balance = max(0.0, $total - $paid);

                return [
                    $units !== '' ? $units : '—',
                    (string) ($lease->pmTenant?->name ?? '—'),
                    $this->money($monthlyRent),
                    $this->money($arrears),
                    $this->money($total),
                    $this->money($paid),
                    $this->money($balance),
                ];
            })->all(),
        ];
    }

    private function filterLandlordId(): ?int
    {
        $id = request()->query('landlord_id');
        if ($id === null || $id === '') {
            return null;
        }

        if (is_numeric($id)) {
            $int = (int) $id;

            return $int > 0 ? $int : null;
        }

        return null;
    }

    private function buildLandlordDetailedStatementReport(): array
    {
        $landlords = User::query()
            ->where('property_portal_role', 'landlord')
            ->orderBy('name')
            ->get(['id', 'name']);

        $landlordId = $this->filterLandlordId();
        if ($landlordId === null) {
            return [
                'landlords' => $landlords,
                'selectedLandlordId' => null,
                'stats' => [
                    ['label' => 'Landlord', 'value' => '—', 'hint' => 'Select a landlord to view statement'],
                ],
                'columns' => ['Date', 'Transaction Type', 'Transaction ID', 'Invoice No', 'Payments', 'Balance'],
                'tableRows' => [],
                'emptyTitle' => 'Select a landlord',
                'emptyHint' => 'Use the landlord filter to load the detailed statement.',
            ];
        }

        $selected = $landlords->firstWhere('id', $landlordId);

        $query = PmLandlordLedgerEntry::query()
            ->where('user_id', $landlordId)
            ->orderBy('occurred_at')
            ->orderBy('id');
        $this->applyDateRange($query, 'occurred_at');
        $entries = $query->limit(2000)->get();

        $running = 0.0;
        $rows = $entries->map(function (PmLandlordLedgerEntry $e) use (&$running) {
            $amount = (float) $e->amount;
            $isCredit = $e->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT;

            // Balance: prefer stored balance_after when available; else compute running.
            if ($e->balance_after !== null) {
                $running = (float) $e->balance_after;
            } else {
                $running += $isCredit ? $amount : (-1 * $amount);
            }

            $txnType = $e->reference_type ?: ($isCredit ? 'Credit' : 'Debit');
            $txnId = ($e->reference_type && $e->reference_id)
                ? strtoupper((string) $e->reference_type).'-'.$e->reference_id
                : 'LED-'.$e->id;

            $invoiceNo = '—';
            if ($e->reference_type === 'invoice' && $e->reference_id) {
                $invoiceNo = (string) (PmInvoice::query()->where('id', $e->reference_id)->value('invoice_no') ?? '—');
            }

            $payments = $isCredit ? $this->money($amount) : $this->money(0);

            return [
                $this->dateTime((string) $e->occurred_at),
                (string) $txnType,
                (string) $txnId,
                (string) $invoiceNo,
                $payments,
                $this->money((float) $running),
            ];
        })->all();

        return [
            'landlords' => $landlords,
            'selectedLandlordId' => $landlordId,
            'stats' => [
                ['label' => 'Landlord', 'value' => (string) ($selected?->name ?? '—'), 'hint' => 'Detailed statement'],
                ['label' => 'Entries', 'value' => (string) count($rows), 'hint' => 'Ledger rows'],
            ],
            'columns' => ['Date', 'Transaction Type', 'Transaction ID', 'Invoice No', 'Payments', 'Balance'],
            'tableRows' => $rows,
        ];
    }

    private function buildLandlordBalanceSummaryReport(): array
    {
        $query = PmLandlordLedgerEntry::query()
            ->select('user_id', DB::raw("SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) as paid_amount"))
            ->groupBy('user_id')
            ->orderByDesc('paid_amount')
            ->limit(200);

        $this->applyDateRange($query, 'occurred_at');
        $rows = $query->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $totalPaid = (float) $rows->sum('paid_amount');

        return [
            'stats' => [
                ['label' => 'Landlords', 'value' => (string) $rows->count(), 'hint' => 'With payments'],
                ['label' => 'Total paid', 'value' => $this->money($totalPaid), 'hint' => 'Credits'],
            ],
            'columns' => ['Landlord', 'Amount paid'],
            'tableRows' => $rows->map(function ($row) use ($users) {
                return [
                    (string) ($users->get($row->user_id)?->name ?? '—'),
                    $this->money((float) ($row->paid_amount ?? 0)),
                ];
            })->all(),
        ];
    }

    private function buildLandlordCommissionsReport(): array
    {
        $commissionPct = (float) PropertyPortalSetting::getValue('commission_default_percent', '10');
        if ($commissionPct < 0) {
            $commissionPct = 0.0;
        }

        // Total rent collected per property in the selected date range (completed receipts only).
        $collectedByPropertyQ = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total');
        $this->applyDateRange($collectedByPropertyQ, 'pay.paid_at');
        $collectedByProperty = $collectedByPropertyQ->pluck('total', 'property_id');

        // Landlord-property links (ownership % used to compute landlord income share).
        $links = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->select([
                'pl.property_id',
                'pl.ownership_percent',
                'u.name as landlord_name',
                'p.name as property_name',
            ])
            ->orderBy('p.name')
            ->orderBy('u.name')
            ->get();

        $rawRows = $links->map(function ($link) use ($collectedByProperty, $commissionPct) {
            $pid = (int) $link->property_id;
            $ownership = (float) ($link->ownership_percent ?? 0);
            $income = ((float) ($collectedByProperty[$pid] ?? 0)) * ($ownership / 100);
            $commission = $income * ($commissionPct / 100);

            return [
                'property' => (string) ($link->property_name ?? '—'),
                'landlord' => (string) ($link->landlord_name ?? '—'),
                'income' => $income,
                'commission' => $commission,
            ];
        })->filter(fn (array $r) => $r['income'] > 0)->values();

        $totalIncome = (float) $rawRows->sum('income');
        $totalCommission = (float) $rawRows->sum('commission');

        return [
            'stats' => [
                ['label' => 'Commission rate', 'value' => number_format($commissionPct, 2).'%', 'hint' => 'Default setting'],
                ['label' => 'Income (collected)', 'value' => $this->money($totalIncome), 'hint' => 'Landlord share'],
                ['label' => 'Agent earns', 'value' => $this->money($totalCommission), 'hint' => 'Commission total'],
            ],
            'columns' => ['Property', 'Landlord', 'Income (total rent collected)', 'Commissions (agent earns)'],
            'tableRows' => $rawRows->map(fn (array $r) => [
                $r['property'],
                $r['landlord'],
                $this->money((float) $r['income']),
                $this->money((float) $r['commission']),
            ])->all(),
        ];
    }

    private function buildRentCollectionReport(): array
    {
        $query = PmPayment::query()->with('tenant');
        $this->applyDateRange($query, 'paid_at');
        $payments = $query->latest('paid_at')->latest('id')->limit(300)->get();

        return [
            'stats' => [
                ['label' => 'Payments', 'value' => (string) $payments->count(), 'hint' => 'Recent'],
                ['label' => 'Completed', 'value' => (string) $payments->where('status', PmPayment::STATUS_COMPLETED)->count(), 'hint' => 'Settled'],
                ['label' => 'Collected', 'value' => $this->money((float) $payments->where('status', PmPayment::STATUS_COMPLETED)->sum('amount')), 'hint' => 'Completed only'],
            ],
            'columns' => ['Date', 'Tenant', 'Channel', 'Amount', 'Reference', 'Status'],
            'tableRows' => $payments->map(fn (PmPayment $payment) => [
                $this->dateTime((string) $payment->paid_at),
                (string) ($payment->tenant?->name ?? '—'),
                ucfirst((string) ($payment->channel ?? '—')),
                $this->money((float) $payment->amount),
                (string) ($payment->external_ref ?? '—'),
                ucfirst((string) $payment->status),
            ])->all(),
        ];
    }

    private function buildPropertyStatementReport(): array
    {
        $from = $this->filterDateFrom();
        $to = $this->filterDateTo();
        $periodStart = $from ? \Illuminate\Support\Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $periodEnd = $to ? \Illuminate\Support\Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $propertyQ = $this->filterPropertySearch();

        // Opening balance (carried forward) = outstanding invoices before the period start.
        $openingQ = DB::table('pm_invoices as i')
            ->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->join('properties as p', 'p.id', '=', 'u.property_id')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('pm_accounting_entries as ae')
                    ->where('ae.source_key', 'invoice_issued')
                    ->whereColumn('ae.reference', 'i.invoice_no');
            })
            ->whereDate('i.issue_date', '<', $periodStart->toDateString())
            ->whereColumn('i.amount_paid', '<', 'i.amount')
            ->selectRaw("
                i.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as opening_balance
            ")
            ->groupBy('i.pm_tenant_id', 'i.property_unit_id');
        if ($propertyQ !== null) {
            $openingQ->where('p.name', 'like', '%'.$propertyQ.'%');
        }
        $openingRows = $openingQ->get();

        // Period invoicing (rent/bills/penalties) by tenant+unit.
        $periodInvQ = DB::table('pm_invoices as i')
            ->join('pm_tenants as t', 't.id', '=', 'i.pm_tenant_id')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->join('properties as p', 'p.id', '=', 'u.property_id')
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('pm_accounting_entries as ae')
                    ->where('ae.source_key', 'invoice_issued')
                    ->whereColumn('ae.reference', 'i.invoice_no');
            })
            ->whereBetween('i.issue_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw("
                i.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(CASE WHEN i.invoice_type = 'rent' THEN i.amount ELSE 0 END),0) as rent_invoices,
                COALESCE(SUM(CASE
                    WHEN (i.invoice_type = 'mixed' AND LOWER(COALESCE(i.description, '')) LIKE '%penalty%')
                      OR (i.invoice_type NOT IN ('rent', 'water', 'mixed'))
                    THEN i.amount ELSE 0 END),0) as penalties_invoices,
                COALESCE(SUM(CASE
                    WHEN i.invoice_type = 'water'
                      OR (i.invoice_type = 'mixed' AND LOWER(COALESCE(i.description, '')) NOT LIKE '%penalty%')
                    THEN i.amount ELSE 0 END),0) as bills_invoices,
                COALESCE(SUM(i.amount),0) as invoices_total
            ")
            ->groupBy('i.pm_tenant_id', 'i.property_unit_id');
        if ($propertyQ !== null) {
            $periodInvQ->where('p.name', 'like', '%'.$propertyQ.'%');
        }
        $periodInvRows = $periodInvQ->get();

        // Payments received in period = allocations where payment completed and paid_at within period.
        $payQ = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
            ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
            ->join('properties as p', 'p.id', '=', 'u.property_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('pm_accounting_entries as ae')
                    ->where('ae.source_key', 'payment_received')
                    ->whereRaw("ae.reference = COALESCE(NULLIF(pay.external_ref, ''), CONCAT('PAY-', pay.id))");
            })
            ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
            ->selectRaw("
                pay.pm_tenant_id as tenant_id,
                i.property_unit_id as unit_id,
                MAX(t.name) as tenant_name,
                MAX(u.label) as unit_label,
                MAX(p.name) as property_name,
                COALESCE(SUM(a.amount),0) as payments_received
            ")
            ->groupBy('pay.pm_tenant_id', 'i.property_unit_id');
        if ($propertyQ !== null) {
            $payQ->where('p.name', 'like', '%'.$propertyQ.'%');
        }
        $paymentRows = $payQ->get();

        // Merge all keys (tenant+unit).
        $index = [];
        $put = static function ($tenantId, $unitId, array $payload) use (&$index) {
            $k = (string) $tenantId.'|'.(string) $unitId;
            $index[$k] = array_merge($index[$k] ?? [
                'tenant_name' => '—',
                'unit_label' => '—',
                'property_name' => '—',
                'opening_balance' => 0.0,
                'rent_invoices' => 0.0,
                'penalties_invoices' => 0.0,
                'bills_invoices' => 0.0,
                'invoices_total' => 0.0,
                'payments_received' => 0.0,
            ], $payload);
        };

        foreach ($openingRows as $r) {
            $put($r->tenant_id, $r->unit_id, [
                'tenant_name' => (string) ($r->tenant_name ?? '—'),
                'unit_label' => (string) ($r->unit_label ?? '—'),
                'property_name' => (string) ($r->property_name ?? '—'),
                'opening_balance' => (float) ($r->opening_balance ?? 0),
            ]);
        }
        foreach ($periodInvRows as $r) {
            $put($r->tenant_id, $r->unit_id, [
                'tenant_name' => (string) ($r->tenant_name ?? '—'),
                'unit_label' => (string) ($r->unit_label ?? '—'),
                'property_name' => (string) ($r->property_name ?? '—'),
                'rent_invoices' => (float) ($r->rent_invoices ?? 0),
                'penalties_invoices' => (float) ($r->penalties_invoices ?? 0),
                'bills_invoices' => (float) ($r->bills_invoices ?? 0),
                'invoices_total' => (float) ($r->invoices_total ?? 0),
            ]);
        }
        foreach ($paymentRows as $r) {
            $put($r->tenant_id, $r->unit_id, [
                'tenant_name' => (string) ($r->tenant_name ?? '—'),
                'unit_label' => (string) ($r->unit_label ?? '—'),
                'property_name' => (string) ($r->property_name ?? '—'),
                'payments_received' => (float) ($r->payments_received ?? 0),
            ]);
        }

        $rowsRaw = collect(array_values($index))
            ->map(function (array $r) {
                $opening = (float) ($r['opening_balance'] ?? 0);
                $rent = (float) ($r['rent_invoices'] ?? 0);
                $pen = (float) ($r['penalties_invoices'] ?? 0);
                $bills = (float) ($r['bills_invoices'] ?? 0);
                $invTotal = $rent + $pen + $bills;
                $paid = (float) ($r['payments_received'] ?? 0);
                $closing = max(0.0, $opening + $invTotal - $paid);

                return [
                    'property_name' => (string) ($r['property_name'] ?? '—'),
                    'tenant_name' => (string) ($r['tenant_name'] ?? '—'),
                    'unit_label' => (string) ($r['unit_label'] ?? '—'),
                    'closing' => $closing,
                    'rent' => $rent,
                    'penalty' => $pen,
                    'bills' => $bills,
                    'paid' => $paid,
                    'opening' => $opening,
                ];
            })
            ->sortBy([fn ($a) => $a['property_name'], fn ($a) => $a['tenant_name'], fn ($a) => $a['unit_label']])
            ->values()
            ->all();

        $rows = collect($rowsRaw)->map(fn (array $r) => [
            $r['property_name'],
            $r['tenant_name'],
            $r['unit_label'],
            $this->money((float) $r['closing']),
            $this->money((float) $r['rent']),
            $this->money((float) $r['penalty']),
            $this->money((float) $r['bills']),
            $this->money((float) $r['paid']),
            $this->money((float) $r['opening']),
            $this->money((float) $r['closing']),
        ])->all();

        $totalClosing = (float) collect($rowsRaw)->sum('closing');

        $postedInvoicesQ = DB::table('pm_accounting_entries as ae')
            ->leftJoin('properties as p', 'p.id', '=', 'ae.property_id')
            ->where('ae.source_key', 'invoice_issued')
            ->where('ae.category', PmAccountingEntry::CATEGORY_INCOME)
            ->where('ae.entry_type', PmAccountingEntry::TYPE_CREDIT)
            ->whereBetween('ae.entry_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
        if ($propertyQ !== null) {
            $postedInvoicesQ->where('p.name', 'like', '%'.$propertyQ.'%');
        }
        $postedInvoices = (float) $postedInvoicesQ->sum('ae.amount');

        $postedPaymentsQ = DB::table('pm_accounting_entries as ae')
            ->leftJoin('properties as p', 'p.id', '=', 'ae.property_id')
            ->where('ae.source_key', 'payment_received')
            ->where('ae.category', PmAccountingEntry::CATEGORY_ASSET)
            ->where('ae.entry_type', PmAccountingEntry::TYPE_DEBIT)
            ->whereBetween('ae.entry_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);
        if ($propertyQ !== null) {
            $postedPaymentsQ->where('p.name', 'like', '%'.$propertyQ.'%');
        }
        $postedPayments = (float) $postedPaymentsQ->sum('ae.amount');

        return [
            'stats' => [
                ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => 'Tenant-unit lines'],
                ['label' => 'Total outstanding', 'value' => $this->money((float) $totalClosing), 'hint' => 'Balances'],
                ['label' => 'Invoices (posted)', 'value' => $this->money($postedInvoices), 'hint' => 'Accounting module'],
                ['label' => 'Payments (posted)', 'value' => $this->money($postedPayments), 'hint' => 'Accounting module'],
                ['label' => 'Period', 'value' => $periodStart->format('Y-m-d').' → '.$periodEnd->format('Y-m-d'), 'hint' => 'Filter'],
            ],
            'columns' => [
                'Property',
                'Tenant name',
                'Unit number',
                'Balance (owes us)',
                'Invoices: Rent',
                'Invoices: Penalties',
                'Invoices: Bills',
                'Payments received',
                'Opening balance',
                'Total balance',
            ],
            'tableRows' => $rows,
            'showPropertyFilter' => true,
        ];
    }

    private function filterPropertySearch(): ?string
    {
        $q = request()->query('property');
        if (! is_string($q)) {
            return null;
        }
        $q = trim($q);
        if ($q === '') {
            return null;
        }

        return mb_substr($q, 0, 80);
    }

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

    private function money(float $amount): string
    {
        return 'KES '.number_format($amount, 2);
    }

    private function date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
    }

    private function dateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return (string) \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
    }

    private function filterDateFrom(): ?string
    {
        $from = request()->query('from');
        if (! is_string($from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            return null;
        }

        return $from;
    }

    private function filterDateTo(): ?string
    {
        $to = request()->query('to');
        if (! is_string($to) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return null;
        }

        return $to;
    }

    private function applyDateRange($query, string $dateColumn): void
    {
        $from = $this->filterDateFrom();
        $to = $this->filterDateTo();

        if ($from !== null) {
            $query->whereDate($dateColumn, '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate($dateColumn, '<=', $to);
        }
    }
}
