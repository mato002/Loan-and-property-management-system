<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmTenant;
use App\Models\PmTenantCreditBalance;
use App\Models\PmTenantCreditTransaction;
use App\Services\Property\PropertyMoney;
use App\Services\Property\TenantCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PmTenantCreditController extends Controller
{
    public function report(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = PmTenantCreditBalance::query()
            ->with('tenant')
            ->where('balance', '>', 0)
            ->orderByDesc('balance');

        if ($q !== '') {
            $query->whereHas('tenant', fn ($t) => $t
                ->where('name', 'like', '%'.$q.'%')
                ->orWhere('phone', 'like', '%'.$q.'%'));
        }

        $balances = $query->paginate(30)->withQueryString();
        $totalUnapplied = (float) PmTenantCreditBalance::query()->sum('balance');

        return property_view('property.agent.revenue.tenant_credits_report', [
            'balances' => $balances,
            'totalUnapplied' => $totalUnapplied,
            'filters' => ['q' => $q],
        ]);
    }

    public function ledger(PmTenant $tenant): View
    {
        $creditService = app(TenantCreditService::class);
        $transactions = $creditService->ledgerForTenant((int) $tenant->id, 40);
        $balance = $creditService->balanceForTenant((int) $tenant->id);
        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $tenant->id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        return property_view('property.agent.tenants.credit_ledger', [
            'tenant' => $tenant,
            'balance' => $balance,
            'transactions' => $transactions,
            'openInvoices' => $openInvoices,
            'advanceCreditsEnabled' => $creditService->isEnabled(),
        ]);
    }

    public function apply(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'pm_invoice_id' => ['required', 'exists:pm_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = PmInvoice::query()->findOrFail($data['pm_invoice_id']);
        try {
            app(TenantCreditService::class)->applyToInvoiceManual(
                (int) $tenant->id,
                $invoice,
                (float) $data['amount'],
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Credit applied to invoice '.$invoice->invoice_no.'.');
    }

    public function refund(Request $request, PmTenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        try {
            app(TenantCreditService::class)->refundCredit(
                (int) $tenant->id,
                (float) $data['amount'],
                $request->user(),
                $data['notes'] ?? null,
                $data['reference'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'Refunded '.PropertyMoney::kes((float) $data['amount']).' to tenant.');
    }

    public function autoApply(PmTenant $tenant): RedirectResponse
    {
        $applied = app(TenantCreditService::class)->autoApplyForTenant((int) $tenant->id, request()->user());
        $total = array_sum(array_column($applied, 'amount'));

        return back()->with('success', $total > 0
            ? 'Applied '.PropertyMoney::kes($total).' across '.count($applied).' invoice(s).'
            : 'No open invoices or no credit available to apply.');
    }
}
