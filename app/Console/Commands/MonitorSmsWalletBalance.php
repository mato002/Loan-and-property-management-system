<?php

namespace App\Console\Commands;

use App\Services\Property\SmsWalletMonitoringService;
use Illuminate\Console\Command;

class MonitorSmsWalletBalance extends Command
{
    protected $signature = 'sms:monitor-wallet';

    protected $description = 'Check SMS wallet balance and queued SMS pressure; raise system notifications when action is needed.';

    public function handle(SmsWalletMonitoringService $monitor): int
    {
        $monitor->monitorAndNotify();
        $this->info('SMS wallet monitoring completed.');

        return self::SUCCESS;
    }
}
