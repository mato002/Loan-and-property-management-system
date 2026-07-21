<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\AccountingFirebreakService;
use App\Services\Property\FinancialReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialReconciliationController extends Controller
{
    public function index(
        Request $request,
        FinancialReconciliationService $reconciliation,
        AccountingFirebreakService $firebreak,
    ): View {
        $tenantId = max(0, (int) $request->query('tenant', 0));
        $tenantFilter = $tenantId > 0 ? $tenantId : null;
        $report = $reconciliation->reconcile($tenantFilter, 100);
        $summary = $report['summary'] ?? [];
        $auditLogs = $firebreak->recentAuditLogs(30)
            ->filter(fn ($log) => str_starts_with((string) $log->action, 'fin_recon_')
                || (string) $log->action === 'financial_reconciliation_scan');

        $stats = [
            ['label' => 'Total mismatches', 'value' => (string) ((int) ($summary['total_mismatches'] ?? 0)), 'hint' => 'All reconciliation layers'],
            ['label' => 'Critical', 'value' => (string) ((int) ($summary['critical'] ?? 0)), 'hint' => 'Drift > KES 1,000'],
            ['label' => 'Warning', 'value' => (string) ((int) ($summary['warning'] ?? 0)), 'hint' => 'Drift > KES 100'],
            ['label' => 'Info', 'value' => (string) ((int) ($summary['info'] ?? 0)), 'hint' => 'Drift > tolerance'],
            ['label' => 'Layers scanned', 'value' => (string) count($report['layers'] ?? []), 'hint' => 'Operational vs GL'],
            ['label' => 'Last run', 'value' => isset($report['run_at']) ? \Carbon\Carbon::parse($report['run_at'])->diffForHumans() : '—', 'hint' => 'On page load'],
        ];

        $severityColors = [
            FinancialReconciliationService::SEVERITY_CRITICAL => 'bg-rose-200 text-rose-900',
            FinancialReconciliationService::SEVERITY_WARNING => 'bg-amber-200 text-amber-900',
            FinancialReconciliationService::SEVERITY_INFO => 'bg-sky-200 text-sky-900',
        ];

        return property_view('property.agent.accounting.financial_reconciliation', [
            'stats' => $stats,
            'tenantFilter' => $tenantFilter,
            'report' => $report,
            'summary' => $summary,
            'auditLogs' => $auditLogs,
            'severityColors' => $severityColors,
        ]);
    }
}
