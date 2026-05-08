<?php

namespace App\Jobs;

use App\Models\PmMessageRecipient;
use App\Services\Property\PropertyCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $recipientId)
    {
    }

    public function handle(PropertyCommunicationService $service): void
    {
        $recipient = PmMessageRecipient::query()->with('message')->find($this->recipientId);
        if (! $recipient) {
            return;
        }

        $service->dispatchSmsRecipient($recipient);
    }
}
