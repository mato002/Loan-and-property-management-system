<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\ClientWallet;
use App\Models\ClientWalletRefundRequest;
use App\Models\ClientWalletTransaction;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Services\ClientWalletService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanClientWalletController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function payLoanCreate(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.pay_loan')) {
            abort(403);
        }

        $wallet = app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()?->id);
        if (! $wallet->isActive()) {
            return redirect()
                ->route('loan.clients.show', $loan_client)
                ->withErrors(['wallet' => 'Wallet must be active to pay from balance.']);
        }

        return redirect()->to(route('loan.clients.show', $loan_client).'?pay_loan=1#wallet');
    }

    public function payLoanStore(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.pay_loan')) {
            abort(403);
        }

        $payLoanRedirect = fn (): RedirectResponse => redirect()->to(route('loan.clients.show', $loan_client).'?pay_loan=1#wallet');

        $validator = Validator::make($request->all(), [
            'form_context' => ['nullable', 'string', 'max:32'],
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_kind' => ['nullable', 'in:normal,prepayment'],
        ]);
        if ($validator->fails()) {
            return $payLoanRedirect()->withErrors($validator)->withInput();
        }
        $validated = $validator->validated();

        $loan = LoanBookLoan::query()->with('loanClient')->findOrFail((int) $validated['loan_book_loan_id']);
        if ((int) $loan->loan_client_id !== (int) $loan_client->id) {
            return $payLoanRedirect()->withErrors(['loan_book_loan_id' => 'Loan does not belong to this client.'])->withInput();
        }
        if ($loan->status === LoanBookLoan::STATUS_WRITTEN_OFF) {
            return $payLoanRedirect()->withErrors(['loan_book_loan_id' => 'Cannot pay against a written-off loan.'])->withInput();
        }
        if (
            (float) $loan->balance <= 0.01
            && (float) $loan->principal_outstanding <= 0.01
            && (float) $loan->interest_outstanding <= 0.01
            && (float) $loan->fees_outstanding <= 0.01
        ) {
            return $payLoanRedirect()->withErrors(['loan_book_loan_id' => 'Loan has no outstanding balance to settle.'])->withInput();
        }

        $amount = round((float) $validated['amount'], 2);
        $paymentKind = (string) ($validated['payment_kind'] ?? LoanBookPayment::KIND_NORMAL);

        try {
            DB::transaction(function () use ($request, $loan_client, $loan, $amount, $paymentKind): void {
                $wallet = ClientWallet::query()
                    ->where('loan_client_id', $loan_client->id)
                    ->lockForUpdate()
                    ->first();
                if (! $wallet) {
                    app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()->id);
                    $wallet = ClientWallet::query()->where('loan_client_id', $loan_client->id)->lockForUpdate()->firstOrFail();
                }
                if (! $wallet->isActive()) {
                    throw new \RuntimeException('Wallet is not active.');
                }
                if (round((float) $wallet->balance, 2) + 0.0001 < $amount) {
                    throw new \RuntimeException('Insufficient wallet balance.');
                }

                $payment = LoanBookPayment::query()->create([
                    'reference' => null,
                    'loan_book_loan_id' => $loan->id,
                    'amount' => $amount,
                    'currency' => $wallet->currency ?: 'KES',
                    'channel' => 'wallet',
                    'status' => LoanBookPayment::STATUS_UNPOSTED,
                    'payment_kind' => $paymentKind,
                    'funded_from_wallet' => true,
                    'transaction_at' => now(),
                    'notes' => 'Wallet-funded repayment from client wallet.',
                    'created_by' => $request->user()->id,
                ]);
                $payment->update([
                    'reference' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                ]);

                $payment = LoanBookPayment::query()->lockForUpdate()->findOrFail($payment->id);
                $payment->load('loan');

                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment, $request->user());
                $payment->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => $request->user()->id,
                    'accounting_journal_entry_id' => $entry->id,
                ]);

                $this->syncCollectionEntryFromProcessedPayment($payment->fresh());
                app(LoanBookLoanUpdateService::class)->onPaymentProcessed($payment->fresh());
                app(ClientWalletService::class)->syncPostedPaymentWalletEffects($payment->fresh(['allocations', 'loan']));
            });
        } catch (\Throwable $e) {
            return $payLoanRedirect()
                ->withErrors(['wallet' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('loan.clients.show', $loan_client)
            ->with('status', 'Loan repayment posted from wallet balance.');
    }

    public function refundRequestCreate(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.refund_request')) {
            abort(403);
        }

        app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()?->id);

        return redirect()->to(route('loan.clients.show', $loan_client).'?refund_request=1#wallet');
    }

    public function refundRequestStore(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.refund_request')) {
            abort(403);
        }

        $validator = Validator::make($request->all(), [
            'form_context' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return redirect()->to(route('loan.clients.show', $loan_client).'?refund_request=1#wallet')
                ->withErrors($validator)
                ->withInput();
        }
        $validated = $validator->validated();

        $amount = round((float) $validated['amount'], 2);
        $wallet = app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()->id);
        if (round((float) $wallet->balance, 2) + 0.0001 < $amount) {
            return redirect()->to(route('loan.clients.show', $loan_client).'?refund_request=1#wallet')
                ->withErrors(['amount' => 'Refund amount cannot exceed wallet balance.'])
                ->withInput();
        }

        ClientWalletRefundRequest::query()->create([
            'client_wallet_id' => $wallet->id,
            'loan_client_id' => $loan_client->id,
            'amount' => $amount,
            'status' => ClientWalletRefundRequest::STATUS_PENDING,
            'notes' => $validated['notes'] ?? null,
            'requested_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('loan.clients.show', $loan_client)
            ->with('status', 'Refund request submitted. Awaiting approval before posting.');
    }

    public function freeze(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.freeze')) {
            abort(403);
        }

        $wallet = app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()->id);
        app(ClientWalletService::class)->setWalletStatus($wallet, ClientWallet::STATUS_FROZEN, $request->user()->id);

        return back()->with('status', 'Wallet frozen.');
    }

    public function unfreeze(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.freeze')) {
            abort(403);
        }

        $wallet = app(ClientWalletService::class)->ensureWallet($loan_client, $request->user()->id);
        app(ClientWalletService::class)->setWalletStatus($wallet, ClientWallet::STATUS_ACTIVE, $request->user()->id);

        return back()->with('status', 'Wallet reactivated.');
    }

    public function adjust(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.adjust')) {
            abort(403);
        }

        $validated = $request->validate([
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $ctx = [
            'reference' => 'ADJ-'.now()->format('YmdHis'),
            'description' => $validated['description'],
            'created_by' => $request->user()->id,
        ];

        try {
            if ($validated['direction'] === 'credit') {
                app(ClientWalletService::class)->creditWallet($loan_client, $amount, ClientWalletTransaction::SOURCE_ADJUSTMENT, $ctx);
            } else {
                app(ClientWalletService::class)->debitWallet($loan_client, $amount, ClientWalletTransaction::SOURCE_ADJUSTMENT, $ctx);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['adjust' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Wallet adjustment recorded.');
    }

    public function statementExport(Request $request, LoanClient $loan_client): StreamedResponse
    {
        $this->ensureLoanClientAccessible($loan_client);
        if (! $request->user()?->hasLoanPermission('wallets.view')) {
            abort(403);
        }

        $filename = 'wallet-statement-'.$loan_client->client_number.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($loan_client): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'Source', 'Description', 'Credit', 'Debit', 'Running balance', 'Reference']);

            $loan_client->walletTransactions()
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out): void {
                    foreach ($rows as $tx) {
                        $credit = $tx->transaction_type === ClientWalletTransaction::TYPE_CREDIT ? (float) $tx->amount : '';
                        $debit = $tx->transaction_type === ClientWalletTransaction::TYPE_DEBIT ? (float) $tx->amount : '';
                        fputcsv($out, [
                            optional($tx->created_at)->format('Y-m-d H:i'),
                            $tx->transaction_type,
                            $tx->source_type,
                            (string) ($tx->description ?? ''),
                            $credit,
                            $debit,
                            (float) $tx->running_balance,
                            (string) ($tx->reference ?? ''),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function syncCollectionEntryFromProcessedPayment(LoanBookPayment $payment): void
    {
        if (! $payment->loan_book_loan_id) {
            return;
        }

        $existing = LoanBookCollectionEntry::query()
            ->where('loan_book_loan_id', $payment->loan_book_loan_id)
            ->whereDate('collected_on', optional($payment->transaction_at)->toDateString() ?? now()->toDateString())
            ->where('amount', $payment->amount)
            ->where('channel', $payment->channel)
            ->where(function ($q) use ($payment) {
                if ($payment->accounting_journal_entry_id) {
                    $q->where('accounting_journal_entry_id', $payment->accounting_journal_entry_id);
                } else {
                    $q->whereNull('accounting_journal_entry_id');
                }
            })
            ->first();

        if ($existing) {
            return;
        }

        LoanBookCollectionEntry::query()->create([
            'loan_book_loan_id' => $payment->loan_book_loan_id,
            'collected_on' => optional($payment->transaction_at)->toDateString() ?? now()->toDateString(),
            'amount' => $payment->amount,
            'channel' => $payment->channel,
            'collected_by_employee_id' => null,
            'notes' => 'Auto-synced from processed payment '.($payment->reference ?? ('#'.$payment->id)),
            'accounting_journal_entry_id' => $payment->accounting_journal_entry_id,
        ]);
    }
}
