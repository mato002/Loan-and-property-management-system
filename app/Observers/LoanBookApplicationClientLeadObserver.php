<?php

namespace App\Observers;

use App\Models\LoanBookApplication;
use App\Services\ClientLeadPipelineService;

class LoanBookApplicationClientLeadObserver
{
    public function __construct(
        private readonly ClientLeadPipelineService $pipeline,
    ) {}

    public function saved(LoanBookApplication $application): void
    {
        if (! $application->wasChanged('stage')) {
            return;
        }

        $this->pipeline->syncFromLoanBookApplication($application);
    }
}
