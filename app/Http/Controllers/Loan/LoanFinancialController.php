<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\FinancialAccount;
use App\Models\InvestmentPackage;
use App\Models\Investor;
use App\Models\MpesaPayoutBatch;
use App\Models\MpesaPlatformTransaction;
use App\Models\TellerMovement;
use App\Models\TellerSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanFinancialController extends Controller
{
    public function mpesaPlatform(): View
    {
        $transactions = MpesaPlatformTransaction::query()->latest()->paginate(15);

        $stkTodaySum = MpesaPlatformTransaction::query()
            ->where('channel', 'stk_push')
            ->whereDate('created_at', today())
            ->sum('amount');

        $c2b24hSum = MpesaPlatformTransaction::query()
            ->where('channel', 'c2b')
            ->where('created_at', '>=', now()->subDay())
            ->sum('amount');

        $failedCount = MpesaPlatformTransaction::query()->where('status', 'failed')->count();

        return view('loan.financial.mpesa_platform', [
            'title' => 'M-Pesa platform',
            'subtitle' => 'Log STK, C2B, and B2C-style movements; reconcile before syncing Daraja webhooks.',
            'transactions' => $transactions,
            'stkTodaySum' => $stkTodaySum,
            'c2b24hSum' => $c2b24hSum,
            'failedCount' => $failedCount,
        ]);
    }

    public function mpesaPlatformTransactionStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'channel' => ['required', 'in:stk_push,c2b,b2c'],
            'status' => ['required', 'in:pending,completed,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        MpesaPlatformTransaction::create($data);

        return redirect()
            ->route('loan.financial.mpesa_platform')
            ->with('status', 'Transaction recorded.');
    }

    public function mpesaPlatformTransactionDestroy(MpesaPlatformTransaction $mpesa_platform_transaction): RedirectResponse
    {
        $mpesa_platform_transaction->delete();

        return redirect()
            ->route('loan.financial.mpesa_platform')
            ->with('status', 'Transaction removed.');
    }

    public function mpesaPayouts(): View
    {
        $batches = MpesaPayoutBatch::query()->latest()->paginate(15);

        return view('loan.financial.mpesa_payouts', [
            'title' => 'M-Pesa payouts',
            'subtitle' => 'Create and track payout batches before connecting live B2C.',
            'batches' => $batches,
        ]);
    }

    public function mpesaPayoutsCreate(): View
    {
        return view('loan.financial.mpesa_payouts_create', [
            'title' => 'New payout batch',
            'subtitle' => 'Reference is generated if you leave it blank.',
            'batch' => new MpesaPayoutBatch(['status' => 'draft', 'recipient_count' => 1]),
        ]);
    }

    public function mpesaPayoutsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:80', 'unique:mpesa_payout_batches,reference'],
            'recipient_count' => ['required', 'integer', 'min:1'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'in:draft,queued,sent,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($data['reference'])) {
            $data['reference'] = 'POB-'.strtoupper(bin2hex(random_bytes(4)));
        }

        $batch = MpesaPayoutBatch::create($data);

        return redirect()
            ->route('loan.financial.mpesa_payouts.edit', $batch)
            ->with('status', 'Batch created. Update status as it progresses.');
    }

    public function mpesaPayoutsEdit(MpesaPayoutBatch $mpesa_payout_batch): View
    {
        return view('loan.financial.mpesa_payouts_edit', [
            'title' => 'Edit payout batch',
            'subtitle' => $mpesa_payout_batch->reference,
            'batch' => $mpesa_payout_batch,
        ]);
    }

    public function mpesaPayoutsUpdate(Request $request, MpesaPayoutBatch $mpesa_payout_batch): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:80', 'unique:mpesa_payout_batches,reference,'.$mpesa_payout_batch->id],
            'recipient_count' => ['required', 'integer', 'min:1'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'in:draft,queued,sent,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mpesa_payout_batch->update($data);

        return redirect()
            ->route('loan.financial.mpesa_payouts.edit', $mpesa_payout_batch)
            ->with('status', 'Batch updated.');
    }

    public function mpesaPayoutsDestroy(MpesaPayoutBatch $mpesa_payout_batch): RedirectResponse
    {
        $mpesa_payout_batch->delete();

        return redirect()
            ->route('loan.financial.mpesa_payouts')
            ->with('status', 'Batch removed.');
    }

    public function accountBalances(): View
    {
        $accounts = FinancialAccount::query()->orderBy('name')->paginate(15);

        return view('loan.financial.account_balances', [
            'title' => 'Account balances',
            'subtitle' => 'Bank, float, and control accounts used in reporting.',
            'accounts' => $accounts,
        ]);
    }

    public function financialAccountsCreate(): View
    {
        return view('loan.financial.accounts_form', [
            'title' => 'Add account',
            'subtitle' => 'Create a ledger line for balances.',
            'account' => new FinancialAccount(['currency' => 'KES', 'balance' => 0]),
            'action' => route('loan.financial.accounts.store'),
            'method' => 'post',
        ]);
    }

    public function financialAccountsStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'account_type' => ['required', 'string', 'max:60'],
            'currency' => ['required', 'string', 'max:10'],
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        FinancialAccount::create($data);

        return redirect()
            ->route('loan.financial.account_balances')
            ->with('status', 'Account added.');
    }

    public function financialAccountsEdit(FinancialAccount $financial_account): View
    {
        return view('loan.financial.accounts_form', [
            'title' => 'Edit account',
            'subtitle' => $financial_account->name,
            'account' => $financial_account,
            'action' => route('loan.financial.accounts.update', $financial_account),
            'method' => 'patch',
        ]);
    }

    public function financialAccountsUpdate(Request $request, FinancialAccount $financial_account): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'account_type' => ['required', 'string', 'max:60'],
            'currency' => ['required', 'string', 'max:10'],
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        $financial_account->update($data);

        return redirect()
            ->route('loan.financial.account_balances')
            ->with('status', 'Account updated.');
    }

    public function financialAccountsDestroy(FinancialAccount $financial_account): RedirectResponse
    {
        $financial_account->delete();

        return redirect()
            ->route('loan.financial.account_balances')
            ->with('status', 'Account deleted.');
    }

    public function investmentPackages(): View
    {
        $packages = InvestmentPackage::query()->orderByDesc('id')->paginate(15);

        return view('loan.financial.investment_packages', [
            'title' => 'Investment packages',
            'subtitle' => 'Products offered to investors.',
            'packages' => $packages,
        ]);
    }

    public function investmentPackagesCreate(): View
    {
        return view('loan.financial.packages_form', [
            'title' => 'New package',
            'subtitle' => 'Rates and minimums are free-text until pricing rules are fixed.',
            'package' => new InvestmentPackage([
                'status' => 'draft',
                'rate_label' => '',
                'minimum_label' => '',
            ]),
            'action' => route('loan.financial.packages.store'),
            'method' => 'post',
        ]);
    }

    public function investmentPackagesStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'rate_label' => ['required', 'string', 'max:80'],
            'minimum_label' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,draft'],
        ]);

        InvestmentPackage::create($data);

        return redirect()
            ->route('loan.financial.investment_packages')
            ->with('status', 'Package created.');
    }

    public function investmentPackagesEdit(InvestmentPackage $investment_package): View
    {
        return view('loan.financial.packages_form', [
            'title' => 'Edit package',
            'subtitle' => $investment_package->name,
            'package' => $investment_package,
            'action' => route('loan.financial.packages.update', $investment_package),
            'method' => 'patch',
        ]);
    }

    public function investmentPackagesUpdate(Request $request, InvestmentPackage $investment_package): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'rate_label' => ['required', 'string', 'max:80'],
            'minimum_label' => ['required', 'string', 'max:80'],
            'status' => ['required', 'in:active,draft'],
        ]);

        $investment_package->update($data);

        return redirect()
            ->route('loan.financial.investment_packages')
            ->with('status', 'Package updated.');
    }

    public function investmentPackagesDestroy(InvestmentPackage $investment_package): RedirectResponse
    {
        $investment_package->delete();

        return redirect()
            ->route('loan.financial.investment_packages')
            ->with('status', 'Package deleted.');
    }

    public function investorsList(): View
    {
        $investors = Investor::query()
            ->with('investmentPackage')
            ->orderByDesc('id')
            ->paginate(15);

        return view('loan.financial.investors_list', [
            'title' => 'Investors list',
            'subtitle' => 'Link each investor to a package where applicable.',
            'investors' => $investors,
        ]);
    }

    public function investorsCreate(): View
    {
        return view('loan.financial.investors_form', [
            'title' => 'Add investor',
            'subtitle' => 'Committed amount and maturity power reports.',
            'investor' => new Investor,
            'packages' => InvestmentPackage::query()->orderBy('name')->get(),
            'action' => route('loan.financial.investors.store'),
            'method' => 'post',
        ]);
    }

    public function investorsStore(Request $request): RedirectResponse
    {
        $this->normalizeInvestorRequest($request);

        $data = $request->validate([
            'investment_package_id' => ['nullable', 'exists:investment_packages,id'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'accrued_interest' => ['nullable', 'numeric', 'min:0'],
            'maturity_date' => ['nullable', 'date'],
        ]);

        $data['accrued_interest'] = $data['accrued_interest'] ?? 0;

        Investor::create($data);

        return redirect()
            ->route('loan.financial.investors_list')
            ->with('status', 'Investor saved.');
    }

    public function investorsEdit(Investor $investor): View
    {
        return view('loan.financial.investors_form', [
            'title' => 'Edit investor',
            'subtitle' => $investor->name,
            'investor' => $investor,
            'packages' => InvestmentPackage::query()->orderBy('name')->get(),
            'action' => route('loan.financial.investors.update', $investor),
            'method' => 'patch',
        ]);
    }

    public function investorsUpdate(Request $request, Investor $investor): RedirectResponse
    {
        $this->normalizeInvestorRequest($request);

        $data = $request->validate([
            'investment_package_id' => ['nullable', 'exists:investment_packages,id'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'committed_amount' => ['nullable', 'numeric', 'min:0'],
            'accrued_interest' => ['nullable', 'numeric', 'min:0'],
            'maturity_date' => ['nullable', 'date'],
        ]);

        $data['accrued_interest'] = $data['accrued_interest'] ?? 0;

        $investor->update($data);

        return redirect()
            ->route('loan.financial.investors_list')
            ->with('status', 'Investor updated.');
    }

    public function investorsDestroy(Investor $investor): RedirectResponse
    {
        $investor->delete();

        return redirect()
            ->route('loan.financial.investors_list')
            ->with('status', 'Investor removed.');
    }

    public function tellerOperations(): View
    {
        $openSessions = TellerSession::query()
            ->whereNull('closed_at')
            ->latest()
            ->get();

        $recentSessions = TellerSession::query()
            ->whereNotNull('closed_at')
            ->latest()
            ->limit(10)
            ->get();

        $todayStart = today();
        $cashInToday = TellerMovement::query()
            ->whereHas('tellerSession', fn ($q) => $q->whereDate('created_at', $todayStart))
            ->where('kind', 'cash_in')
            ->sum('amount');
        $cashOutToday = TellerMovement::query()
            ->whereHas('tellerSession', fn ($q) => $q->whereDate('created_at', $todayStart))
            ->where('kind', 'cash_out')
            ->sum('amount');

        return view('loan.financial.teller_operations', [
            'title' => 'Teller operations',
            'subtitle' => 'Open tills, post cash in/out, then close with a counted float.',
            'openSessions' => $openSessions,
            'recentSessions' => $recentSessions,
            'cashInToday' => $cashInToday,
            'cashOutToday' => $cashOutToday,
        ]);
    }

    public function tellerSessionStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_label' => ['required', 'string', 'max:120'],
            'opened_by' => ['nullable', 'string', 'max:120'],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);

        $session = TellerSession::create($data);

        return redirect()
            ->route('loan.financial.teller_sessions.show', $session)
            ->with('status', 'Till opened. Record movements, then close the session.');
    }

    public function tellerSessionShow(TellerSession $teller_session): View
    {
        $teller_session->load(['movements' => fn ($q) => $q->latest()]);

        return view('loan.financial.teller_session_show', [
            'title' => 'Till session',
            'subtitle' => $teller_session->branch_label.' · '.($teller_session->isOpen() ? 'Open' : 'Closed'),
            'session' => $teller_session,
        ]);
    }

    public function tellerMovementStore(Request $request, TellerSession $teller_session): RedirectResponse
    {
        if (! $teller_session->isOpen()) {
            return redirect()
                ->route('loan.financial.teller_sessions.show', $teller_session)
                ->with('error', 'This till is already closed.');
        }

        $data = $request->validate([
            'kind' => ['required', 'in:cash_in,cash_out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $teller_session->movements()->create($data);

        return redirect()
            ->route('loan.financial.teller_sessions.show', $teller_session)
            ->with('status', 'Movement recorded.');
    }

    public function tellerSessionClose(Request $request, TellerSession $teller_session): RedirectResponse
    {
        if (! $teller_session->isOpen()) {
            return redirect()
                ->route('loan.financial.teller_sessions.show', $teller_session)
                ->with('error', 'Session already closed.');
        }

        $data = $request->validate([
            'closing_float' => ['required', 'numeric', 'min:0'],
        ]);

        $teller_session->update([
            'closing_float' => $data['closing_float'],
            'closed_at' => now(),
        ]);

        return redirect()
            ->route('loan.financial.teller_operations')
            ->with('status', 'Till closed.');
    }

    public function investorsReports(): View
    {
        $principal = (float) Investor::query()->sum('committed_amount');
        $accrued = (float) Investor::query()->sum('accrued_interest');
        $investorCount = Investor::query()->count();
        $maturingCount = Investor::query()
            ->whereNotNull('maturity_date')
            ->whereBetween('maturity_date', [today(), today()->copy()->addDays(30)])
            ->count();

        return view('loan.financial.investors_reports', [
            'title' => 'Investors reports',
            'subtitle' => 'Totals from saved investors; export CSV for statements and maturities.',
            'principalOutstanding' => $principal,
            'accruedInterest' => $accrued,
            'maturing30d' => $maturingCount,
            'investorCount' => $investorCount,
        ]);
    }

    public function investorsReportsStatementCsv(): StreamedResponse
    {
        $filename = 'investor-statements-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Phone', 'Package', 'Committed', 'Accrued interest', 'Maturity']);

            Investor::query()
                ->with('investmentPackage')
                ->orderBy('name')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $inv) {
                        fputcsv($out, [
                            $inv->name,
                            $inv->email,
                            $inv->phone,
                            $inv->investmentPackage?->name,
                            $inv->committed_amount,
                            $inv->accrued_interest,
                            optional($inv->maturity_date)?->format('Y-m-d'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function investorsReportsMaturityCsv(): StreamedResponse
    {
        $filename = 'maturity-schedule-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Package', 'Committed', 'Maturity date', 'Days to maturity']);

            $today = today()->startOfDay();

            Investor::query()
                ->with('investmentPackage')
                ->whereNotNull('maturity_date')
                ->orderBy('maturity_date')
                ->chunk(200, function ($rows) use ($out, $today) {
                    foreach ($rows as $inv) {
                        $m = $inv->maturity_date;
                        $days = $m ? (int) $today->diffInDays($m->copy()->startOfDay(), false) : '';
                        fputcsv($out, [
                            $inv->name,
                            $inv->investmentPackage?->name,
                            $inv->committed_amount,
                            $m?->format('Y-m-d'),
                            $days,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeInvestorRequest(Request $request): void
    {
        $merge = [];

        if (! $request->filled('investment_package_id')) {
            $merge['investment_package_id'] = null;
        }

        foreach (['email', 'phone', 'committed_amount', 'maturity_date', 'accrued_interest'] as $field) {
            if ($request->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }
}
