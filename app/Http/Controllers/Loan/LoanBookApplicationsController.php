<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookApplication;
use App\Models\LoanClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanBookApplicationsController extends Controller
{
    public function index(): View
    {
        $applications = LoanBookApplication::query()
            ->with('loanClient')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('loan.book.applications.index', [
            'title' => 'Loan applications',
            'subtitle' => 'Customer LoanBook pipeline — from submission to disbursement.',
            'applications' => $applications,
        ]);
    }

    public function report(): View
    {
        $applications = LoanBookApplication::query()
            ->with('loanClient')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('loan.book.applications.report', [
            'title' => 'Application loans report',
            'subtitle' => 'Export-style listing for committee and MIS.',
            'applications' => $applications,
        ]);
    }

    public function create(): View
    {
        return view('loan.book.applications.create', [
            'title' => 'Create application',
            'subtitle' => 'Start a new LoanBook file for an onboarded client.',
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'stages' => $this->stageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $client = LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        if (empty($validated['branch'])) {
            $validated['branch'] = $client->branch;
        }

        $next = (LoanBookApplication::query()->max('id') ?? 0) + 1;
        $validated['reference'] = 'APP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        $validated['submitted_at'] = now();

        LoanBookApplication::query()->create($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application saved.'));
    }

    public function edit(LoanBookApplication $loan_book_application): View
    {
        return view('loan.book.applications.edit', [
            'title' => 'Edit application',
            'subtitle' => $loan_book_application->reference,
            'application' => $loan_book_application,
            'clients' => LoanClient::query()->clients()->orderBy('last_name')->orderBy('first_name')->get(),
            'stages' => $this->stageOptions(),
        ]);
    }

    public function update(Request $request, LoanBookApplication $loan_book_application): RedirectResponse
    {
        $validated = $request->validate([
            'loan_client_id' => ['required', 'exists:loan_clients,id'],
            'product_name' => ['required', 'string', 'max:160'],
            'amount_requested' => ['required', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'stage' => ['required', 'string', 'in:'.implode(',', array_keys($this->stageOptions()))],
            'branch' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        LoanClient::query()->clients()->findOrFail($validated['loan_client_id']);
        $loan_book_application->update($validated);

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application updated.'));
    }

    public function destroy(LoanBookApplication $loan_book_application): RedirectResponse
    {
        if ($loan_book_application->loan()->exists()) {
            return redirect()
                ->route('loan.book.applications.index')
                ->with('error', __('Cannot delete an application that already has a loan record.'));
        }

        $loan_book_application->delete();

        return redirect()
            ->route('loan.book.applications.index')
            ->with('status', __('Application removed.'));
    }

    /**
     * @return array<string, string>
     */
    private function stageOptions(): array
    {
        return [
            LoanBookApplication::STAGE_SUBMITTED => 'Submitted',
            LoanBookApplication::STAGE_CREDIT_REVIEW => 'Credit review',
            LoanBookApplication::STAGE_APPROVED => 'Approved',
            LoanBookApplication::STAGE_DECLINED => 'Declined',
            LoanBookApplication::STAGE_DISBURSED => 'Disbursed',
        ];
    }
}
