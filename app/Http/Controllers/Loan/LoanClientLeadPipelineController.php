<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\Concerns\ScopesLoanPortfolioAccess;
use App\Models\ClientLead;
use App\Models\LoanClient;
use App\Services\ClientLeadPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanClientLeadPipelineController extends Controller
{
    use ScopesLoanPortfolioAccess;

    public function __construct(
        private readonly ClientLeadPipelineService $pipeline,
    ) {}

    public function updateStage(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLeadPipeline($loan_client);

        $validated = $request->validate([
            'stage' => [
                'required',
                'string',
                Rule::in([
                    ClientLead::STAGE_NEW,
                    ClientLead::STAGE_CONTACTED,
                    ClientLead::STAGE_INTERESTED,
                    ClientLead::STAGE_APPLIED,
                    ClientLead::STAGE_APPROVED,
                    ClientLead::STAGE_DISBURSED,
                    ClientLead::STAGE_DROPPED,
                ]),
            ],
        ]);

        $lead = $this->pipeline->ensureForLoanClient($loan_client);

        try {
            $this->pipeline->transitionToStage($lead, (string) $validated['stage'], $request->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['stage' => $e->getMessage()]);
        }

        return back()->with('status', 'Pipeline stage updated.');
    }

    public function storeActivity(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLeadPipeline($loan_client);

        $validated = $request->validate([
            'activity_type' => ['required', 'string', Rule::in(['call', 'visit', 'sms', 'whatsapp', 'system'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'next_action_date' => ['nullable', 'date'],
        ]);

        $lead = $this->pipeline->ensureForLoanClient($loan_client);
        $this->pipeline->recordActivity(
            $lead,
            $request->user(),
            (string) $validated['activity_type'],
            isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            isset($validated['next_action_date']) ? (string) $validated['next_action_date'] : null,
        );

        return back()->with('status', 'Activity logged.');
    }

    public function storeLoss(Request $request, LoanClient $loan_client): RedirectResponse
    {
        $this->ensureLeadPipeline($loan_client);

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                Rule::in(['high_interest', 'no_documents', 'not_reachable', 'competitor', 'changed_mind', 'other']),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = $this->pipeline->ensureForLoanClient($loan_client);

        try {
            $this->pipeline->recordLoss(
                $lead,
                $request->user(),
                (string) $validated['reason'],
                isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Lead marked as dropped with reason.');
    }

    private function ensureLeadPipeline(LoanClient $loan_client): void
    {
        $this->ensureLoanClientAccessible($loan_client);
        abort_unless($loan_client->kind === LoanClient::KIND_LEAD, 404);
    }
}
