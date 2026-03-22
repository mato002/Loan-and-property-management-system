<?php

namespace App\Console\Commands;

use App\Models\SmsSchedule;
use App\Services\BulkSmsService;
use Illuminate\Console\Command;

class DispatchScheduledBulkSms extends Command
{
    protected $signature = 'bulksms:dispatch-schedules';

    protected $description = 'Send due bulk SMS schedules and debit the SMS wallet';

    public function handle(BulkSmsService $bulkSms): int
    {
        $due = SmsSchedule::query()
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get();

        foreach ($due as $schedule) {
            $bulkSms->dispatchSchedule($schedule);
        }

        return self::SUCCESS;
    }
}
