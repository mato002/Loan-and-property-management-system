<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\Employee;
use App\Models\LoanBookAgent;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookCollectionRate;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBranch;
use App\Models\LoanRegion;
use App\Notifications\Loan\LoanWorkflowNotification;
use App\Support\TabularExport;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\LoanBook\LoanDisbursementPayoutService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanBookOperationsController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function disbursementsIndex(Request $request)
    {
        $query = LoanBookDisbursement::query()->with(['loan.loanClient', 'loan.loanBranch.region', 'accountingJournalEntry']);
        $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient');
        $q = trim((string) $request->query('q', ''));
        $method = trim((string) $request->query('method', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));
        $calendarYear = min(2100, max(2000, (int) $request->query('cal_year', now()->year)));
        $calendarMonth = min(12, max(1, (int) $request->query('cal_month', now()->month)));
        $calendarRegionId = max(0, (int) $request->query('cal_region_id', 0));
        $calendarBranchId = max(0, (int) $request->query('cal_branch_id', 0));
        $calendarProduct = trim((string) $request->query('cal_product', ''));

        $query
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('reference', 'like', '%'.$q.'%')
                        ->orWhere('method', 'like', '%'.$q.'%')
                        ->orWhereHas('loan', function ($loan) use ($q) {
                            $loan->where('loan_number', 'like', '%'.$q.'%')
                                ->orWhereHas('loanClient', function ($client) use ($q) {
                                    $client->where('client_number', 'like', '%'.$q.'%')
                                        ->orWhere('first_name', 'like', '%'.$q.'%')
                                        ->orWhere('last_name', 'like', '%'.$q.'%');
                                });
                        });
                });
            })
            ->when($method !== '', fn ($builder) => $builder->where('method', $method))
            ->when($from !== '', fn ($builder) => $builder->whereDate('disbursed_at', '>=', $from))
            ->when($to !== '', fn ($builder) => $builder->whereDate('disbursed_at', '<=', $to))
            ->when($calendarProduct !== '', function ($builder) use ($calendarProduct) {
                $builder->whereHas('loan', fn ($loan) => $loan->where('product_name', $calendarProduct));
            })
            ->when($calendarBranchId > 0, function ($builder) use ($calendarBranchId) {
                $builder->whereHas('loan', fn ($loan) => $loan->where('loan_branch_id', $calendarBranchId));
            })
            ->when($calendarRegionId > 0, function ($builder) use ($calendarRegionId) {
                $builder->whereHas('loan.loanBranch', fn ($branch) => $branch->where('loan_region_id', $calendarRegionId));
            });

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('disbursed_at')->orderByDesc('id')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-disbursements-'.now()->format('Ymd_His'),
                ['Date', 'Loan #', 'Client #', 'Client Name', 'Region', 'Branch', 'Product', 'Amount', 'Method', 'Payout Status', 'Payout Error', 'Reference', 'GL Journal #'],
                function () use ($rows) {
                    $amountTotal = 0.0;
                    foreach ($rows as $d) {
                        $amount = (float) $d->amount;
                        $amountTotal += $amount;
                        yield [
                            (string) optional($d->disbursed_at)->format('Y-m-d'),
                            (string) ($d->loan?->loan_number ?? ''),
                            (string) ($d->loan?->loanClient?->client_number ?? ''),
                            (string) ($d->loan?->loanClient?->full_name ?? ''),
                            (string) ($d->loan?->loanBranch?->region?->name ?? ''),
                            (string) ($d->loan?->loanBranch?->name ?? $d->loan?->branch ?? ''),
                            (string) ($d->loan?->product_name ?? ''),
                            number_format($amount, 2, '.', ''),
                            (string) $d->method,
                            (string) ($d->payout_status ?? 'completed'),
                            (string) ($d->payout_status === 'failed' ? ($d->payout_result_desc ?? '') : ''),
                            (string) $d->reference,
                            (string) ($d->accounting_journal_entry_id ?? ''),
                        ];
                    }
                    yield ['TOTAL', '', '', '', '', '', '', number_format($amountTotal, 2, '.', ''), '', '', '', '', ''];
                },
                $export
            );
        }

        $disbursements = $query->orderByDesc('disbursed_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $methods = LoanBookDisbursement::query()->select('method')->distinct()->orderBy('method')->pluck('method');
        $monthStart = Carbon::create($calendarYear, $calendarMonth, 1)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $calendarQuery = LoanBookDisbursement::query()
            ->with(['loan.loanBranch.region'])
            ->whereDate('disbursed_at', '>=', $monthStart->toDateString())
            ->whereDate('disbursed_at', '<=', $monthEnd->toDateString())
            ->whereHas('loan');
        $this->scopeByAssignedLoanClient($calendarQuery, auth()->user(), 'loan.loanClient');

        $calendarQuery->when($calendarProduct !== '', function ($builder) use ($calendarProduct) {
            $builder->whereHas('loan', fn ($loan) => $loan->where('product_name', $calendarProduct));
        });
        $calendarQuery->when($calendarBranchId > 0, function ($builder) use ($calendarBranchId) {
            $builder->whereHas('loan', fn ($loan) => $loan->where('loan_branch_id', $calendarBranchId));
        });
        $calendarQuery->when($calendarRegionId > 0, function ($builder) use ($calendarRegionId) {
            $builder->whereHas('loan.loanBranch', fn ($branch) => $branch->where('loan_region_id', $calendarRegionId));
        });

        $calendarRows = $calendarQuery->get();
        $calendarByDay = [];
        foreach ($calendarRows as $row) {
            $day = (int) optional($row->disbursed_at)->format('j');
            if ($day <= 0) {
                continue;
            }
            $branchName = trim((string) ($row->loan?->loanBranch?->name ?? $row->loan?->branch ?? 'Unassigned'));
            if ($branchName === '') {
                $branchName = 'Unassigned';
            }
            $calendarByDay[$day][$branchName] = (float) ($calendarByDay[$day][$branchName] ?? 0) + (float) $row->amount;
        }
        foreach ($calendarByDay as $day => $branchTotals) {
            arsort($branchTotals);
            $calendarByDay[$day] = $branchTotals;
        }

        $calendarGridStart = (clone $monthStart)->startOfWeek(Carbon::MONDAY);
        $calendarGridEnd = (clone $monthEnd)->endOfWeek(Carbon::SUNDAY);
        $calendarWeeks = [];
        $cursor = (clone $calendarGridStart);
        while ($cursor->lte($calendarGridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = (clone $cursor);
                $cursor->addDay();
            }
            $calendarWeeks[] = $week;
        }

        $calendarRegionOptions = LoanRegion::query()->orderBy('name')->get(['id', 'name']);
        $calendarBranchOptions = LoanBranch::query()
            ->with('region:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'loan_region_id']);
        $calendarProductOptionsQuery = LoanBookLoan::query()
            ->whereNotNull('product_name')
            ->where('product_name', '!=', '')
            ->select('product_name')
            ->distinct()
            ->orderBy('product_name');
        $this->scopeByAssignedLoanClient($calendarProductOptionsQuery, auth()->user());
        $calendarProductOptions = $calendarProductOptionsQuery->pluck('product_name');

        $pendingLoanQuery = LoanBookLoan::query()
            ->with('loanClient')
            ->whereIn('status', [
                LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                LoanBookLoan::STATUS_ACTIVE,
            ])
            ->whereDoesntHave('disbursements')
            ->orderByDesc('created_at');
        $this->scopeByAssignedLoanClient($pendingLoanQuery, auth()->user());
        $pendingLoans = $pendingLoanQuery->limit(30)->get();

        return view('loan.book.disbursements.index', [
            'title' => 'Disbursements',
            'subtitle' => 'Cash-out and channel postings against booked loans.',
            'disbursements' => $disbursements,
            'q' => $q,
            'method' => $method,
            'from' => $from,
            'to' => $to,
            'perPage' => $perPage,
            'methods' => $methods,
            'pendingLoans' => $pendingLoans,
            'calendarYear' => $calendarYear,
            'calendarMonth' => $calendarMonth,
            'calendarRegionId' => $calendarRegionId,
            'calendarBranchId' => $calendarBranchId,
            'calendarProduct' => $calendarProduct,
            'calendarWeeks' => $calendarWeeks,
            'calendarByDay' => $calendarByDay,
            'calendarRegionOptions' => $calendarRegionOptions,
            'calendarBranchOptions' => $calendarBranchOptions,
            'calendarProductOptions' => $calendarProductOptions,
            'calendarCurrentMonth' => $monthStart,
        ]);
    }

    public function disbursementsCreate(): View
    {
        $loanQuery = LoanBookLoan::query()
            ->with('loanClient')
            ->whereDoesntHave('disbursements')
            ->orderByDesc('created_at');
        $this->scopeByAssignedLoanClient($loanQuery, auth()->user());

        return view('loan.book.disbursements.create', [
            'title' => 'Record disbursement',
            'subtitle' => 'Link a payout to an existing loan account.',
            'loans' => $loanQuery->get(),
            'b2cPayoutConfigured' => app(MpesaDarajaService::class)->isB2cConfigured(),
        ]);
    }

    public function disbursementsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:80'],
            'method' => ['required', 'string', 'max:40'],
            'payout_transaction_id' => [
                'nullable',
                'string',
                'max:80',
                Rule::requiredIf(in_array($request->input('method'), ['mpesa', 'bank', 'cheque'], true)),
            ],
            'disbursed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $loan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($loan->loanClient, $request->user());
        if ($loan->disbursements()->exists()) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['loan_book_loan_id' => __('This loan has already been disbursed. Multiple disbursements are not allowed.')]);
        }

        try {
            // All methods (including M-Pesa) are recorded as manual payouts: GL posts immediately.
            // Daraja B2C is only used from "Retry M-Pesa payout" when B2C env is fully configured.
            DB::transaction(function () use ($validated, $request) {
                $txnRef = trim((string) ($validated['payout_transaction_id'] ?? ''));
                $needsTxnRef = in_array($validated['method'], ['mpesa', 'bank', 'cheque'], true);

                $disbursement = LoanBookDisbursement::query()->create(array_merge($validated, [
                    'payout_status' => 'completed',
                    'payout_provider' => $validated['method'] === 'mpesa' ? 'mpesa' : null,
                    'payout_requested_at' => now(),
                    'payout_completed_at' => now(),
                    'payout_transaction_id' => $needsTxnRef && $txnRef !== '' ? $txnRef : null,
                ]));
                $disbursement->load('loan');
                $entry = app(LoanBookGlPostingService::class)->postDisbursement($disbursement, $request->user());
                $disbursement->update(['accounting_journal_entry_id' => $entry->id]);

                app(LoanBookLoanUpdateService::class)->onDisbursed($disbursement);
            });
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['accounting' => $e->getMessage()]);
        }

        $request->user()?->notify(new LoanWorkflowNotification(
            'Disbursement recorded',
            'Disbursement for loan '.($loan->loan_number ?? '#'.$loan->id).' was posted successfully.',
            route('loan.book.disbursements.index')
        ));

        return redirect()
            ->route('loan.book.disbursements.index')
            ->with('status', __('Disbursement recorded and posted to the general ledger.'));
    }

    public function disbursementsDestroy(LoanBookDisbursement $loan_book_disbursement): RedirectResponse
    {
        $loan_book_disbursement->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_disbursement->loan?->loanClient);

        if ($loan_book_disbursement->accounting_journal_entry_id) {
            return redirect()
                ->route('loan.book.disbursements.index')
                ->withErrors([
                    'disbursement' => __('This disbursement is linked to a journal entry. Remove that entry under Accounting → Journal first if you need to reverse it.'),
                ]);
        }

        $loan_book_disbursement->delete();

        return redirect()
            ->route('loan.book.disbursements.index')
            ->with('status', __('Disbursement removed.'));
    }

    public function disbursementsShow(LoanBookDisbursement $loan_book_disbursement): View
    {
        $loan_book_disbursement->load([
            'loan.loanClient',
            'accountingJournalEntry',
        ]);
        $this->ensureLoanClientOwner($loan_book_disbursement->loan?->loanClient);

        return view('loan.book.disbursements.show', [
            'title' => 'Disbursement details',
            'subtitle' => (string) ($loan_book_disbursement->reference ?? 'Disbursement'),
            'disbursement' => $loan_book_disbursement,
            'b2cPayoutConfigured' => app(MpesaDarajaService::class)->isB2cConfigured(),
        ]);
    }

    public function disbursementsRetryPayout(LoanBookDisbursement $loan_book_disbursement): RedirectResponse
    {
        $loan_book_disbursement->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_disbursement->loan?->loanClient);

        if ($loan_book_disbursement->method !== 'mpesa') {
            return redirect()
                ->route('loan.book.disbursements.show', $loan_book_disbursement)
                ->withErrors(['disbursement' => __('Retry is available only for M-Pesa disbursements.')]);
        }

        if ($loan_book_disbursement->accounting_journal_entry_id) {
            return redirect()
                ->route('loan.book.disbursements.show', $loan_book_disbursement)
                ->withErrors(['disbursement' => __('This disbursement is already posted. No retry is needed.')]);
        }

        if (($loan_book_disbursement->payout_status ?? '') !== 'failed') {
            return redirect()
                ->route('loan.book.disbursements.show', $loan_book_disbursement)
                ->withErrors(['disbursement' => __('Retry is only allowed when payout status is failed.')]);
        }

        if (! app(MpesaDarajaService::class)->isB2cConfigured()) {
            return redirect()
                ->route('loan.book.disbursements.show', $loan_book_disbursement)
                ->withErrors([
                    'disbursement' => __('Automatic M-Pesa B2C is not configured. Record payouts manually on this screen, or set MPESA_B2C_* variables in `.env` to enable API retry.'),
                ]);
        }

        $result = app(LoanDisbursementPayoutService::class)->initiateMpesaPayout($loan_book_disbursement, $loan_book_disbursement->loan);
        if (! $result['ok']) {
            return redirect()
                ->route('loan.book.disbursements.show', $loan_book_disbursement)
                ->withErrors(['disbursement' => 'M-Pesa payout retry failed: '.($result['message'] ?: 'No response from provider.')]);
        }

        return redirect()
            ->route('loan.book.disbursements.show', $loan_book_disbursement)
            ->with('status', __('Payout retry sent to M-Pesa. Waiting for callback confirmation.'));
    }

    public function collectionSheet(Request $request)
    {
        $fromRaw = (string) $request->query('from', $request->query('date', now()->toDateString()));
        $toRaw = (string) $request->query('to', $request->query('date', now()->toDateString()));
        try {
            $from = Carbon::parse($fromRaw)->toDateString();
        } catch (\Throwable) {
            $from = now()->toDateString();
        }
        try {
            $to = Carbon::parse($toRaw)->toDateString();
        } catch (\Throwable) {
            $to = $from;
        }
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $q = trim((string) $request->query('q', ''));
        $channel = trim((string) $request->query('channel', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $entriesQuery = LoanBookCollectionEntry::query()
            ->with([
                'loan' => function ($loanQuery) use ($from, $to) {
                    $loanQuery
                        ->with(['loanClient.assignedEmployee'])
                        ->withSum('processedRepayments', 'amount')
                        ->withSum(['collectionEntries as period_collection_sum' => function ($entryQuery) use ($from, $to) {
                            $entryQuery->whereBetween('collected_on', [$from, $to]);
                        }], 'amount');
                },
                'collectedBy',
                'accountingJournalEntry',
            ])
            ->whereBetween('collected_on', [$from, $to])
            ->orderByDesc('collected_on')
            ->orderByDesc('id')
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('notes', 'like', '%'.$q.'%')
                        ->orWhereHas('loan', function ($loan) use ($q) {
                            $loan->where('loan_number', 'like', '%'.$q.'%')
                                ->orWhereHas('loanClient', function ($client) use ($q) {
                                    $client->where('client_number', 'like', '%'.$q.'%')
                                        ->orWhere('first_name', 'like', '%'.$q.'%')
                                        ->orWhere('last_name', 'like', '%'.$q.'%');
                                });
                        });
                });
            })
            ->when($channel !== '', fn ($builder) => $builder->where('channel', $channel));
        $this->scopeByAssignedLoanClient($entriesQuery, $request->user(), 'loan.loanClient');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $entriesQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-sheet-'.now()->format('Ymd_His'),
                ['Collected On', 'Loan #', 'Client #', 'Client Name', 'Amount', 'Channel', 'Collected By', 'GL Journal #'],
                function () use ($rows) {
                    $amountTotal = 0.0;
                    foreach ($rows as $row) {
                        $amount = (float) $row->amount;
                        $amountTotal += $amount;
                        yield [
                            (string) optional($row->collected_on)->format('Y-m-d'),
                            (string) ($row->loan?->loan_number ?? ''),
                            (string) ($row->loan?->loanClient?->client_number ?? ''),
                            (string) ($row->loan?->loanClient?->full_name ?? ''),
                            number_format($amount, 2, '.', ''),
                            (string) $row->channel,
                            (string) ($row->collectedBy?->full_name ?? ''),
                            (string) ($row->accounting_journal_entry_id ?? ''),
                        ];
                    }
                    yield ['TOTAL', '', '', '', number_format($amountTotal, 2, '.', ''), '', '', ''];
                },
                $export
            );
        }

        $entries = $entriesQuery->paginate($perPage)->withQueryString();

        $loanQuery = LoanBookLoan::query()
            ->with('loanClient')
            ->where('status', LoanBookLoan::STATUS_ACTIVE)
            ->orderBy('loan_number');
        $this->scopeByAssignedLoanClient($loanQuery, $request->user());
        $loans = $loanQuery->get();

        return view('loan.book.collection_sheet', [
            'title' => 'Collection sheet',
            'subtitle' => 'Daily receipts by loan account.',
            'entries' => $entries,
            'loans' => $loans,
            'employees' => Employee::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'filterFrom' => $from,
            'filterTo' => $to,
            'q' => $q,
            'channel' => $channel,
            'perPage' => $perPage,
            'channels' => LoanBookCollectionEntry::query()->select('channel')->distinct()->orderBy('channel')->pluck('channel'),
        ]);
    }

    public function collectionSheetStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'collected_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'channel' => ['required', 'string', 'max:40'],
            'collected_by_employee_id' => ['nullable', 'exists:employees,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sync_to_accounting' => ['sometimes', 'boolean'],
        ]);

        $loan = LoanBookLoan::query()->with('loanClient')->findOrFail($validated['loan_book_loan_id']);
        $this->ensureLoanClientOwner($loan->loanClient, $request->user());

        $sync = $request->boolean('sync_to_accounting');

        try {
            if ($sync) {
                DB::transaction(function () use ($validated, $request) {
                    $row = LoanBookCollectionEntry::query()->create($validated);
                    $row->load('loan');
                    $entry = app(LoanBookGlPostingService::class)->postCollectionEntry($row, $request->user());
                    $row->update(['accounting_journal_entry_id' => $entry->id]);
                });
            } else {
                LoanBookCollectionEntry::query()->create($validated);
            }
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['accounting' => $e->getMessage()]);
        }

        return redirect()
            ->route('loan.book.collection_sheet.index', ['date' => $validated['collected_on']])
            ->with('status', $sync
                ? __('Collection line saved and posted to the general ledger.')
                : __('Collection line saved.'));
    }

    public function collectionSheetDestroy(LoanBookCollectionEntry $loan_book_collection_entry): RedirectResponse
    {
        $loan_book_collection_entry->load('loan.loanClient');
        $this->ensureLoanClientOwner($loan_book_collection_entry->loan?->loanClient);

        if ($loan_book_collection_entry->accounting_journal_entry_id) {
            $d = $loan_book_collection_entry->collected_on->toDateString();

            return redirect()
                ->route('loan.book.collection_sheet.index', ['date' => $d])
                ->withErrors([
                    'accounting' => __('This line is linked to a journal entry. Remove that entry under Accounting → Journal first if you need to reverse it.'),
                ]);
        }

        $d = $loan_book_collection_entry->collected_on->toDateString();
        $loan_book_collection_entry->delete();

        return redirect()
            ->route('loan.book.collection_sheet.index', ['date' => $d])
            ->with('status', __('Collection line removed.'));
    }

    public function collectionMtd(): View
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $scopedLoanIds = LoanBookLoan::query()->select('id');
        $this->scopeByAssignedLoanClient($scopedLoanIds, auth()->user());

        $totalsQuery = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereBetween('transaction_at', [
                $start.' 00:00:00',
                $end.' 23:59:59',
            ])
            ->selectRaw('COUNT(*) as `receipt_count`, COALESCE(SUM(`amount`), 0) as `collected`');
        $this->scopeByAssignedLoanClient($totalsQuery, auth()->user(), 'loan.loanClient');
        $totals = $totalsQuery->first();

        $byChannelQuery = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereBetween('transaction_at', [
                $start.' 00:00:00',
                $end.' 23:59:59',
            ])
            ->selectRaw('`channel`, COALESCE(SUM(`amount`), 0) as `total`')
            ->groupBy('channel')
            ->orderByDesc('total');
        $this->scopeByAssignedLoanClient($byChannelQuery, auth()->user(), 'loan.loanClient');
        $byChannel = $byChannelQuery->get();

        $recentQuery = LoanBookPayment::query()
            ->with(['loan.loanClient'])
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereNotNull('loan_book_loan_id')
            ->whereHas('loan')
            ->whereBetween('transaction_at', [
                $start.' 00:00:00',
                $end.' 23:59:59',
            ])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->limit(15);
        $this->scopeByAssignedLoanClient($recentQuery, auth()->user(), 'loan.loanClient');
        $recent = $recentQuery->get();

        $loanBranchMetrics = DB::table('loan_book_loans')
            ->whereIn('id', $scopedLoanIds)
            ->whereNotNull('branch')
            ->whereNotNull('disbursed_at')
            ->whereDate('disbursed_at', '>=', $start)
            ->whereDate('disbursed_at', '<=', $end)
            ->selectRaw('
                branch,
                COUNT(*) as total_loans,
                COALESCE(SUM(principal), 0) as disbursed_amount,
                COALESCE(SUM(principal + interest_outstanding + fees_outstanding), 0) as loan_plus_charges
            ')
            ->groupBy('branch')
            ->get()
            ->keyBy('branch');

        $paidBranchMetrics = DB::table('loan_book_collection_entries as ce')
            ->join('loan_book_loans as l', 'l.id', '=', 'ce.loan_book_loan_id')
            ->whereIn('ce.loan_book_loan_id', $scopedLoanIds)
            ->whereNotNull('l.branch')
            ->whereDate('ce.collected_on', '>=', $start)
            ->whereDate('ce.collected_on', '<=', $end)
            ->selectRaw('l.branch as branch, COALESCE(SUM(ce.amount), 0) as paid')
            ->groupBy('l.branch')
            ->get()
            ->keyBy('branch');

        $branches = $loanBranchMetrics->keys()->merge($paidBranchMetrics->keys())->unique()->sort()->values();
        $branchPerformance = $branches->map(function ($branch) use ($loanBranchMetrics, $paidBranchMetrics) {
            $loanRow = $loanBranchMetrics->get($branch);
            $paidRow = $paidBranchMetrics->get($branch);

            $disbursed = (float) ($loanRow->disbursed_amount ?? 0);
            $totalLoans = (int) ($loanRow->total_loans ?? 0);
            $loanPlusCharges = (float) ($loanRow->loan_plus_charges ?? 0);
            $paid = (float) ($paidRow->paid ?? 0);
            $arrears = max(0, $loanPlusCharges - $paid);
            $gcPercent = $loanPlusCharges > 0 ? ($paid / $loanPlusCharges) * 100 : 0.0;

            return (object) [
                'branch' => $branch,
                'disbursed_amount' => $disbursed,
                'total_loans' => $totalLoans,
                'loan_plus_charges' => $loanPlusCharges,
                'paid' => $paid,
                'arrears' => $arrears,
                'gc_percent' => $gcPercent,
            ];
        })->values();

        $branchTotals = (object) [
            'disbursed_amount' => (float) $branchPerformance->sum('disbursed_amount'),
            'total_loans' => (int) $branchPerformance->sum('total_loans'),
            'loan_plus_charges' => (float) $branchPerformance->sum('loan_plus_charges'),
            'paid' => (float) $branchPerformance->sum('paid'),
            'arrears' => (float) $branchPerformance->sum('arrears'),
            'gc_percent' => ((float) $branchPerformance->sum('loan_plus_charges')) > 0
                ? ((float) $branchPerformance->sum('paid') / (float) $branchPerformance->sum('loan_plus_charges')) * 100
                : 0.0,
        ];

        return view('loan.book.collection_mtd', [
            'title' => 'Collection MTD',
            'subtitle' => now()->format('F Y').' — month-to-date receipts.',
            'start' => $start,
            'end' => $end,
            'totals' => $totals,
            'byChannel' => $byChannel,
            'recent' => $recent,
            'branchPerformance' => $branchPerformance,
            'branchTotals' => $branchTotals,
        ]);
    }

    public function collectionReports(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $q = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $reportMode = strtolower((string) $request->query('report_mode', 'detail'));
        if (! in_array($reportMode, ['detail', 'branch'], true)) {
            $reportMode = 'detail';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));
        try {
            $from = Carbon::parse($from)->toDateString();
            $to = Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->toDateString();
        }

        $scopedLoanIds = LoanBookLoan::query()->select('id');
        $this->scopeByAssignedLoanClient($scopedLoanIds, $request->user());

        $byBranchQuery = DB::table('loan_book_collection_entries')
            ->join('loan_book_loans', 'loan_book_loans.id', '=', 'loan_book_collection_entries.loan_book_loan_id')
            ->whereIn('loan_book_collection_entries.loan_book_loan_id', $scopedLoanIds)
            ->where('loan_book_collection_entries.collected_on', '>=', $from)
            ->where('loan_book_collection_entries.collected_on', '<=', $to)
            ->when($branch !== '', fn ($builder) => $builder->where('loan_book_loans.branch', $branch))
            ->when($q !== '', fn ($builder) => $builder->where('loan_book_loans.branch', 'like', '%'.$q.'%'))
            ->selectRaw('loan_book_loans.`branch` as `branch`, COUNT(*) as `receipt_count`, COALESCE(SUM(loan_book_collection_entries.`amount`), 0) as `total`')
            ->groupBy('loan_book_loans.branch')
            ->orderByDesc('total');

        $processedPaymentsSub = DB::table('loan_book_payments')
            ->selectRaw('loan_book_loan_id, COALESCE(SUM(amount), 0) as paid_total')
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->groupBy('loan_book_loan_id');

        $detailQuery = DB::table('loan_book_loans as l')
            ->leftJoin('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->leftJoin('employees as e', 'e.id', '=', 'c.assigned_employee_id')
            ->leftJoin('loan_book_collection_entries as ce', function ($join) use ($from, $to) {
                $join->on('ce.loan_book_loan_id', '=', 'l.id')
                    ->where('ce.collected_on', '>=', $from)
                    ->where('ce.collected_on', '<=', $to);
            })
            ->leftJoinSub($processedPaymentsSub, 'pp', function ($join) {
                $join->on('pp.loan_book_loan_id', '=', 'l.id');
            })
            ->whereIn('l.id', $scopedLoanIds)
            ->when($branch !== '', fn ($builder) => $builder->where('l.branch', $branch))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('l.loan_number', 'like', '%'.$q.'%')
                        ->orWhere('l.branch', 'like', '%'.$q.'%')
                        ->orWhere('c.client_number', 'like', '%'.$q.'%')
                        ->orWhere('c.first_name', 'like', '%'.$q.'%')
                        ->orWhere('c.last_name', 'like', '%'.$q.'%')
                        ->orWhere('c.phone', 'like', '%'.$q.'%')
                        ->orWhereRaw("TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) like ?", ['%'.$q.'%']);
                });
            })
            ->groupBy(
                'l.id',
                'l.loan_number',
                'l.branch',
                'l.balance',
                'l.dpd',
                'c.first_name',
                'c.last_name',
                'c.phone',
                'e.first_name',
                'e.last_name',
                'pp.paid_total'
            )
            ->selectRaw("
                l.id as loan_id,
                l.loan_number,
                l.branch,
                l.balance,
                l.dpd,
                TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) as client_name,
                c.phone as client_phone,
                TRIM(CONCAT(COALESCE(e.first_name,''), ' ', COALESCE(e.last_name,''))) as portfolio_name,
                COALESCE(SUM(ce.amount), 0) as collection_total,
                COALESCE(pp.paid_total, 0) as paid_total
            ")
            ->havingRaw('COALESCE(SUM(ce.amount), 0) > 0')
            ->orderByDesc('collection_total');

        $branches = DB::table('loan_book_loans')
            ->whereIn('id', $scopedLoanIds)
            ->whereNotNull('branch')
            ->where('branch', '!=', '')
            ->distinct()
            ->orderBy('branch')
            ->pluck('branch');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            if ($reportMode === 'branch') {
                $rows = (clone $byBranchQuery)->limit(5000)->get();

                return TabularExport::stream(
                    'loanbook-collection-reports-branch-'.now()->format('Ymd_His'),
                    ['Branch', 'Receipt Lines', 'Total'],
                    function () use ($rows) {
                        $receiptLinesTotal = 0;
                        $amountTotal = 0.0;
                        foreach ($rows as $row) {
                            $receiptLines = (int) ($row->receipt_count ?? 0);
                            $amount = (float) ($row->total ?? 0);
                            $receiptLinesTotal += $receiptLines;
                            $amountTotal += $amount;
                            yield [
                                (string) ($row->branch ?? ''),
                                (string) $receiptLines,
                                number_format($amount, 2, '.', ''),
                            ];
                        }
                        yield ['TOTAL', (string) $receiptLinesTotal, number_format($amountTotal, 2, '.', '')];
                    },
                    $export
                );
            }

            $rows = (clone $detailQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-reports-detail-'.now()->format('Ymd_His'),
                ['Loan #', 'Client', 'Contact', 'Portfolio', 'Branch', 'Collection', 'Arrears (DPD)', 'Paid', 'Balance'],
                function () use ($rows) {
                    $collectionTotal = 0.0;
                    $paidTotal = 0.0;
                    $balanceTotal = 0.0;
                    foreach ($rows as $row) {
                        $collection = (float) ($row->collection_total ?? 0);
                        $paid = (float) ($row->paid_total ?? 0);
                        $balance = (float) ($row->balance ?? 0);
                        $collectionTotal += $collection;
                        $paidTotal += $paid;
                        $balanceTotal += $balance;
                        yield [
                            (string) ($row->loan_number ?? ''),
                            (string) ($row->client_name ?? ''),
                            (string) ($row->client_phone ?? ''),
                            (string) ($row->portfolio_name ?? ''),
                            (string) ($row->branch ?? ''),
                            number_format($collection, 2, '.', ''),
                            (string) ((int) ($row->dpd ?? 0)),
                            number_format($paid, 2, '.', ''),
                            number_format($balance, 2, '.', ''),
                        ];
                    }
                    yield ['TOTAL', '', '', '', '', number_format($collectionTotal, 2, '.', ''), '', number_format($paidTotal, 2, '.', ''), number_format($balanceTotal, 2, '.', '')];
                },
                $export
            );
        }

        $byBranch = $byBranchQuery->paginate($perPage)->withQueryString();
        $detailRows = $detailQuery->paginate($perPage, ['*'], 'detail_page')->withQueryString();

        return view('loan.book.collection_reports', [
            'title' => 'Collection reports',
            'subtitle' => 'Receipts grouped by branch for the selected window.',
            'from' => $from,
            'to' => $to,
            'byBranch' => $byBranch,
            'detailRows' => $detailRows,
            'reportMode' => $reportMode,
            'q' => $q,
            'branch' => $branch,
            'branches' => $branches,
            'perPage' => $perPage,
        ]);
    }

    public function collectionsReportsCommandCenter(Request $request): View
    {
        $selectedBranchId = max(0, (int) $request->query('branch_id', 0));

        $branchOptions = LoanBranch::query()
            ->with('region:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'loan_region_id']);

        $selectedBranch = $selectedBranchId > 0
            ? $branchOptions->firstWhere('id', $selectedBranchId)
            : null;

        $todayDate = now();
        $dateWindowLabel = $todayDate->format('d M Y').' - '.$todayDate->copy()->addDays(6)->format('d M Y');

        $metrics = [
            'total_expected' => 2_388_700,
            'total_collected' => 1_872_450,
            'current_yield' => 1_872_450,
            'arrears_recovery_yield' => 642_300,
            'prepayment_yield' => 210_750,
            'expected_inflow_7_days' => 5_642_100,
            'expected_inflow_14_days' => 9_785_900,
            'expected_inflow_30_days' => 18_964_300,
            'available_liquidity_today' => 2_145_780,
            'liquidity_floor_amount' => 1_800_000,
            'projected_liquidity_breach_days' => 8,
        ];

        $metrics['collection_efficiency'] = $metrics['total_expected'] > 0
            ? ($metrics['total_collected'] / $metrics['total_expected']) * 100
            : 0.0;
        $metrics['yield_gap'] = max(0, $metrics['total_expected'] - $metrics['total_collected']);

        $forecastWindows = [
            [
                'window' => '7 Days',
                'expected_inflow' => $metrics['expected_inflow_7_days'],
                'expected_collected' => 4_980_300,
            ],
            [
                'window' => '14 Days',
                'expected_inflow' => $metrics['expected_inflow_14_days'],
                'expected_collected' => 8_412_000,
            ],
            [
                'window' => '30 Days',
                'expected_inflow' => $metrics['expected_inflow_30_days'],
                'expected_collected' => 15_105_200,
            ],
        ];

        $forecastWindows = collect($forecastWindows)->map(function (array $row) {
            $expectedInflow = (float) $row['expected_inflow'];
            $expectedCollected = (float) $row['expected_collected'];
            $gap = max(0.0, $expectedInflow - $expectedCollected);
            $rate = $expectedInflow > 0 ? ($expectedCollected / $expectedInflow) * 100 : 0.0;

            $row['gap'] = $gap;
            $row['collection_rate'] = $rate;

            return $row;
        })->values();

        $collectionMix = collect([
            ['label' => 'Current / Due Today', 'amount' => 1_120_000, 'color' => '#0f766e'],
            ['label' => 'Due Yesterday', 'amount' => 752_450, 'color' => '#2563eb'],
            ['label' => 'Arrears 1-7 Days', 'amount' => 642_300, 'color' => '#f59e0b'],
            ['label' => 'Deep Arrears 8+ Days', 'amount' => 290_180, 'color' => '#ef4444'],
        ]);
        $collectionMixTotal = (float) $collectionMix->sum('amount');
        $collectionMixWithPct = $collectionMix->map(function (array $segment) use ($collectionMixTotal) {
            $segment['percentage'] = $collectionMixTotal > 0
                ? (((float) $segment['amount']) / $collectionMixTotal) * 100
                : 0.0;

            return $segment;
        })->values();

        $dailyCollectionRates = collect([
            ['date' => 'Today', 'expected' => 2_388_700, 'collected' => 1_872_450, 'trend' => [58, 61, 63, 66, 70, 72, 78]],
            ['date' => 'Yesterday', 'expected' => 2_210_500, 'collected' => 1_821_040, 'trend' => [54, 56, 59, 61, 64, 68, 74]],
            ['date' => $todayDate->copy()->subDays(2)->format('D, d M'), 'expected' => 2_145_200, 'collected' => 1_690_100, 'trend' => [49, 51, 55, 57, 60, 63, 69]],
            ['date' => $todayDate->copy()->subDays(3)->format('D, d M'), 'expected' => 2_008_450, 'collected' => 1_755_300, 'trend' => [52, 55, 57, 60, 64, 69, 74]],
            ['date' => $todayDate->copy()->subDays(4)->format('D, d M'), 'expected' => 1_985_600, 'collected' => 1_472_400, 'trend' => [44, 47, 50, 54, 58, 61, 66]],
            ['date' => $todayDate->copy()->subDays(5)->format('D, d M'), 'expected' => 2_112_300, 'collected' => 1_980_150, 'trend' => [62, 64, 67, 70, 73, 77, 81]],
        ])->map(function (array $row) {
            $expected = (float) $row['expected'];
            $collected = (float) $row['collected'];
            $rate = $expected > 0 ? ($collected / $expected) * 100 : 0.0;
            $gap = max(0.0, $expected - $collected);
            $row['collection_rate'] = $rate;
            $row['yield_gap'] = $gap;

            return $row;
        })->values();

        $agentPerformanceSummary = [
            'top_agent' => 'Faith N.',
            'top_agent_collected' => 468_900,
            'pending_collections' => 39,
        ];

        $alerts = [
            [
                'severity' => 'critical',
                'title' => 'Liquidity at risk in 8 days',
                'description' => 'Projected cash balance crosses below liquidity floor.',
                'time_ago' => '12m ago',
            ],
            [
                'severity' => 'positive',
                'title' => 'High arrears recovery',
                'description' => 'Recovered KES 642,300 from late loans today.',
                'time_ago' => '35m ago',
            ],
            [
                'severity' => 'warning',
                'title' => 'Pending collections building up',
                'description' => '39 accounts still pending field follow-up.',
                'time_ago' => '1h ago',
            ],
            [
                'severity' => 'info',
                'title' => 'Top performing agent',
                'description' => 'Faith N. leads today with KES 468,900 collected.',
                'time_ago' => '2h ago',
            ],
            [
                'severity' => 'critical',
                'title' => 'Yield gap alert',
                'description' => 'Current gap remains at KES 516,250 versus expected.',
                'time_ago' => '3h ago',
            ],
        ];

        $projectedLiquidityAfter30 = 1_540_200;
        $liquidityFloorStatus = $projectedLiquidityAfter30 < $metrics['liquidity_floor_amount'] ? 'AT RISK' : 'HEALTHY';

        return view('loan.book.collections_reports', [
            'title' => 'Collections & Reports',
            'subtitle' => 'Real-time collection intelligence and cashflow visibility',
            'selectedBranchId' => $selectedBranchId,
            'selectedBranch' => $selectedBranch,
            'branchOptions' => $branchOptions,
            'metrics' => $metrics,
            'forecastWindows' => $forecastWindows,
            'collectionMix' => $collectionMixWithPct,
            'collectionMixTotal' => $collectionMixTotal,
            'dailyCollectionRates' => $dailyCollectionRates,
            'agentPerformanceSummary' => $agentPerformanceSummary,
            'alerts' => $alerts,
            'liquidityFloorStatus' => $liquidityFloorStatus,
            'projectedLiquidityBreachDate' => now()->addDays((int) $metrics['projected_liquidity_breach_days'])->toDateString(),
            'dateWindowLabel' => $dateWindowLabel,
        ]);
    }

    public function agentsIndex(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $active = trim((string) $request->query('active', ''));
        $monthRaw = trim((string) $request->query('month', now()->format('Y-m')));
        $dayRaw = trim((string) $request->query('day', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthRaw)->startOfMonth();
        } catch (\Throwable) {
            $monthDate = now()->startOfMonth();
        }
        $month = $monthDate->format('Y-m');
        $monthStart = $monthDate->toDateString();
        $monthEnd = $monthDate->copy()->endOfMonth()->toDateString();
        $day = ctype_digit($dayRaw) ? max(1, min(31, (int) $dayRaw)) : null;

        $agentsQuery = LoanBookAgent::query()
            ->with('employee')
            ->when($q !== '', fn ($builder) => $builder->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))
            ->when($branch !== '', fn ($builder) => $builder->where('branch', $branch))
            ->when($active !== '', fn ($builder) => $builder->where('is_active', $active === '1'))
            ->orderBy('name');

        $assignedLoansByEmployee = DB::table('loan_book_loans as l')
            ->join('loan_clients as c', 'c.id', '=', 'l.loan_client_id')
            ->whereNotNull('c.assigned_employee_id')
            ->selectRaw('c.assigned_employee_id as employee_id, COUNT(*) as assigned_loans')
            ->groupBy('c.assigned_employee_id')
            ->pluck('assigned_loans', 'employee_id');

        $collectionMetricsByEmployee = DB::table('loan_book_collection_entries')
            ->whereNotNull('collected_by_employee_id')
            ->whereDate('collected_on', '>=', $monthStart)
            ->whereDate('collected_on', '<=', $monthEnd)
            ->when($day !== null, fn ($builder) => $builder->whereRaw('DAY(collected_on) = ?', [$day]))
            ->selectRaw('collected_by_employee_id as employee_id, COUNT(*) as loanbook_lines, COALESCE(SUM(amount), 0) as month_collections')
            ->groupBy('collected_by_employee_id')
            ->get()
            ->keyBy('employee_id');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $agentsQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-agents-'.now()->format('Ymd_His'),
                ['Agent Name', 'Branches', 'Portfolios', 'Loanbook', 'Assigned Loans', $monthDate->format('M').' Collections'],
                function () use ($rows, $assignedLoansByEmployee, $collectionMetricsByEmployee) {
                    $loanbookLinesTotal = 0;
                    $assignedLoansTotal = 0;
                    $collectionsTotal = 0.0;
                    foreach ($rows as $agent) {
                        $employeeId = $agent->employee_id;
                        $metrics = $employeeId ? $collectionMetricsByEmployee->get($employeeId) : null;
                        $loanbookLines = (int) ($metrics->loanbook_lines ?? 0);
                        $assignedLoans = (int) ($employeeId ? ($assignedLoansByEmployee[$employeeId] ?? 0) : 0);
                        $collections = (float) ($metrics->month_collections ?? 0);
                        $loanbookLinesTotal += $loanbookLines;
                        $assignedLoansTotal += $assignedLoans;
                        $collectionsTotal += $collections;
                        yield [
                            (string) $agent->name,
                            (string) ($agent->branch ?? ''),
                            (string) ($agent->employee?->full_name ?? 'None'),
                            (string) $loanbookLines,
                            (string) $assignedLoans,
                            number_format($collections, 2, '.', ''),
                        ];
                    }
                    yield ['TOTAL', '', '', (string) $loanbookLinesTotal, (string) $assignedLoansTotal, number_format($collectionsTotal, 2, '.', '')];
                },
                $export
            );
        }

        $agents = $agentsQuery->paginate($perPage)->withQueryString();
        $agents->getCollection()->transform(function ($agent) use ($assignedLoansByEmployee, $collectionMetricsByEmployee) {
            $employeeId = $agent->employee_id;
            $metrics = $employeeId ? $collectionMetricsByEmployee->get($employeeId) : null;
            $agent->loanbook_lines = (int) ($metrics->loanbook_lines ?? 0);
            $agent->assigned_loans = (int) ($employeeId ? ($assignedLoansByEmployee[$employeeId] ?? 0) : 0);
            $agent->month_collections = (float) ($metrics->month_collections ?? 0);

            return $agent;
        });

        return view('loan.book.agents.index', [
            'title' => 'Collection agents',
            'subtitle' => 'Field staff and third-party collectors linked to LoanBook.',
            'agents' => $agents,
            'q' => $q,
            'branch' => $branch,
            'active' => $active,
            'perPage' => $perPage,
            'month' => $month,
            'day' => $day,
            'monthLabel' => $monthDate->format('M Y'),
            'monthShortLabel' => $monthDate->format('M'),
            'branches' => LoanBookAgent::query()->whereNotNull('branch')->where('branch', '!=', '')->distinct()->orderBy('branch')->pluck('branch'),
        ]);
    }

    public function agentsCreate(): View
    {
        return view('loan.book.agents.create', [
            'title' => 'Add collection agent',
            'subtitle' => 'Register someone who can be credited on collection lines.',
            'employees' => Employee::query()->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function agentsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'branch' => ['nullable', 'string', 'max:120'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);

        LoanBookAgent::query()->create($validated);

        return redirect()
            ->route('loan.book.collection_agents.index')
            ->with('status', __('Agent saved.'));
    }

    public function agentsEdit(LoanBookAgent $loan_book_agent): View
    {
        return view('loan.book.agents.edit', [
            'title' => 'Edit collection agent',
            'subtitle' => $loan_book_agent->name,
            'agent' => $loan_book_agent,
            'employees' => Employee::query()->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function agentsUpdate(Request $request, LoanBookAgent $loan_book_agent): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'branch' => ['nullable', 'string', 'max:120'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);

        $loan_book_agent->update($validated);

        return redirect()
            ->route('loan.book.collection_agents.index')
            ->with('status', __('Agent updated.'));
    }

    public function agentsDestroy(LoanBookAgent $loan_book_agent): RedirectResponse
    {
        $loan_book_agent->delete();

        return redirect()
            ->route('loan.book.collection_agents.index')
            ->with('status', __('Agent removed.'));
    }

    public function ratesIndex(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $year = trim((string) $request->query('year', ''));
        $month = trim((string) $request->query('month', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $ratesQuery = LoanBookCollectionRate::query()
            ->when($q !== '', fn ($builder) => $builder->where('branch', 'like', '%'.$q.'%'))
            ->when($branch !== '', fn ($builder) => $builder->where('branch', $branch))
            ->when($year !== '', fn ($builder) => $builder->where('year', (int) $year))
            ->when($month !== '', fn ($builder) => $builder->where('month', (int) $month))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('branch');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $ratesQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-rates-'.now()->format('Ymd_His'),
                ['Branch', 'Year', 'Month', 'Target Amount', 'Notes'],
                function () use ($rows) {
                    $targetAmountTotal = 0.0;
                    foreach ($rows as $rate) {
                        $targetAmount = (float) $rate->target_amount;
                        $targetAmountTotal += $targetAmount;
                        yield [
                            (string) $rate->branch,
                            (string) $rate->year,
                            (string) $rate->month,
                            number_format($targetAmount, 2, '.', ''),
                            (string) ($rate->notes ?? ''),
                        ];
                    }
                    yield ['TOTAL', '', '', number_format($targetAmountTotal, 2, '.', ''), ''];
                },
                $export
            );
        }

        $rates = $ratesQuery->paginate($perPage)->withQueryString();

        $scopedLoanIds = LoanBookLoan::query()->select('id');
        $this->scopeByAssignedLoanClient($scopedLoanIds, $request->user());

        $loanMetrics = DB::table('loan_book_loans')
            ->whereIn('id', $scopedLoanIds)
            ->whereNotNull('branch')
            ->whereNotNull('disbursed_at')
            ->selectRaw('branch, YEAR(disbursed_at) as y, MONTH(disbursed_at) as m, COALESCE(SUM(principal), 0) as disbursed_loan, COALESCE(SUM(principal + interest_outstanding + fees_outstanding), 0) as loan_plus_charges')
            ->groupBy('branch', DB::raw('YEAR(disbursed_at)'), DB::raw('MONTH(disbursed_at)'))
            ->get()
            ->keyBy(fn ($row) => ($row->branch ?? '').'|'.$row->y.'|'.$row->m);

        $collectionMetrics = DB::table('loan_book_collection_entries as ce')
            ->join('loan_book_loans as l', 'l.id', '=', 'ce.loan_book_loan_id')
            ->whereIn('ce.loan_book_loan_id', $scopedLoanIds)
            ->whereNotNull('l.branch')
            ->selectRaw('l.branch as branch, YEAR(ce.collected_on) as y, MONTH(ce.collected_on) as m, COALESCE(SUM(ce.amount), 0) as collected_total')
            ->groupBy('l.branch', DB::raw('YEAR(ce.collected_on)'), DB::raw('MONTH(ce.collected_on)'))
            ->get()
            ->keyBy(fn ($row) => ($row->branch ?? '').'|'.$row->y.'|'.$row->m);

        $rates->getCollection()->transform(function ($rate) use ($loanMetrics, $collectionMetrics) {
            $key = ($rate->branch ?? '').'|'.(int) $rate->year.'|'.(int) $rate->month;
            $loanRow = $loanMetrics->get($key);
            $collectionRow = $collectionMetrics->get($key);

            $rate->disbursed_loan = (float) ($loanRow->disbursed_loan ?? 0);
            $rate->loan_plus_charges = (float) ($loanRow->loan_plus_charges ?? 0);
            $rate->otc = (float) $rate->target_amount;
            $rate->oc = (float) $rate->target_amount;
            $rate->dd7 = 0.0;
            $rate->cg7 = 0.0;
            $rate->arrears = max(0, ((float) $rate->oc) - (float) ($collectionRow->collected_total ?? 0));

            $base = max(0.00001, (float) $rate->loan_plus_charges);
            $rate->otc_percent = ((float) $rate->otc / $base) * 100;
            $rate->oc_percent = ((float) $rate->oc / $base) * 100;
            $rate->gc_percent = ((float) $rate->oc / $base) * 100;

            return $rate;
        });

        return view('loan.book.rates.index', [
            'title' => 'Collection rates & targets',
            'subtitle' => 'Monthly branch collection targets for PAR and budgeting.',
            'rates' => $rates,
            'q' => $q,
            'branch' => $branch,
            'year' => $year,
            'month' => $month,
            'perPage' => $perPage,
            'branches' => LoanBookCollectionRate::query()->select('branch')->distinct()->orderBy('branch')->pluck('branch'),
            'years' => LoanBookCollectionRate::query()->select('year')->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function ratesCreate(): View
    {
        return view('loan.book.rates.create', [
            'title' => 'New collection target',
            'subtitle' => 'Set expected receipts for a branch and calendar month.',
        ]);
    }

    public function ratesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        LoanBookCollectionRate::query()->create($validated);

        return redirect()
            ->route('loan.book.collection_rates.index')
            ->with('status', __('Target saved.'));
    }

    public function ratesEdit(LoanBookCollectionRate $loan_book_collection_rate): View
    {
        return view('loan.book.rates.edit', [
            'title' => 'Edit collection target',
            'subtitle' => $loan_book_collection_rate->branch.' · '.$loan_book_collection_rate->year.'-'.str_pad((string) $loan_book_collection_rate->month, 2, '0', STR_PAD_LEFT),
            'rate' => $loan_book_collection_rate,
        ]);
    }

    public function ratesUpdate(Request $request, LoanBookCollectionRate $loan_book_collection_rate): RedirectResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $loan_book_collection_rate->update($validated);

        return redirect()
            ->route('loan.book.collection_rates.index')
            ->with('status', __('Target updated.'));
    }

    public function ratesDestroy(LoanBookCollectionRate $loan_book_collection_rate): RedirectResponse
    {
        $loan_book_collection_rate->delete();

        return redirect()
            ->route('loan.book.collection_rates.index')
            ->with('status', __('Target removed.'));
    }
}
