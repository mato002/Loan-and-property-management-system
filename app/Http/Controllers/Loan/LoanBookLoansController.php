<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\LoanBranch;
use App\Models\LoanClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LoanBookLoansController extends Controller
{
    public function index(): View
    {
        $loans = LoanBookLoan::query()
            ->with(['loanClient', 'application'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('loan.book.loans.index', [
            'title' => 'View loans',
            'subtitle' => 'Active and closed facilities in LoanBook.',
            'loans' => $loans,
        ]);
    }

    public function arrears(): View
    {
        $loans = LoanBookLoan::query()
            ->with(['loanClient'])
            ->where('status', LoanBookLoan::STATUS_ACTIVE)
            ->where('dpd', '>', 0)
            ->orderByDesc('dpd')
            ->paginate(20);

        return view('loan.book.loans.arrears', [
            'title' => 'Loan arrears',
            'subtitle' => 'Active accounts with days past due.',
            'loans' => $loans,
        ]);
    }

    public function checkoff(): View
    {
        $loans = LoanBookLoan::query()
            ->with(['loanClient'])
            ->where('is_checkoff', true)
            ->orderByDesc('balance')
            ->paginate(20);

        return view('loan.book.loans.checkoff', [
            'title' => 'Checkoff loans',
            'subtitle' => 'Salary-checkoff and employer-deduct facilities.',
            'loans' => $loans,
        ]);
    }

    public function create(): View
    {
        return view('loan.book.loans.create', [
            'title' => 'Create loan',
            'subtitle' => 'Book a new facility (manual or from an approved application).',
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'applications' => LoanBookApplication::query()
                ->whereIn('stage', [LoanBookApplication::STAGE_APPROVED, LoanBookApplication::STAGE_DISBURSED])
                ->whereDoesntHave('loan')
                ->with('loanClient')
                ->orderByDesc('created_at')
                ->get(),
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedLoan($request);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $this->applyDirectoryBranch($validated);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }

        $next = (LoanBookLoan::query()->max('id') ?? 0) + 1;
        $validated['loan_number'] = 'LN-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

        if (empty($validated['balance'])) {
            $validated['balance'] = $validated['principal'];
        }

        LoanBookLoan::query()->create($validated);

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan booked.'));
    }

    public function edit(LoanBookLoan $loan_book_loan): View
    {
        return view('loan.book.loans.edit', [
            'title' => 'Edit loan',
            'subtitle' => $loan_book_loan->loan_number,
            'loan' => $loan_book_loan,
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'applications' => LoanBookApplication::query()
                ->with('loanClient')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(),
            'statuses' => $this->statusOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(Request $request, LoanBookLoan $loan_book_loan): RedirectResponse
    {
        $validated = $this->validatedLoan($request, false);
        $validated['is_checkoff'] = $request->boolean('is_checkoff');
        if (empty($validated['loan_book_application_id'])) {
            $validated['loan_book_application_id'] = null;
        }
        LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $loan_book_loan->update($validated);

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan updated.'));
    }

    public function destroy(LoanBookLoan $loan_book_loan): RedirectResponse
    {
        if ($loan_book_loan->disbursements()->exists() || $loan_book_loan->collectionEntries()->exists()) {
            return redirect()
                ->route('loan.book.loans.index')
                ->with('error', __('Remove disbursements and collection lines before deleting this loan.'));
        }

        $loan_book_loan->delete();

        return redirect()
            ->route('loan.book.loans.index')
            ->with('status', __('Loan removed.'));
    }

    private function validatedLoan(Request $request, bool $isCreate = true): array
    {
        $rules = [
            'loan_book_application_id' => ['nullable', 'exists:loan_book_applications,id'],
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'principal' => ['required', 'numeric', 'min:0'],
            'balance' => ['nullable', 'numeric', 'min:0'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys($this->statusOptions()))],
            'dpd' => ['required', 'integer', 'min:0', 'max:9999'],
            'disbursed_at' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date'],
            'checkoff_employer' => ['nullable', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (Schema::hasTable('loan_branches')) {
            $rules['loan_branch_id'] = ['nullable', 'exists:loan_branches,id'];
        }

        return $request->validate($rules);
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            LoanBookLoan::STATUS_PENDING_DISBURSEMENT => 'Pending disbursement',
            LoanBookLoan::STATUS_ACTIVE => 'Active',
            LoanBookLoan::STATUS_CLOSED => 'Closed',
            LoanBookLoan::STATUS_WRITTEN_OFF => 'Written off',
            LoanBookLoan::STATUS_RESTRUCTURED => 'Restructured',
        ];
    }

    private function branchOptions(): Collection
    {
        if (! Schema::hasTable('loan_branches')) {
            return collect();
        }

        return LoanBranch::query()->with('region')->orderBy('name')->get();
    }

    private function applyDirectoryBranch(array &$validated): void
    {
        if (! Schema::hasTable('loan_branches')) {
            unset($validated['loan_branch_id']);

            return;
        }

        if (empty($validated['loan_branch_id'])) {
            $validated['loan_branch_id'] = null;

            return;
        }

        $branch = LoanBranch::query()->find($validated['loan_branch_id']);
        if ($branch) {
            $validated['branch'] = $branch->name;
        }
    }
}
