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
        $query = LoanBookDisbursement::query()->with(['loan.loanClient', 'accountingJournalEntry']);
        $this->scopeByAssignedLoanClient($query, auth()->user(), 'loan.loanClient');
        $q = trim((string) $request->query('q', ''));
        $method = trim((string) $request->query('method', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

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
            ->when($to !== '', fn ($builder) => $builder->whereDate('disbursed_at', '<=', $to));

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->orderByDesc('disbursed_at')->orderByDesc('id')->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-disbursements-'.now()->format('Ymd_His'),
                ['Date', 'Loan #', 'Client #', 'Client Name', 'Amount', 'Method', 'Payout Status', 'Payout Error', 'Reference', 'GL Journal #'],
                function () use ($rows) {
                    foreach ($rows as $d) {
                        yield [
                            (string) optional($d->disbursed_at)->format('Y-m-d'),
                            (string) ($d->loan?->loan_number ?? ''),
                            (string) ($d->loan?->loanClient?->client_number ?? ''),
                            (string) ($d->loan?->loanClient?->full_name ?? ''),
                            number_format((float) $d->amount, 2, '.', ''),
                            (string) $d->method,
                            (string) ($d->payout_status ?? 'completed'),
                            (string) ($d->payout_status === 'failed' ? ($d->payout_result_desc ?? '') : ''),
                            (string) $d->reference,
                            (string) ($d->accounting_journal_entry_id ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $disbursements = $query->orderByDesc('disbursed_at')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $methods = LoanBookDisbursement::query()->select('method')->distinct()->orderBy('method')->pluck('method');

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
        ]);
    }

    public function disbursementsCreate(): View
    {
        $loanQuery = LoanBookLoan::query()->with('loanClient')->orderByDesc('created_at');
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
        $date = $request->query('date', now()->toDateString());
        try {
            $on = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $on = now()->toDateString();
        }

        $q = trim((string) $request->query('q', ''));
        $channel = trim((string) $request->query('channel', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $entriesQuery = LoanBookCollectionEntry::query()
            ->with(['loan.loanClient', 'collectedBy', 'accountingJournalEntry'])
            ->whereDate('collected_on', $on)
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
                    foreach ($rows as $row) {
                        yield [
                            (string) optional($row->collected_on)->format('Y-m-d'),
                            (string) ($row->loan->loan_number ?? ''),
                            (string) ($row->loan->loanClient->client_number ?? ''),
                            (string) ($row->loan->loanClient->full_name ?? ''),
                            number_format((float) $row->amount, 2, '.', ''),
                            (string) $row->channel,
                            (string) ($row->collectedBy->full_name ?? ''),
                            (string) ($row->accounting_journal_entry_id ?? ''),
                        ];
                    }
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
            'filterDate' => $on,
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
            ->whereBetween('transaction_at', [
                $start.' 00:00:00',
                $end.' 23:59:59',
            ])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->limit(15);
        $this->scopeByAssignedLoanClient($recentQuery, auth()->user(), 'loan.loanClient');
        $recent = $recentQuery->get();

        return view('loan.book.collection_mtd', [
            'title' => 'Collection MTD',
            'subtitle' => now()->format('F Y').' — month-to-date receipts.',
            'start' => $start,
            'end' => $end,
            'totals' => $totals,
            'byChannel' => $byChannel,
            'recent' => $recent,
        ]);
    }

    public function collectionReports(Request $request)
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        $q = trim((string) $request->query('q', ''));
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
            ->when($q !== '', fn ($builder) => $builder->where('loan_book_loans.branch', 'like', '%'.$q.'%'))
            ->selectRaw('loan_book_loans.`branch` as `branch`, COUNT(*) as `receipt_count`, COALESCE(SUM(loan_book_collection_entries.`amount`), 0) as `total`')
            ->groupBy('loan_book_loans.branch')
            ->orderByDesc('total');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $byBranchQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-reports-'.now()->format('Ymd_His'),
                ['Branch', 'Receipt Lines', 'Total'],
                function () use ($rows) {
                    foreach ($rows as $row) {
                        yield [
                            (string) ($row->branch ?? ''),
                            (string) ((int) ($row->receipt_count ?? 0)),
                            number_format((float) ($row->total ?? 0), 2, '.', ''),
                        ];
                    }
                },
                $export
            );
        }

        $byBranch = $byBranchQuery->paginate($perPage)->withQueryString();

        return view('loan.book.collection_reports', [
            'title' => 'Collection reports',
            'subtitle' => 'Receipts grouped by branch for the selected window.',
            'from' => $from,
            'to' => $to,
            'byBranch' => $byBranch,
            'q' => $q,
            'perPage' => $perPage,
        ]);
    }

    public function agentsIndex(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $branch = trim((string) $request->query('branch', ''));
        $active = trim((string) $request->query('active', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $agentsQuery = LoanBookAgent::query()
            ->with('employee')
            ->when($q !== '', fn ($builder) => $builder->where('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))
            ->when($branch !== '', fn ($builder) => $builder->where('branch', $branch))
            ->when($active !== '', fn ($builder) => $builder->where('is_active', $active === '1'))
            ->orderBy('name');

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $agentsQuery)->limit(5000)->get();

            return TabularExport::stream(
                'loanbook-collection-agents-'.now()->format('Ymd_His'),
                ['Name', 'Phone', 'Branch', 'Linked Employee', 'Active'],
                function () use ($rows) {
                    foreach ($rows as $agent) {
                        yield [
                            (string) $agent->name,
                            (string) ($agent->phone ?? ''),
                            (string) ($agent->branch ?? ''),
                            (string) ($agent->employee->full_name ?? ''),
                            $agent->is_active ? 'Yes' : 'No',
                        ];
                    }
                },
                $export
            );
        }

        $agents = $agentsQuery->paginate($perPage)->withQueryString();

        return view('loan.book.agents.index', [
            'title' => 'Collection agents',
            'subtitle' => 'Field staff and third-party collectors linked to LoanBook.',
            'agents' => $agents,
            'q' => $q,
            'branch' => $branch,
            'active' => $active,
            'perPage' => $perPage,
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
                    foreach ($rows as $rate) {
                        yield [
                            (string) $rate->branch,
                            (string) $rate->year,
                            (string) $rate->month,
                            number_format((float) $rate->target_amount, 2, '.', ''),
                            (string) ($rate->notes ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $rates = $ratesQuery->paginate($perPage)->withQueryString();

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
