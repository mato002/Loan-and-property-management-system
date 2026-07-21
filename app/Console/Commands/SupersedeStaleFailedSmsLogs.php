<?php

namespace App\Console\Commands;

use App\Services\Property\RentReminderEligibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SupersedeStaleFailedSmsLogs extends Command
{
    protected $signature = 'property:supersede-stale-failed-sms-logs
                            {--from= : Only failed logs on/after this date (Y-m-d)}
                            {--to= : Only failed logs on/before this date (Y-m-d)}
                            {--dry-run : Report how many would be superseded without updating}';

    protected $description = 'Mark failed rent-reminder SMS logs as superseded when a successful send exists for the same invoice and phone';

    public function handle(RentReminderEligibilityService $eligibility): int
    {
        $from = $this->option('from') ? Carbon::parse((string) $this->option('from'))->startOfDay() : null;
        $to = $this->option('to') ? Carbon::parse((string) $this->option('to'))->startOfDay() : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $eligibility->supersedeStaleFailedSmsLogs($from, $to, $dryRun);

        $verb = $dryRun ? 'Would supersede' : 'Superseded';
        $this->info("Scanned {$result['scanned']} failed SMS log(s).");
        $this->info("{$verb} {$result['superseded']} row(s); skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
