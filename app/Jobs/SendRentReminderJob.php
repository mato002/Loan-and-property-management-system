<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SendRentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [300, 600];

    public function __construct(public string $date, public bool $force = false)
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        Artisan::call('rent:send-reminders', [
            '--date' => $this->date,
        ]);
    }
}
