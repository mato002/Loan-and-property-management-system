<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\PropertyPortalSetting;
use App\Notifications\Loan\LoanWorkflowNotification;
use App\Support\TabularExport;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoanPaymentsController extends Controller
{
    use ScopesLoanPortfolioAccess;

    private function assignReference(LoanBookPayment $payment): void
    {
        $payment->update([
            'reference' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function unposted(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()->with('loan.loanClient')->unpostedQueue();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request);
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('transaction_at'), $request, 'payments-unposted')) {
            return $export;
        }
        $payments = $query->orderByDesc('transaction_at')->paginate($filters['perPage'])->withQueryString();
        $assignableLoans = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($assignableLoans, $request->user());
        $assignableLoans = $assignableLoans
            ->limit(500)
            ->get();
        $assignableLoanOptions = $assignableLoans
            ->mapWithKeys(fn (LoanBookLoan $loan) => [
                $loan->id => $loan->loan_number.' - '.($loan->loanClient?->full_name ?? 'Unknown client'),
            ]);
        $suggestedLoanByPayment = $this->buildSuggestedLoanMap($payments->getCollection(), $assignableLoans);

        return view('loan.payments.unposted', array_merge(compact('payments', 'assignableLoanOptions', 'suggestedLoanByPayment'), $filters));
    }

    public function unpostedPrint(Request $request): View
    {
        $query = LoanBookPayment::query()->with('loan.loanClient')->unpostedQueue();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request);

        $payments = $query
            ->orderByDesc('transaction_at')
            ->limit(5000)
            ->get();

        $totalAmount = (float) $payments->sum('amount');

        return view('loan.payments.unposted-print', [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
            'generatedAt' => now(),
            'generatedBy' => $request->user()?->name ?? 'System',
            'branchName' => 'Nakuru',
            'filters' => $filters,
        ]);
    }

    public function processed(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with(['loan.loanClient', 'postedByUser', 'validatedByUser', 'accountingJournalEntry.lines.account'])
            ->processedQueue();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $source = trim((string) $request->query('source', ''));
        $payMode = trim((string) $request->query('pay_mode', ''));
        $corporate = trim((string) $request->query('corporate', ''));
        $filters = $this->applyListFilters($query, $request);
        if ($source !== '') {
            $query->where(function (Builder $builder) use ($source): void {
                if ($source === 'sms_forwarder') {
                    $builder->where('channel', 'like', '%_sms_%');

                    return;
                }
                if ($source === 'manual') {
                    $builder->where('channel', 'not like', '%_sms_%');

                    return;
                }
                $builder->where('channel', $source);
            });
        }
        if ($payMode !== '') {
            $query->where('channel', $payMode);
        }
        if ($corporate !== '') {
            $query->whereHas('loan', fn (Builder $loan) => $loan->where('checkoff_employer', $corporate));
        }
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('posted_at')->orderByDesc('transaction_at'), $request, 'payments-processed')) {
            return $export;
        }
        $payments = $query
            ->orderByDesc('posted_at')
            ->orderByDesc('transaction_at')
            ->paginate($filters['perPage'])
            ->withQueryString();
        $totalAmount = (clone $query)->sum('amount');
        $displayDate = $to = trim((string) ($filters['to'] ?? ''));
        if ($displayDate === '') {
            $displayDate = now()->toDateString();
        }
        $corporateOptions = LoanBookLoan::query()
            ->whereNotNull('checkoff_employer')
            ->where('checkoff_employer', '!=', '')
            ->orderBy('checkoff_employer')
            ->distinct()
            ->pluck('checkoff_employer')
            ->values();
        $payModeOptions = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->orderBy('channel')
            ->distinct()
            ->pluck('channel')
            ->values();

        return view('loan.payments.processed', array_merge(compact(
            'payments',
            'source',
            'payMode',
            'corporate',
            'totalAmount',
            'displayDate',
            'corporateOptions',
            'payModeOptions'
        ), $filters));
    }

    public function processedPrint(Request $request): View
    {
        $query = LoanBookPayment::query()
            ->with(['loan.loanClient', 'postedByUser', 'validatedByUser', 'accountingJournalEntry.lines.account'])
            ->processedQueue();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');

        $source = trim((string) $request->query('source', ''));
        $payMode = trim((string) $request->query('pay_mode', ''));
        $corporate = trim((string) $request->query('corporate', ''));
        $filters = $this->applyListFilters($query, $request);

        if ($source !== '') {
            $query->where(function (Builder $builder) use ($source): void {
                if ($source === 'sms_forwarder') {
                    $builder->where('channel', 'like', '%_sms_%');

                    return;
                }
                if ($source === 'manual') {
                    $builder->where('channel', 'not like', '%_sms_%');

                    return;
                }
                $builder->where('channel', $source);
            });
        }
        if ($payMode !== '') {
            $query->where('channel', $payMode);
        }
        if ($corporate !== '') {
            $query->whereHas('loan', fn (Builder $loan) => $loan->where('checkoff_employer', $corporate));
        }

        $payments = $query
            ->orderByDesc('posted_at')
            ->orderByDesc('transaction_at')
            ->limit(5000)
            ->get();

        $totalAmount = (float) $payments->sum('amount');

        return view('loan.payments.processed-print', [
            'payments' => $payments,
            'totalAmount' => $totalAmount,
            'generatedAt' => now(),
            'generatedBy' => $request->user()?->name ?? 'System',
            'branchName' => 'Nakuru',
            'filters' => $filters,
        ]);
    }

    public function prepayments(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_PREPAYMENT)
            ->notMergedChild();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request, true);
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('transaction_at'), $request, 'payments-prepayments')) {
            return $export;
        }
        $payments = $query->orderByDesc('transaction_at')->paginate($filters['perPage'])->withQueryString();

        return view('loan.payments.prepayments', array_merge(compact('payments'), $filters));
    }

    public function overpayments(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_OVERPAYMENT)
            ->notMergedChild();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request, true);
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('transaction_at'), $request, 'payments-overpayments')) {
            return $export;
        }
        $payments = $query->orderByDesc('transaction_at')->paginate($filters['perPage'])->withQueryString();

        return view('loan.payments.overpayments', array_merge(compact('payments'), $filters));
    }

    public function merged(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with(['loan.loanClient', 'mergedChildren'])
            ->where('payment_kind', LoanBookPayment::KIND_MERGED)
            ->notMergedChild();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request, true);
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('created_at'), $request, 'payments-merged')) {
            return $export;
        }
        $payments = $query->orderByDesc('created_at')->paginate($filters['perPage'])->withQueryString();

        return view('loan.payments.merged', array_merge(compact('payments'), $filters));
    }

    public function show(LoanBookPayment $loan_book_payment): View
    {
        $loan_book_payment->load([
            'loan.loanClient',
            'postedByUser',
            'validatedByUser',
            'createdByUser',
            'accountingJournalEntry.lines.account',
        ]);
        $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient);

        return view('loan.payments.show', [
            'payment' => $loan_book_payment,
        ]);
    }

    public function c2bReversals(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->where('payment_kind', LoanBookPayment::KIND_C2B_REVERSAL)
            ->notMergedChild();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request, true);
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('transaction_at'), $request, 'payments-c2b-reversals')) {
            return $export;
        }
        $payments = $query->orderByDesc('transaction_at')->paginate($filters['perPage'])->withQueryString();

        return view('loan.payments.c2b-reversals', array_merge(compact('payments'), $filters));
    }

    public function receipts(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->whereNotNull('mpesa_receipt_number')
            ->notMergedChild();
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $source = trim((string) $request->query('source', ''));
        $duplicatesOnly = $request->boolean('duplicates_only');
        $filters = $this->applyListFilters($query, $request, true);
        if ($source !== '') {
            $query->where(function (Builder $builder) use ($source): void {
                if ($source === 'sms_forwarder') {
                    $builder->where('channel', 'like', '%_sms_%');

                    return;
                }
                if ($source === 'manual') {
                    $builder->where('channel', 'not like', '%_sms_%');

                    return;
                }
                $builder->where('channel', $source);
            });
        }
        if ($duplicatesOnly) {
            $query->whereIn('mpesa_receipt_number', function ($sub) {
                $sub->from('loan_book_payments')
                    ->select('mpesa_receipt_number')
                    ->whereNotNull('mpesa_receipt_number')
                    ->groupBy('mpesa_receipt_number')
                    ->havingRaw('COUNT(*) > 1');
            });
        }
        if ($export = $this->exportIfRequested((clone $query)->orderByDesc('transaction_at'), $request, 'payments-receipts')) {
            return $export;
        }
        $payments = $query->orderByDesc('transaction_at')->paginate($filters['perPage'])->withQueryString();
        $receiptCounts = LoanBookPayment::query()
            ->notMergedChild()
            ->whereIn('mpesa_receipt_number', $payments->pluck('mpesa_receipt_number')->filter()->unique()->values())
            ->selectRaw('mpesa_receipt_number, COUNT(*) as c')
            ->groupBy('mpesa_receipt_number')
            ->pluck('c', 'mpesa_receipt_number');
        $duplicateReceipts = $receiptCounts
            ->filter(fn ($count) => (int) $count > 1)
            ->map(fn ($count) => (int) $count)
            ->all();

        return view('loan.payments.receipts', array_merge(compact('payments', 'source', 'duplicateReceipts', 'duplicatesOnly'), $filters));
    }

    public function payinSummary(Request $request): View|StreamedResponse
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();
        $channel = trim((string) $request->query('channel', ''));

        $format = strtolower(trim((string) $request->query('export', '')));
        if (in_array($format, ['csv', 'xls', 'pdf'], true)) {
            return $this->streamPayinSummaryExport($request, $format);
        }

        $base = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->notMergedChild()
            ->whereBetween('transaction_at', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ]);
        $this->scopeByAssignedLoanClient($base, $request->user(), 'loan.loanClient');
        $base->when($channel !== '', fn (Builder $q) => $q->where('channel', $channel));

        $byChannel = (clone $base)
            ->selectRaw('channel, SUM(amount) as total_amount, COUNT(*) as payment_count')
            ->groupBy('channel')
            ->orderByDesc('total_amount')
            ->get();

        $totals = [
            'amount' => (clone $base)->sum('amount'),
            'count' => (clone $base)->count(),
        ];

        return view('loan.payments.payin-summary', compact('from', 'to', 'byChannel', 'totals', 'channel'));
    }

    private function streamPayinSummaryExport(Request $request, string $format): StreamedResponse
    {
        $from = $request->date('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->date('to') ?: now()->endOfMonth()->toDateString();
        $channel = trim((string) $request->query('channel', ''));

        $base = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->notMergedChild()
            ->whereBetween('transaction_at', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ]);
        $this->scopeByAssignedLoanClient($base, $request->user(), 'loan.loanClient');
        $base->when($channel !== '', fn (Builder $q) => $q->where('channel', $channel));

        $byChannel = (clone $base)
            ->selectRaw('channel, SUM(amount) as total_amount, COUNT(*) as payment_count')
            ->groupBy('channel')
            ->orderByDesc('total_amount')
            ->get();

        $totals = [
            'amount' => (clone $base)->sum('amount'),
            'count' => (clone $base)->count(),
        ];

        return TabularExport::stream(
            'payin-summary-'.now()->format('Ymd_His'),
            ['Channel', 'Total amount', 'Payment count'],
            function () use ($byChannel) {
                foreach ($byChannel as $row) {
                    yield [
                        (string) $row->channel,
                        number_format((float) $row->total_amount, 2, '.', ''),
                        (string) $row->payment_count,
                    ];
                }
            },
            $format,
            [
                'title' => 'Pay-in summary',
                'filename_base' => 'payin-summary',
                'subtitle' => $from.' → '.$to.($channel !== '' ? ' · Channel: '.$channel : ' · All channels'),
                'summary' => [
                    'Total amount' => number_format((float) $totals['amount'], 2),
                    'Payment count' => (string) $totals['count'],
                ],
            ]
        );
    }

    public function report(Request $request): View|StreamedResponse
    {
        $format = strtolower(trim((string) $request->query('export', '')));
        if (in_array($format, ['csv', 'xls', 'pdf'], true)) {
            return $this->streamReportExport($request, $format);
        }

        $query = $this->reportQuery($request);
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));

        $payments = (clone $query)
            ->with(['loan.loanClient', 'postedByUser'])
            ->orderByDesc('transaction_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('loan.payments.report', compact('payments', 'perPage'));
    }

    /**
     * Legacy URL: /payments/report/export?… — same filters, default CSV (supports format=csv|xls|pdf).
     */
    public function reportExport(Request $request): StreamedResponse
    {
        $format = strtolower(trim((string) $request->query('format', $request->query('export', 'csv'))));
        if (! in_array($format, ['csv', 'xls', 'pdf'], true)) {
            $format = 'csv';
        }

        return $this->streamReportExport($request, $format);
    }

    private function streamReportExport(Request $request, string $format): StreamedResponse
    {
        $rows = $this->reportQuery($request)
            ->with(['loan.loanClient', 'postedByUser'])
            ->orderByDesc('transaction_at')
            ->limit(5000)
            ->get();

        return TabularExport::stream(
            'payments-report-'.now()->format('Ymd_His'),
            ['Reference', 'Date', 'Loan #', 'Client #', 'Client Name', 'Amount', 'Channel', 'Status', 'Kind', 'Receipt', 'Posted at', 'Posted by'],
            function () use ($rows) {
                foreach ($rows as $payment) {
                    yield [
                        (string) ($payment->reference ?? ''),
                        (string) optional($payment->transaction_at)->format('Y-m-d H:i'),
                        (string) ($payment->loan?->loan_number ?? ''),
                        (string) ($payment->loan?->loanClient?->client_number ?? ''),
                        (string) ($payment->loan?->loanClient?->full_name ?? ''),
                        number_format((float) $payment->amount, 2, '.', ''),
                        (string) ($payment->channel ?? ''),
                        (string) ($payment->status ?? ''),
                        (string) ($payment->payment_kind ?? ''),
                        (string) ($payment->mpesa_receipt_number ?? ''),
                        (string) (optional($payment->posted_at)->format('Y-m-d H:i') ?? ''),
                        (string) ($payment->postedByUser?->name ?? ''),
                    ];
                }
            },
            $format,
            [
                'title' => 'Payments report',
                'filename_base' => 'payments-report',
                'subtitle' => 'Filtered payment register',
            ]
        );
    }

    private function reportQuery(Request $request)
    {
        $q = LoanBookPayment::query()->notMergedChild();
        $this->scopeByAssignedLoanClient($q, $request->user(), 'loan.loanClient');

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
        $this->ensureLoanClientOwner($payment->loan?->loanClient, $request->user());

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

    public function validateSingle(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        $payment = $loan_book_payment;
        $this->ensureLoanClientOwner($payment->loan?->loanClient, $request->user());

        if ($payment->status !== LoanBookPayment::STATUS_PROCESSED) {
            return back()->withErrors(['status' => 'Only processed payments can be validated.']);
        }

        if ($payment->validated_at) {
            return back()->with('status', 'Payment '.$payment->reference.' is already validated.');
        }

        $payment->update([
            'validated_at' => now(),
            'validated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Payment '.$payment->reference.' validated.');
    }

    public function mergeForm(Request $request): View|StreamedResponse
    {
        $query = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->unpostedQueue()
            ->where('payment_kind', '!=', LoanBookPayment::KIND_MERGED);
        $this->scopeByAssignedLoanClient($query, $request->user(), 'loan.loanClient');
        $filters = $this->applyListFilters($query, $request, false);
        if ($export = $this->exportIfRequested((clone $query)->orderBy('transaction_at'), $request, 'payments-merge-candidates')) {
            return $export;
        }
        $candidates = $query->orderBy('transaction_at')->paginate($filters['perPage'])->withQueryString();

        return view('loan.payments.merge', array_merge(compact('candidates'), $filters));
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
            $this->ensureLoanClientOwner($row->loan?->loanClient, $request->user());
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

    public function create(Request $request): View
    {
        $query = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $loans = $query->get();
        $selectedLoanId = $request->integer('loan_book_loan_id');
        if (! $loans->contains(fn (LoanBookLoan $loan): bool => (int) $loan->id === $selectedLoanId)) {
            $selectedLoanId = 0;
        }

        return view('loan.payments.create', compact('loans', 'selectedLoanId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
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

        $loan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($loan->loanClient, $request->user());

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

        $request->user()?->notify(new LoanWorkflowNotification(
            'Payment created',
            'Payment '.$payment->reference.' was created in unposted queue.',
            route('loan.payments.unposted')
        ));

        return redirect()
            ->route('loan.payments.unposted')
            ->with('status', 'Payment '.$payment->reference.' created (unposted).');
    }

    public function reversalCreate(Request $request): View
    {
        $loanQuery = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE])
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($loanQuery, $request->user());
        $loans = $loanQuery->get();

        $original = null;
        if ($request->filled('from')) {
            $original = LoanBookPayment::query()
                ->with('loan.loanClient')
                ->where('status', LoanBookPayment::STATUS_PROCESSED)
                ->find($request->integer('from'));
            if ($original) {
                $this->ensureLoanClientOwner($original->loan?->loanClient, $request->user());
            }
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

        $loan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($loan->loanClient, $request->user());

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

        $request->user()?->notify(new LoanWorkflowNotification(
            'C2B reversal created',
            'Reversal '.$payment->reference.' was recorded and awaits posting.',
            route('loan.payments.c2b_reversals')
        ));

        return redirect()
            ->route('loan.payments.c2b_reversals')
            ->with('status', 'C2B reversal '.$payment->reference.' recorded (unposted). Post when confirmed.');
    }

    public function edit(LoanBookPayment $loan_book_payment): View
    {
        $loan_book_payment->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient);
        abort_unless($loan_book_payment->canEdit(), 403);

        $query = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($query, auth()->user());
        $loans = $query->get();
        $suggestedLoanId = null;
        if (! $loan_book_payment->loan_book_loan_id) {
            $suggested = $this->buildSuggestedLoanMap(collect([$loan_book_payment]), $loans);
            $candidate = (int) ($suggested[(int) $loan_book_payment->id] ?? 0);
            if ($candidate > 0) {
                $suggestedLoanId = $candidate;
            }
        }

        return view('loan.payments.edit', [
            'payment' => $loan_book_payment,
            'loans' => $loans,
            'suggestedLoanId' => $suggestedLoanId,
        ]);
    }

    public function update(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        $loan_book_payment->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient, $request->user());
        abort_unless($loan_book_payment->canEdit(), 403);

        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
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

        $targetLoan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($targetLoan->loanClient, $request->user());

        $loan_book_payment->update($validated);

        return redirect()
            ->route('loan.payments.unposted')
            ->with('status', 'Payment updated.');
    }

    public function destroy(LoanBookPayment $loan_book_payment): RedirectResponse
    {
        $loan_book_payment->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient);
        abort_unless($loan_book_payment->canEdit(), 403);

        $loan_book_payment->delete();

        return redirect()
            ->back()
            ->with('status', 'Payment deleted.');
    }

    public function post(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        $loan_book_payment->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient, $request->user());
        abort_unless($loan_book_payment->status === LoanBookPayment::STATUS_UNPOSTED, 403);
        abort_unless($loan_book_payment->merged_into_payment_id === null, 403);

        try {
            DB::transaction(function () use ($request, $loan_book_payment) {
                $payment = LoanBookPayment::query()->lockForUpdate()->findOrFail($loan_book_payment->id);
                $payment->load('loan.loanClient');
                $this->ensureLoanClientOwner($payment->loan?->loanClient, $request->user());
                if ($payment->accounting_journal_entry_id) {
                    throw new \RuntimeException('This payment is already linked to a journal entry.');
                }
                if ($payment->status !== LoanBookPayment::STATUS_UNPOSTED) {
                    throw new \RuntimeException('This payment is no longer unposted.');
                }
                if (! $payment->loan_book_loan_id) {
                    throw new \RuntimeException('Assign this payment to a loan/client before posting.');
                }
                $payment->load('loan');
                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment, $request->user());
                $payment->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => $request->user()->id,
                    'accounting_journal_entry_id' => $entry->id,
                ]);

                $this->syncCollectionEntryFromProcessedPayment($payment);

                app(LoanBookLoanUpdateService::class)->onPaymentProcessed($payment->fresh());
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withErrors(['accounting' => $e->getMessage()]);
        }

        $request->user()?->notify(new LoanWorkflowNotification(
            'Payment posted',
            'Payment '.$loan_book_payment->reference.' was posted and processed successfully.',
            route('loan.payments.show', $loan_book_payment)
        ));

        return redirect()
            ->back()
            ->with('status', 'Payment posted as processed and recorded in the general ledger.');
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

    public function assignLoan(Request $request, LoanBookPayment $loan_book_payment): RedirectResponse
    {
        $loan_book_payment->load('loan.loanClient');
        abort_unless($loan_book_payment->canEdit(), 403);
        if ($loan_book_payment->loan_book_loan_id) {
            $this->ensureLoanClientOwner($loan_book_payment->loan?->loanClient, $request->user());
        }

        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'post_now' => ['nullable', 'boolean'],
        ]);

        $targetLoan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($targetLoan->loanClient, $request->user());

        $existingNotes = trim((string) ($loan_book_payment->notes ?? ''));
        $assignmentNote = 'Assigned from unposted SMS unmatched queue on '.now()->format('Y-m-d H:i');

        $loan_book_payment->update([
            'loan_book_loan_id' => $targetLoan->id,
            'notes' => $existingNotes !== ''
                ? $existingNotes."\n".$assignmentNote
                : $assignmentNote,
        ]);

        $shouldPostNow = $request->boolean('post_now', false)
            && str_contains((string) $loan_book_payment->channel, '_sms_')
            && $loan_book_payment->status === LoanBookPayment::STATUS_UNPOSTED;

        if ($shouldPostNow) {
            try {
                DB::transaction(function () use ($loan_book_payment, $request) {
                    $payment = LoanBookPayment::query()->lockForUpdate()->findOrFail($loan_book_payment->id);
                    if (! $payment->loan_book_loan_id) {
                        throw new \RuntimeException('Assign this payment to a loan/client before posting.');
                    }
                    if ($payment->status !== LoanBookPayment::STATUS_UNPOSTED) {
                        throw new \RuntimeException('This payment is no longer unposted.');
                    }
                    if ($payment->accounting_journal_entry_id) {
                        throw new \RuntimeException('This payment is already linked to a journal entry.');
                    }
                    $payment->load('loan');
                    $entry = app(LoanBookGlPostingService::class)->postLoanPayment($payment, $request->user());
                    $payment->update([
                        'status' => LoanBookPayment::STATUS_PROCESSED,
                        'posted_at' => now(),
                        'posted_by' => $request->user()->id,
                        'accounting_journal_entry_id' => $entry->id,
                    ]);
                    $this->syncCollectionEntryFromProcessedPayment($payment);
                    app(LoanBookLoanUpdateService::class)->onPaymentProcessed($payment->fresh());
                });

                return redirect()
                    ->route('loan.payments.unposted')
                    ->with('status', 'Payment '.$loan_book_payment->reference.' assigned and posted to '.$targetLoan->loan_number.'.');
            } catch (\RuntimeException $e) {
                return redirect()
                    ->route('loan.payments.unposted')
                    ->withErrors(['accounting' => $e->getMessage()]);
            }
        }

        return redirect()
            ->route('loan.payments.unposted')
            ->with('status', 'Payment '.$loan_book_payment->reference.' assigned to '.$targetLoan->loan_number.'.');
    }

    public function autoMatch(Request $request): RedirectResponse
    {
        $q = trim((string) $request->input('q', ''));
        $channel = trim((string) $request->input('channel', ''));
        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        $perPage = min(200, max(10, (int) $request->input('per_page', 20)));

        $paymentsQuery = LoanBookPayment::query()
            ->with('loan.loanClient')
            ->unpostedQueue()
            ->whereNull('loan_book_loan_id')
            ->where(function (Builder $builder): void {
                $builder
                    ->where('channel', 'like', '%_sms_unmatched')
                    ->orWhere('channel', 'like', '%_sms_disbursement_unmatched');
            });
        $this->scopeByAssignedLoanClient($paymentsQuery, $request->user(), 'loan.loanClient');

        $paymentsQuery
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('mpesa_receipt_number', 'like', '%'.$q.'%')
                        ->orWhere('payer_msisdn', 'like', '%'.$q.'%');
                });
            })
            ->when($channel !== '', fn (Builder $builder) => $builder->where('channel', $channel))
            ->when($from !== '', fn (Builder $builder) => $builder->where('transaction_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn (Builder $builder) => $builder->where('transaction_at', '<=', $to.' 23:59:59'));

        $payments = $paymentsQuery->orderByDesc('transaction_at')->limit(2000)->get();
        if ($payments->isEmpty()) {
            return redirect()
                ->route('loan.payments.unposted', compact('q', 'channel', 'from', 'to', 'perPage'))
                ->with('status', 'Auto-match finished: 0 matched, 0 skipped (no eligible unmatched SMS payments in this filter).');
        }

        $assignableLoans = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [LoanBookLoan::STATUS_ACTIVE, LoanBookLoan::STATUS_PENDING_DISBURSEMENT])
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($assignableLoans, $request->user());
        $assignableLoans = $assignableLoans->limit(5000)->get();

        $suggested = $this->buildSuggestedLoanMap($payments, $assignableLoans);
        $matched = 0;
        $skipped = 0;

        DB::transaction(function () use ($payments, $suggested, &$matched, &$skipped): void {
            foreach ($payments as $payment) {
                $loanId = (int) ($suggested[(int) $payment->id] ?? 0);
                if ($loanId <= 0) {
                    $skipped++;
                    continue;
                }

                $fresh = LoanBookPayment::query()->lockForUpdate()->find($payment->id);
                if (! $fresh || $fresh->loan_book_loan_id !== null || $fresh->status !== LoanBookPayment::STATUS_UNPOSTED) {
                    $skipped++;
                    continue;
                }

                $note = 'Auto-matched by bulk action on '.now()->format('Y-m-d H:i');
                $existing = trim((string) ($fresh->notes ?? ''));
                $fresh->update([
                    'loan_book_loan_id' => $loanId,
                    'notes' => $existing !== '' ? $existing."\n".$note : $note,
                ]);
                $matched++;
            }
        });

        return redirect()
            ->route('loan.payments.unposted', compact('q', 'channel', 'from', 'to', 'perPage'))
            ->with('status', "Auto-match complete: {$matched} matched, {$skipped} skipped.");
    }

    /**
     * @return array{q:string,channel:string,status:string,from:string,to:string,perPage:int}
     */
    private function applyListFilters(Builder $query, Request $request, bool $allowStatus = true): array
    {
        $q = trim((string) $request->query('q', ''));
        $channel = trim((string) $request->query('channel', ''));
        $status = $allowStatus ? trim((string) $request->query('status', '')) : '';
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $query
            ->when($q !== '', function (Builder $builder) use ($q): void {
                $builder->where(function (Builder $inner) use ($q): void {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('mpesa_receipt_number', 'like', '%'.$q.'%')
                        ->orWhere('payer_msisdn', 'like', '%'.$q.'%')
                        ->orWhereHas('loan', function (Builder $loan) use ($q): void {
                            $loan->where('loan_number', 'like', '%'.$q.'%')
                                ->orWhere('product_name', 'like', '%'.$q.'%')
                                ->orWhereHas('loanClient', function (Builder $client) use ($q): void {
                                    $client->where('client_number', 'like', '%'.$q.'%')
                                        ->orWhere('first_name', 'like', '%'.$q.'%')
                                        ->orWhere('last_name', 'like', '%'.$q.'%');
                                });
                        });
                });
            })
            ->when($channel !== '', fn (Builder $builder) => $builder->where('channel', $channel))
            ->when($allowStatus && $status !== '', fn (Builder $builder) => $builder->where('status', $status))
            ->when($from !== '', fn (Builder $builder) => $builder->where('transaction_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn (Builder $builder) => $builder->where('transaction_at', '<=', $to.' 23:59:59'));

        return compact('q', 'channel', 'status', 'from', 'to', 'perPage');
    }

    private function exportIfRequested(Builder $query, Request $request, string $basename): ?StreamedResponse
    {
        $format = strtolower(trim((string) $request->query('export', '')));
        if (! in_array($format, ['csv', 'xls', 'pdf'], true)) {
            return null;
        }

        $rows = $query->with(['loan.loanClient'])->limit(5000)->get();

        if ($format === 'pdf' && $basename === 'payments-unposted') {
            return $this->streamUnpostedPdfReport($rows, $basename);
        }

        return TabularExport::stream(
            $basename.'-'.now()->format('Ymd_His'),
            ['Reference', 'Date', 'Loan #', 'Client #', 'Client Name', 'Amount', 'Channel', 'Status', 'Kind', 'Receipt'],
            function () use ($rows) {
                foreach ($rows as $payment) {
                    yield [
                        (string) ($payment->reference ?? ''),
                        (string) optional($payment->transaction_at)->format('Y-m-d H:i'),
                        (string) ($payment->loan?->loan_number ?? ''),
                        (string) ($payment->loan?->loanClient?->client_number ?? ''),
                        (string) ($payment->loan?->loanClient?->full_name ?? ''),
                        number_format((float) $payment->amount, 2, '.', ''),
                        (string) ($payment->channel ?? ''),
                        (string) ($payment->status ?? ''),
                        (string) ($payment->payment_kind ?? ''),
                        (string) ($payment->mpesa_receipt_number ?? ''),
                    ];
                }
            },
            $format
        );
    }

    /**
     * @param \Illuminate\Support\Collection<int, LoanBookPayment> $rows
     */
    private function streamUnpostedPdfReport($rows, string $basename): StreamedResponse
    {
        $settingsReady = Schema::hasTable('property_portal_settings');
        $companyName = $settingsReady ? trim((string) PropertyPortalSetting::getValue('company_name', '')) : '';
        $tagline = $settingsReady ? trim((string) PropertyPortalSetting::getValue('company_tagline', '')) : '';
        $logo = $settingsReady ? trim((string) PropertyPortalSetting::getValue('company_logo_url', '')) : '';
        $phone = $settingsReady ? trim((string) PropertyPortalSetting::getValue('contact_phone', '')) : '';
        $email = $settingsReady ? trim((string) PropertyPortalSetting::getValue('contact_email_primary', '')) : '';
        $address = $settingsReady ? trim((string) PropertyPortalSetting::getValue('contact_address', '')) : '';

        $companyName = $companyName !== '' ? $companyName : 'Gaitho Property Agency';
        $tagline = $tagline !== '' ? $tagline : 'Excellence in Property Management.';
        $contactLines = array_values(array_filter([$phone, $email, $address], fn ($v) => $v !== ''));
        $logoSrc = $this->resolvePdfLogoSrc($logo);
        $generatedAt = now();

        $mappedRows = $rows->map(function (LoanBookPayment $payment): array {
            $channelRaw = strtolower((string) ($payment->channel ?? ''));
            $channel = match (true) {
                str_contains($channelRaw, 'mpesa_sms_unmatched') => 'M-Pesa Unmatched',
                str_contains($channelRaw, 'mpesa_sms') => 'M-Pesa SMS',
                str_contains($channelRaw, 'mpesa') => 'M-Pesa',
                str_contains($channelRaw, 'bank') => 'Bank',
                str_contains($channelRaw, 'cash') => 'Cash',
                str_contains($channelRaw, 'wallet') => 'Wallet',
                default => ucwords(str_replace('_', ' ', $channelRaw ?: 'Other')),
            };

            $statusRaw = strtolower((string) ($payment->status ?? ''));
            if ($statusRaw === '' && str_contains($channelRaw, 'unmatched')) {
                $statusRaw = 'unmatched';
            }
            if ($statusRaw === '') {
                $statusRaw = 'unposted';
            }

            return [
                'reference' => (string) ($payment->reference ?: '—'),
                'date_time' => (string) (optional($payment->transaction_at)->format('Y-m-d H:i') ?? '—'),
                'channel' => $channel,
                'receipt' => (string) ($payment->mpesa_receipt_number ?: '—'),
                'client_name' => (string) ($payment->loan?->loanClient?->full_name ?: '—'),
                'loan_number' => (string) ($payment->loan?->loan_number ?: '—'),
                'amount' => (float) ($payment->amount ?? 0),
                'status' => $statusRaw,
            ];
        })->values();

        $totalTransactions = $mappedRows->count();
        $totalAmount = (float) $mappedRows->sum('amount');
        $attentionRequired = (int) $mappedRows->filter(fn ($row) => in_array($row['status'], ['unposted', 'unmatched'], true))->count();

        $html = view('loan.payments.exports.unposted-report', [
            'companyName' => $companyName,
            'tagline' => $tagline,
            'contactLines' => $contactLines,
            'logoSrc' => $logoSrc,
            'generatedAt' => $generatedAt,
            'rows' => $mappedRows,
            'totalTransactions' => $totalTransactions,
            'totalAmount' => $totalAmount,
            'attentionRequired' => $attentionRequired,
        ])->render();

        try {
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $binary = $dompdf->output();

            return response()->streamDownload(function () use ($binary): void {
                echo $binary;
            }, $basename.'-'.now()->format('Ymd_His').'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Throwable) {
            $docName = $basename.'-'.now()->format('Ymd_His').'.doc';

            return response()->streamDownload(function () use ($html): void {
                echo $html;
            }, $docName, [
                'Content-Type' => 'application/msword; charset=UTF-8',
            ]);
        }
    }

    private function resolvePdfLogoSrc(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }
        if (str_starts_with($logo, 'data:image/')) {
            return $logo;
        }
        if (preg_match('/^https?:\/\//i', $logo) === 1) {
            return $logo;
        }

        $candidate = ltrim($logo, '/');
        $publicPath = public_path($candidate);
        if (! is_file($publicPath)) {
            return $logo;
        }
        $mime = mime_content_type($publicPath) ?: 'image/png';
        $content = @file_get_contents($publicPath);

        return $content !== false
            ? 'data:'.$mime.';base64,'.base64_encode($content)
            : $logo;
    }

    /**
     * @param \Illuminate\Support\Collection<int, LoanBookPayment> $payments
     * @param \Illuminate\Support\Collection<int, LoanBookLoan> $loans
     * @return array<int,int>
     */
    private function buildSuggestedLoanMap($payments, $loans): array
    {
        $loanByPhone = [];
        foreach ($loans as $loan) {
            $phone = trim((string) ($loan->loanClient?->phone ?? ''));
            if ($phone === '') {
                continue;
            }
            foreach ($this->phoneVariants($phone) as $variant) {
                if (! isset($loanByPhone[$variant])) {
                    $loanByPhone[$variant] = (int) $loan->id;
                }
            }
        }

        $out = [];
        foreach ($payments as $payment) {
            $payer = trim((string) ($payment->payer_msisdn ?? ''));
            if ($payer === '') {
                continue;
            }
            foreach ($this->phoneVariants($payer) as $variant) {
                if (isset($loanByPhone[$variant])) {
                    $out[(int) $payment->id] = (int) $loanByPhone[$variant];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];
        if (str_starts_with($digits, '254') && strlen($digits) >= 12) {
            $variants[] = '0'.substr($digits, 3);
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $variants[] = '254'.substr($digits, 1);
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
