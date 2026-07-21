<?php

namespace App\Jobs;

use App\Models\LmMessage;
use App\Services\Loan\LoanCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLoanBulkCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [60, 120, 300];

    public function __construct(public int $messageId)
    {
        $this->onQueue('default');
    }

    public function handle(LoanCommunicationService $service): void
    {
        $message = LmMessage::query()->with('recipients')->find($this->messageId);
        if (! $message) {
            return;
        }

        $service->queueMessageRecipients($message);
    }
}
