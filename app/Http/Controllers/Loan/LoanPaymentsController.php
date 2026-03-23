<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Services\LoanBookGlPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanPaymentsController extends Controller
{
    private function assignReference(LoanBookPayment $payment): void
    {
        $payment->update([
            'reference' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function unposted(): View
    {
        $payments = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->unpostedQueue()
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.unposted', compact('payments'));
    }

    public function processed(): View
    {
        $payments = LoanBookPayment::query()
            ->with(['loan.loanClient', 'postedByUser', 'validatedByUser', 'accountingJournalEntry'])
            ->processedQueue()
            ->orderByDesc('posted_at')
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.processed', compact('payments'));
    }

    public function prepayments(): View
    {
        $payments = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_PREPAYMENT)
            ->notMergedChild()
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.prepayments', compact('payments'));
    }

    public function overpayments(): View
    {
        $payments = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_OVERPAYMENT)
            ->notMergedChild()
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.overpayments', compact('payments'));
    }

    public function merged(): View
    {
        $payments = LoanBookPayment::query()
            ->with(['loan.loanClient', 'mergedChildren'])
            ->where('payment_kind', LoanBookPayment::KIND_MERGED)
            ->notMergedChild()
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('loan.payments.merged', compact('payments'));
    }

    public function c2bReversals(): View
    {
        $payments = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_C2B_REVERSAL)
            ->notMergedChild()
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.c2b-reversals', compact('payments'));
    }

    public function receipts(): View
    {
        $payments = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->whereNotNull('mpesa_receipt_number')
            ->notMergedChild()
            ->orderByDesc('transaction_at')
            ->paginate(20);

        return view('loan.payments.receipts', compact('payments'));
    }

    public function payinSummary(Request $request): View
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();

        $base = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->notMergedChild()
            ->whereBetween('transaction_at', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ]);

        $byChannel = (clone $base)
            ->selectRaw('channel, SUM(amount) as total_amount, COUNT(*) as payment_count')
            ->groupBy('channel')
            ->orderByDesc('total_amount')
            ->get();

        $totals = [
            'amount' => (clone $base)->sum('amount'),
            'count' => (clone $base)->count(),
        ];

        return view('loan.payments.payin-summary', compact('from', 'to', 'byChannel', 'totals'));
    }

    public function report(Request $request): View
    {
        $query = $this->reportQuery($request);

        $payments = (clone $query)
            ->with(['loan.loanClient', 'postedByUser'])
            ->orderByDesc('transaction_at')
            ->paginate(30)
            ->withQueryString();

        return view('loan.payments.report', compact('payments'));
    }

    public function reportExport(Request $request): StreamedResponse
    {
        $rows = $this->reportQuery($request)
            ->with('loan')
            ->orderByDesc('transaction_at')
            ->get();

        $filename = 'payments-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Date', 'Loan', 'Amount', 'Channel', 'Status', 'Kind', 'Receipt', 'Posted at']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->reference,
                    $p->transaction_at->format('Y-m-d H:i'),
                    $p->loan?->loan_number ?? '',
                    $p->amount,
                    $p->channel,
                    $p->status,
                    $p->payment_kind,
                    $p->mpesa_receipt_number ?? '',
                    $p->posted_at?->format('Y-m-d H:i') ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function reportQuery(Request $request)
    {
        $q = LoanBookPayment::query()->notMergedChild();

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('kind')) {
            $q->where('payment_kind', $request->string('kind'));
        }
        if ($request->filled('channel')) {
            $q->where('channel', $request->string('channel'));
        }
        if ($request->filled('from')) {
            $q->where('transaction_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $q->where('transaction_at', '<=', $request->date('to')->endOfDay());
        }

        return $q;
    }

    public function validateForm(): View
    {
        return view('loan.payments.validate');
    }

    public function validateStore(Request $request): RedirectResponse
    {
        $request->validate([
            'lookup' => ['required', 'string', 'max:120'],
        ]);

        $key = trim($request->input('lookup', ''));

        $payment = LoanBookPayment::query()
            ->notMergedChild()
            ->where(function ($q) use ($key) {
                $q->where('reference', $key)->orWhere('mpesa_receipt_number', $key);
            })
            ->first();

        if (! $payment) {
            return back()->withErrors(['lookup' => 'No payment found for that reference or receipt.'])->withInput();
        }

        if ($payment->status !== LoanBookPayment::STATUS_PROCESSED) {
            return back()->withErrors(['lookup' => 'Only processed payments can be validated.'])->withInput();
        }

        $payment->update([
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('loan.payments.validate')
            ->with('status', 'Payment '.$payment->reference.' validated.');
    }

    public function mergeForm(): View
    {
        $candidates = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->unpostedQueue()
            ->where('payment_kind', '!=', LoanBookPayment::KIND_MERGED)
            ->orderBy('transaction_at')
            ->get();

        return view('loan.payments.merge', compact('candidates'));
    }

    public function mergeStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_ids' => ['required', 'array', 'min:2'],
            'payment_ids.*' => ['integer', 'exists:loan_book_payments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ids = $validated['payment_ids'];
        $rows = LoanBookPayment::query()->whereIn('id', $ids)->get();

        foreach ($rows as $row) {
            if (! $row->canEdit() || $row->payment_kind === LoanBookPayment::KIND_MERGED) {
                return back()->withErrors(['payment_ids' => 'One or more payments cannot be merged.'])->withInput();
            }
        }

        $total = $rows->sum('amount');
        $firstLoanId = $rows->first()->loan_book_loan_id;

        DB::transaction(function () use ($rows, $total, $firstLoanId, $validated, $request) {
            $parent = LoanBookPayment::create([
                'reference' => null,
                'loan_book_loan_id' => $firstLoanId,
                'amount' => $total,
                'currency' => $rows->first()->currency,
                'channel' => 'merged',
                'status' => LoanBookPayment::STATUS_UNPOSTED,
                'payment_kind' => LoanBookPayment::KIND_MERGED,
                'transaction_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $this->assignReference($parent);

            foreach ($rows as $row) {
                $row->update(['merged_into_payment_id' => $parent->id]);
            }
        });

        return redirect()
            ->route('loan.payments.merged')
            ->with('status', 'Payments merged into a new parent row. Post it from Unposted when ready.');
    }

    public function create(): View
    {
        $loans = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number')
            ->get();

        return view('loan.payments.create', compact('loans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['nullable', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'channel' => ['required', 'in:cash,mpesa,bank,cheque,card'],
            'payment_kind' => ['required', 'in:normal,prepayment,overpayment'],
            'mpesa_receipt_number' => ['nullable', 'string', 'max:80'],
            'payer_msisdn' => ['nullable', 'string', 'max:40'],
            'transaction_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['channel'] === 'mpesa') {
            if (blank($validated['mpesa_receipt_number'] ?? null)) {
                return back()->withErrors(['mpesa_receipt_number' => 'M-Pesa receipt is required for M-Pesa channel.'])->withInput();
            }
            if (blank($validated['payer_msisdn'] ?? null)) {
                return back()->withErrors(['payer_msisdn' => 'Payer MSISDN is required for M-Pesa channel.'])->withInput();
            }
        }

        $payment = LoanBookPayment::create([
            'reference' => null,
            'loan_book_loan_id' => $validated['loan_book_loan_id'] ?? null,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'KES',
            'channel' => $validated['channel'],
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => $validated['payment_kind'],
            'mpesa_receipt_number' => $validated['mpesa_receipt_number'] ?? null,
            'payer_msisdn' => $validated['payer_msisdn'] ?? null,
            'transaction_at' => $validated['transaction_at'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->assignReference($payment);

        return redirect()
            ->route('loan.payments.unposted')
            ->with('status', 'Payment '.$payment->reference.' created (unposted).');
    }

    public function reversalCreate(Request $request): View
    {
        $loans = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE])
            ->orderBy('loan_number')
            ->get();

        $original = null;
        if ($request->filled('from')) {
            $original = LoanBookPayment::query()
                ->with('loan')
                ->where('status', LoanBookPayment::STATUS_PROCESSED)
                ->find($request->integer('from'));
        }

        return view('loan.payments.reversal-create', compact('loans', 'original'));
    }

    public function reversalStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'lt:0'],
            'mpesa_receipt_number' => ['nullable', 'string', 'max:80'],
            'payer_msisdn' => ['nullable', 'string', 'max:40'],
            'transaction_at' => ['required', 'date'],
            'original_payment_id' => ['nullable', 'exists:loan_book_payments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = LoanBookPayment::create([
            'reference' => null,
            'loan_book_loan_id' => $validated['loan_book_loan_id'],
            'amount' => $validated['amount'],
            'currency' => 'KES',
            'channel' => 'mpesa',
            'status' => LoanBookPayment::STATUS_UNPOSTED,
            'payment_kind' => LoanBookPayment::KIND_C2B_REVERSAL,
            'mpesa_receipt_number' => $validated['mpesa_receipt_number'] ?? null,
            'payer_msisdn' => $validated['payer_msisdn'] ?? null,
            'transaction_at' => $validated['transaction_at'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        $this->assignReference($payment);

        return redirect()
            ->route('loan.payments.c2b_reversals')
            ->with('status', 'C2B reversal '.$payment->reference.' recorded (unposted). Post when confirmed.');
    }

    public function edit(LoanBookPayment $loan_book_payment): View
    {
        abort_unless($loan_book_payment->canEdit(), 403);

        $loans = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number')
            ->get();

        return view('loan.payments.edit', ['payment' => $loan_book_payment, 'loans' => $loans]);
    }

    public function update(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        abort_unless($loan_book_payment->canEdit(), 403);

        $validated = $request->validate([
            'loan_book_loan_id' => ['nullable', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'channel' => ['required', 'in:cash,mpesa,bank,cheque,card'],
            'payment_kind' => ['required', 'in:normal,prepayment,overpayment,c2b_reversal'],
            'mpesa_receipt_number' => ['nullable', 'string', 'max:80'],
            'payer_msisdn' => ['nullable', 'string', 'max:40'],
            'transaction_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['channel'] === 'mpesa') {
            if (blank($validated['mpesa_receipt_number'] ?? null)) {
                return back()->withErrors(['mpesa_receipt_number' => 'M-Pesa receipt is required for M-Pesa channel.'])->withInput();
            }
            if (blank($validated['payer_msisdn'] ?? null)) {
                return back()->withErrors(['payer_msisdn' => 'Payer MSISDN is required for M-Pesa channel.'])->withInput();
            }
        }

        $loan_book_payment->update($validated);

        return redirect()
            ->route('loan.payments.unposted')
            ->with('status', 'Payment updated.');
    }

    public function destroy(LoanBookPayment $loan_book_payment): RedirectResponse
    {
        abort_unless($loan_book_payment->canEdit(), 403);

        $loan_book_payment->delete();

        return redirect()
            ->back()
            ->with('status', 'Payment deleted.');
    }

    public function post(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        abort_unless($loan_book_payment->status === LoanBookPayment::STATUS_UNPOSTED, 403);
        abort_unless($loan_book_payment->merged_into_payment_id === null, 403);

        try {
            DB::transaction(function () use ($request, $loan_book_payment) {
                $payment = LoanBookPayment::query()->lockForUpdate()->findOrFail($loan_book_payment->id);
                if ($payment->accounting_journal_entry_id) {
                    throw new \RuntimeException('This payment is already linked to a journal entry.');
                }
                if ($payment->status !== LoanBookPayment::STATUS_UNPOSTED) {
                    throw new \RuntimeException('This payment is no longer unposted.');
                }
                $payment->load('loan');
                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment, $request->user());
                $payment->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => $request->user()->id,
                    'accounting_journal_entry_id' => $entry->id,
                ]);
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['accounting' => $e->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('status', 'Payment posted as processed and recorded in the general ledger.');
    }
}
