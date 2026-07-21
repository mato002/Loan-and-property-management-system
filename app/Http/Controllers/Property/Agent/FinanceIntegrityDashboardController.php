<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\AccountingFirebreakService;
use App\Services\Property\FinanceIntegrityService;
use App\Services\Property\FinancialReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceIntegrityDashboardController extends Controller
{
    public function index(
        Request $request,
        FinanceIntegrityService $integrity,
        AccountingFirebreakService $firebreak,
    ): View {
        $tenantId = max(0, (int) $request->query('tenant', 0));
        $tenantFilter = $tenantId > 0 ? $tenantId : null;

        $report = $integrity->dashboard($tenantFilter, 100);
        $summary = $report['summary'] ?? [];

        $auditLogs = $firebreak->recentAuditLogs(40)
            ->filter(fn ($log) => str_starts_with((string) $log->action, 'finance_integrity_')
                || (string) $log->action === \App\Models\PmAccountingAuditLog::ACTION_FINANCE_INTEGRITY_SCAN
                || str_starts_with((string) $log->action, 'fin_recon_'));

        $severityColors = [
            FinancialReconciliationService::SEVERITY_CRITICAL => 'bg-rose-200 text-rose-900',
            FinancialReconciliationService::SEVERITY_WARNING => 'bg-amber-200 text-amber-900',
            FinancialReconciliationService::SEVERITY_INFO => 'bg-sky-200 text-sky-900',
        ];

        $stats = [
            ['label' => 'Active drift', 'value' => (string) ((int) ($summary['total_issues'] ?? 0)), 'hint' => 'All integrity categories'],
            ['label' => 'Critical', 'value' => (string) ((int) ($summary['critical'] ?? 0)), 'hint' => 'Requires immediate action'],
            ['label' => 'Affected tenants', 'value' => (string) ((int) ($summary['affected_tenants'] ?? 0)), 'hint' => 'Unique tenants in drift rows'],
            ['label' => 'Affected invoices', 'value' => (string) ((int) ($summary['affected_invoices'] ?? 0)), 'hint' => 'Unique invoices in drift rows'],
            ['label' => 'Active categories', 'value' => (string) ((int) ($summary['active_categories'] ?? 0)), 'hint' => 'Categories with open issues'],
            ['label' => 'Last scan', 'value' => isset($report['run_at']) ? \Carbon\Carbon::parse($report['run_at'])->diffForHumans() : '—', 'hint' => 'On page load'],
        ];

        return property_view('property.agent.accounting.finance_integrity_dashboard', [
            'stats' => $stats,
            'tenantFilter' => $tenantFilter,
            'report' => $report,
            'summary' => $summary,
            'auditLogs' => $auditLogs,
            'severityColors' => $severityColors,
        ]);
    }
}
