<?php

namespace App\Console\Commands;

use App\Models\LmMessage;
use App\Models\PmMessage;
use App\Services\Loan\LoanCommunicationService;
use App\Services\Property\PropertyCommunicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DispatchScheduledCommunications extends Command
{
    protected $signature = 'communications:dispatch-scheduled {--limit=100 : Max messages per module per run}';

    protected $description = 'Release due scheduled property and loan communications messages';

    public function handle(
        PropertyCommunicationService $propertyCommunications,
        LoanCommunicationService $loanCommunications,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $property = ['released' => 0, 'skipped' => 0, 'failed' => 0];
        $loan = ['released' => 0, 'skipped' => 0, 'failed' => 0];

        if (Schema::hasTable('pm_messages')) {
            $property = $propertyCommunications->releaseDueScheduledMessages($limit);
        }

        if (Schema::hasTable('lm_messages')) {
            $loan = $loanCommunications->releaseDueScheduledMessages($limit);
        }

        $this->info(sprintf(
            'Property: released=%d skipped=%d failed=%d | Loan: released=%d skipped=%d failed=%d',
            $property['released'],
            $property['skipped'],
            $property['failed'],
            $loan['released'],
            $loan['skipped'],
            $loan['failed'],
        ));

        return self::SUCCESS;
    }
}
