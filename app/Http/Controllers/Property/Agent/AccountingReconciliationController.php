<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Services\Property\AccountingFirebreakService;
use App\Services\Property\CarryForwardAccountingService;
use App\Services\Property\LandlordSubledgerService;
use App\Services\Property\ReversalIntegrityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingReconciliationController extends Controller
{
    public function index(
        Request $request,
        AccountingFirebreakService $firebreak,
        CarryForwardAccountingService $carryForwardAccounting,
        LandlordSubledgerService $landlordSubledger,
        ReversalIntegrityService $reversalIntegrity,
    ): View {
        $tenantId = max(0, (int) $request->query('tenant', 0));
        $tenantFilter = $tenantId > 0 ? $tenantId : null;
        $snapshot = $firebreak->diagnosticsSnapshot($tenantFilter, 100);
        $reversalSnapshot = $reversalIntegrity->diagnosticsSnapshot($tenantFilter, 100);
        $carryForwardArDrift = $carryForwardAccounting->reconcileOperationalArVsGl($tenantFilter, 100);
        $landlordGlDrift = $landlordSubledger->reconcileGl2100VsSubledger(null, 100);
        $duplicateLandlordCredits = $landlordSubledger->detectDuplicateCredits(100);
        $auditLogs = $firebreak->recentAuditLogs(30);

        $issueKeys = [
            'carry_forward_missing_invoice_issued',
            'utility_missing_invoice_issued',
            'invoices_missing_gl_batch',
            'landlord_ledger_gaps',
            'suspense_double_post_risk',
            'allocation_gl_drift',
            'cash_double_debit',
            'negative_landlord_payable',
            'invoice_without_ar',
            'payment_without_cash',
        ];

        $reversalIssueKeys = [
            'credit_notes_missing_credit_memo',
            'reversed_payments_active_gl',
            'reversed_payments_unreversed_tenant_credit',
            'cancelled_invoices_unreversed_gl',
            'cancelled_invoices_unreversed_penalties',
            'orphan_payment_landlord_credits',
        ];

        $totalIssues = 0;
        if (($snapshot['ready'] ?? false) === true) {
            foreach ($issueKeys as $key) {
                $totalIssues += ($snapshot[$key] ?? collect())->count();
            }
        }
        $reversalIssues = 0;
        if (($reversalSnapshot['ready'] ?? false) === true) {
            foreach ($reversalIssueKeys as $key) {
                $reversalIssues += ($reversalSnapshot[$key] ?? collect())->count();
            }
        }

        $stats = [
            ['label' => 'Total accounting issues', 'value' => (string) $totalIssues, 'hint' => 'All detector categories'],
            ['label' => 'Reversal integrity issues', 'value' => (string) $reversalIssues, 'hint' => 'Credit memos, unreversed GL'],
            ['label' => 'Missing GL issuance', 'value' => (string) (($snapshot['carry_forward_missing_invoice_issued'] ?? collect())->count()
                + ($snapshot['utility_missing_invoice_issued'] ?? collect())->count()
                + ($snapshot['invoices_missing_gl_batch'] ?? collect())->count()), 'hint' => 'invoice_issued batches'],
            ['label' => 'Landlord ledger gaps', 'value' => (string) ($snapshot['landlord_ledger_gaps'] ?? collect())->count(), 'hint' => 'GL posted, no ledger'],
            ['label' => 'Suspense double-post', 'value' => (string) ($snapshot['suspense_double_post_risk'] ?? collect())->count(), 'hint' => 'received + suspense'],
            ['label' => 'Allocation / GL drift', 'value' => (string) ($snapshot['allocation_gl_drift'] ?? collect())->count(), 'hint' => 'Ops vs GL'],
            ['label' => 'Impossible GL states', 'value' => (string) (($snapshot['cash_double_debit'] ?? collect())->count()
                + ($snapshot['negative_landlord_payable'] ?? collect())->count()
                + ($snapshot['invoice_without_ar'] ?? collect())->count()
                + ($snapshot['payment_without_cash'] ?? collect())->count()), 'hint' => 'Cash, AR, payable'],
            ['label' => 'CF AR drift', 'value' => (string) $carryForwardArDrift->count(), 'hint' => 'Ops vs Trust GL'],
            ['label' => 'GL 2100 drift', 'value' => (string) $landlordGlDrift->count(), 'hint' => 'GL vs subledger'],
            ['label' => 'Duplicate owner credits', 'value' => (string) $duplicateLandlordCredits->count(), 'hint' => 'Landlord subledger'],
        ];

        return property_view('property.agent.accounting.accounting_reconciliation', [
            'stats' => $stats,
            'tenantFilter' => $tenantFilter,
            'snapshot' => $snapshot,
            'reversalSnapshot' => $reversalSnapshot,
            'carryForwardArDrift' => $carryForwardArDrift,
            'landlordGlDrift' => $landlordGlDrift,
            'duplicateLandlordCredits' => $duplicateLandlordCredits,
            'auditLogs' => $auditLogs,
            'sections' => [
                'carry_forward_missing_invoice_issued' => 'Carry-forward invoices missing invoice_issued',
                'utility_missing_invoice_issued' => 'Utility invoices missing invoice_issued',
                'invoices_missing_gl_batch' => 'Billable invoices missing GL batch (amount > 0)',
                'landlord_ledger_gaps' => 'Payments with GL receipt but no landlord ledger',
                'suspense_double_post_risk' => 'Payments with payment_received AND suspense batches',
                'allocation_gl_drift' => 'Allocation vs GL AR / tenant credit drift',
                'cash_double_debit' => 'Cash double debit (cash debits exceed payment)',
                'negative_landlord_payable' => 'Negative landlord payable balance',
                'invoice_without_ar' => 'Invoice issued batch without AR debit',
                'payment_without_cash' => 'Payment received batch without cash debit',
            ],
            'reversalSections' => [
                'credit_notes_missing_credit_memo' => 'Credit notes missing credit_memo_issued batch',
                'reversed_payments_active_gl' => 'Reversed payments with active GL receipt/suspense',
                'reversed_payments_unreversed_tenant_credit' => 'Reversed payments with unreversed tenant credit',
                'cancelled_invoices_unreversed_gl' => 'Cancelled invoices with unreversed issuance GL',
                'cancelled_invoices_unreversed_penalties' => 'Cancelled invoices with unreversed penalties',
                'orphan_payment_landlord_credits' => 'Reversed payments with orphan landlord subledger credits',
            ],
        ]);
    }
}
