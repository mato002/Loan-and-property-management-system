<?php

namespace App\Jobs;

use App\Models\PmPayment;
use App\Services\Property\PropertyPaymentReceiptNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentReceiptJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $paymentId)
    {
        $this->onQueue('default');
    }

    public function handle(PropertyPaymentReceiptNotifier $notifier): void
    {
        $payment = PmPayment::query()->find($this->paymentId);
        if (! $payment) {
            return;
        }

        $result = $notifier->notify($payment);
        if (! ($result['skipped'] ?? false) && ! ($result['sent_sms'] ?? false) && ! ($result['sent_email'] ?? false)) {
            Log::info('Payment receipt not delivered', [
                'pm_payment_id' => $this->paymentId,
                'message' => $result['message'] ?? null,
            ]);
        }
    }
}
