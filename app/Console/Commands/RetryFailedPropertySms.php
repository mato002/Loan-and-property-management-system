<?php

namespace App\Console\Commands;

use App\Services\Property\PropertyFailedSmsRetryService;
use Illuminate\Console\Command;

class RetryFailedPropertySms extends Command
{
    protected $signature = 'communications:retry-failed-sms {--limit= : Max SMS retries per run}';

    protected $description = 'Automatically retry failed property SMS (balance restored, provider busy, etc.)';

    public function handle(PropertyFailedSmsRetryService $retryService): int
    {
        if (! $retryService->enabled()) {
            $this->info('Property SMS auto-retry is disabled (PROPERTY_SMS_AUTO_RETRY_ENABLED=false).');

            return self::SUCCESS;
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;

        $result = $retryService->retryDue($limit);

        $this->info(sprintf(
            'SMS auto-retry: attempted=%d sent=%d skipped=%d failed=%d',
            $result['attempted'],
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
