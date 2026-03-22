<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoanBookAgent;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanBookCollectionRate;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookLoan;
use App\Services\LoanBookGlPostingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoanBookOperationsController extends Controller
{
    public function disbursementsIndex(): View
    {
        $disbursements = LoanBookDisbursement::query()
            ->with(['loan.loanClient', 'accountingJournalEntry'])
            ->orderByDesc('disbursed_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('loan.book.disbursements.index', [
            'title' => 'Disbursements',
            'subtitle' => 'Cash-out and channel postings against booked loans.',
            'disbursements' => $disbursements,
        ]);
    }

    public function disbursementsCreate(): View
    {
        return view('loan.book.disbursements.create', [
            'title' => 'Record disbursement',
            'subtitle' => 'Link a payout to an existing loan account.',
            'loans' => LoanBookLoan::query()
                ->with('loanClient')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function disbursementsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_book_loan_id' => ['required', 'exists:loan_book_loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:80'],
            'method' => ['required', 'string', 'max:40'],
            'disbursed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($validated, $request) {
                $disbursement = LoanBookDisbursement::query()->create($validated);
                $disbursement->load('loan');
                $entry = app(LoanBookGlPostingService::class)->postDisbursement($disbursement, $request->user());
                $disbursement->update(['accounting_journal_entry_id' => $entry->id]);
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

    public function collectionSheet(Request $request): View
    {
        $date = $request->query('date', now()->toDateString());
        try {
            $on = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $on = now()->toDateString();
        }

        $entries = LoanBookCollectionEntry::query()
            ->with(['loan.loanClient', 'collectedBy', 'accountingJournalEntry'])
            ->whereDate('collected_on', $on)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $loans = LoanBookLoan::query()
            ->with('loanClient')
            ->where('status', LoanBookLoan::STATUS_ACTIVE)
            ->orderBy('loan_number')
            ->get();

        return view('loan.book.collection_sheet', [
            'title' => 'Collection sheet',
            'subtitle' => 'Daily receipts by loan account.',
            'entries' => $entries,
            'loans' => $loans,
            'employees' => Employee::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'filterDate' => $on,
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

        $totals = LoanBookCollectionEntry::query()
            ->where('collected_on', '>=', $start)
            ->where('collected_on', '<=', $end)
            ->selectRaw('COUNT(*) as `receipt_count`, COALESCE(SUM(`amount`), 0) as `collected`')
            ->first();

        $byChannel = LoanBookCollectionEntry::query()
            ->where('collected_on', '>=', $start)
            ->where('collected_on', '<=', $end)
            ->selectRaw('`channel`, COALESCE(SUM(`amount`), 0) as `total`')
            ->groupBy('channel')
            ->orderByDesc('total')
            ->get();

        $recent = LoanBookCollectionEntry::query()
            ->with(['loan.loanClient'])
            ->where('collected_on', '>=', $start)
            ->where('collected_on', '<=', $end)
            ->orderByDesc('collected_on')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

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

    public function collectionReports(Request $request): View
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());
        try {
            $from = Carbon::parse($from)->toDateString();
            $to = Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $from = now()->startOfMonth()->toDateString();
            $to = now()->toDateString();
        }

        $byBranch = DB::table('loan_book_collection_entries')
            ->join('loan_book_loans', 'loan_book_loans.id', '=', 'loan_book_collection_entries.loan_book_loan_id')
            ->where('loan_book_collection_entries.collected_on', '>=', $from)
            ->where('loan_book_collection_entries.collected_on', '<=', $to)
            ->selectRaw('loan_book_loans.`branch` as `branch`, COUNT(*) as `receipt_count`, COALESCE(SUM(loan_book_collection_entries.`amount`), 0) as `total`')
            ->groupBy('loan_book_loans.branch')
            ->orderByDesc('total')
            ->get();

        return view('loan.book.collection_reports', [
            'title' => 'Collection reports',
            'subtitle' => 'Receipts grouped by branch for the selected window.',
            'from' => $from,
            'to' => $to,
            'byBranch' => $byBranch,
        ]);
    }

    public function agentsIndex(): View
    {
        $agents = LoanBookAgent::query()
            ->with('employee')
            ->orderBy('name')
            ->paginate(20);

        return view('loan.book.agents.index', [
            'title' => 'Collection agents',
            'subtitle' => 'Field staff and third-party collectors linked to LoanBook.',
            'agents' => $agents,
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

    public function ratesIndex(): View
    {
        $rates = LoanBookCollectionRate::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderBy('branch')
            ->paginate(20);

        return view('loan.book.rates.index', [
            'title' => 'Collection rates & targets',
            'subtitle' => 'Monthly branch collection targets for PAR and budgeting.',
            'rates' => $rates,
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
