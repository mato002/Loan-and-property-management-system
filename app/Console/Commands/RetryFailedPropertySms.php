<?php

namespace App\Console\Commands;

use App\Services\Loan\LoanFailedSmsRetryService;
use App\Services\Property\PropertyFailedSmsRetryService;
use Illuminate\Console\Command;

class RetryFailedPropertySms extends Command
{
    protected $signature = 'communications:retry-failed-sms {--limit= : Max SMS retries per module per run}';

    protected $description = 'Automatically retry failed property and loan SMS (balance restored, provider busy, etc.)';

    public function handle(
        PropertyFailedSmsRetryService $propertyRetryService,
        LoanFailedSmsRetryService $loanRetryService,
    ): int {
        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;

        if ($propertyRetryService->enabled()) {
            $property = $propertyRetryService->retryDue($limit);
            $this->info(sprintf(
                'Property SMS auto-retry: attempted=%d sent=%d skipped=%d failed=%d',
                $property['attempted'],
                $property['sent'],
                $property['skipped'],
                $property['failed'],
            ));
        } else {
            $this->info('Property SMS auto-retry is disabled (PROPERTY_SMS_AUTO_RETRY_ENABLED=false).');
        }

        if ($loanRetryService->enabled()) {
            $loan = $loanRetryService->retryDue($limit);
            $this->info(sprintf(
                'Loan SMS auto-retry: attempted=%d sent=%d skipped=%d failed=%d',
                $loan['attempted'],
                $loan['sent'],
                $loan['skipped'],
                $loan['failed'],
            ));
        } else {
            $this->info('Loan SMS auto-retry is disabled (LOAN_SMS_AUTO_RETRY_ENABLED=false).');
        }

        return self::SUCCESS;
    }
}
