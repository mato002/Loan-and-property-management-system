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

        $dueCount = $due->count();
        $sent = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            if ($bulkSms->dispatchSchedule($schedule)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->info("Bulk SMS schedules: due={$dueCount}, sent={$sent}, failed={$failed}.");

        return self::SUCCESS;
    }
}
