<?php

namespace App\Observers;

use App\Models\LoanBookLoan;
use App\Services\ClientLeadPipelineService;

class LoanBookLoanClientLeadObserver
{
    public function __construct(
        private readonly ClientLeadPipelineService $pipeline,
    ) {}

    public function saved(LoanBookLoan $loan): void
    {
        if (! $loan->wasChanged('disbursed_at')) {
            return;
        }

        if ($loan->disbursed_at === null) {
            return;
        }

        $this->pipeline->syncFromLoanBookLoan($loan);
    }
}
