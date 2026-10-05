<?php

namespace App\Jobs;

use App\Models\PmPayment;
use App\Services\Property\PropertyPaymentReceiptNotifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPaymentReceiptJob implements ShouldQueue, ShouldBeUnique
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /** Keep duplicates out of the queue while one receipt job is pending/running. */
    public int $uniqueFor = 900;

    public function __construct(public readonly int $paymentId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'payment-receipt-'.$this->paymentId;
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
