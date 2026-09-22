<?php

namespace App\Jobs;

use App\Models\PmLandlordPayout;
use App\Services\Property\LandlordRemittanceAdviceNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLandlordRemittanceAdviceJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $payoutId)
    {
        $this->onQueue('default');
    }

    public function handle(LandlordRemittanceAdviceNotifier $notifier): void
    {
        $payout = PmLandlordPayout::query()->with('items.landlord', 'items.property')->find($this->payoutId);
        if (! $payout || $payout->status !== 'paid') {
            return;
        }

        $result = $notifier->notify($payout);
        if (! ($result['sent_email'] ?? false) && ! ($result['sent_sms'] ?? false)) {
            Log::info('Landlord remittance advice not delivered', [
                'payout_id' => $this->payoutId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
