<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Services\Property\FinanceFirebreakService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceDiagnosticsController extends Controller
{
    public function index(Request $request, FinanceFirebreakService $firebreak): View
    {
        $tenantId = max(0, (int) $request->query('tenant', 0));
        $tenantFilter = $tenantId > 0 ? $tenantId : null;
        $snapshot = $firebreak->diagnosticsSnapshot($tenantFilter);

        $stats = [
            ['label' => 'Allocation drift', 'value' => (string) $snapshot['allocation_drift']->count(), 'hint' => 'amount_paid vs allocations'],
            ['label' => 'Duplicated carry-forward', 'value' => (string) $snapshot['duplicated_carry_forward']->count(), 'hint' => 'Same lease/period'],
            ['label' => 'Protected carry-forward', 'value' => (string) $snapshot['recreated_after_payment']->count(), 'hint' => 'Paid invoices preserved'],
            ['label' => 'Stale opening arrears', 'value' => (string) $snapshot['stale_opening_arrears']->count(), 'hint' => 'Tenant JSON not invoiced'],
            ['label' => 'Partial overdue', 'value' => (string) $snapshot['partial_overdue']->count(), 'hint' => 'Partial + past due'],
            ['label' => 'Orphan allocations', 'value' => (string) $snapshot['orphan_allocations']->count(), 'hint' => 'Missing/cancelled targets'],
        ];

        return property_view('property.agent.accounting.finance_diagnostics', [
            'stats' => $stats,
            'tenantFilter' => $tenantFilter,
            'snapshot' => $snapshot,
        ]);
    }

    public function refreshInvoiceStatuses(Request $request): RedirectResponse
    {
        $limit = min(5000, max(100, (int) $request->input('limit', 2000)));
        $changed = PmInvoice::refreshStaleStatuses($limit);

        return redirect()
            ->route('property.accounting.finance_diagnostics')
            ->with('success', "Refreshed {$changed} invoice status(es).");
    }
}
