<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Console\Command;

class VerifyPmPayment extends Command
{
    protected $signature = 'pm:payment-verify {id : pm_payments.id}';

    protected $description = 'Query Daraja status for a pending STK payment and settle it if confirmed.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        /** @var PmPayment|null $payment */
        $payment = PmPayment::query()->find($id);
        if (! $payment) {
            $this->error('Payment not found: '.$id);
            return self::FAILURE;
        }

        if ($payment->status !== PmPayment::STATUS_PENDING) {
            $this->info('Payment already settled: '.$payment->status);
            return self::SUCCESS;
        }

        $checkout = (string) data_get($payment->meta, 'daraja.checkout_request_id', '');
        if ($checkout === '') {
            $this->error('Missing daraja.checkout_request_id in payment meta.');
            return self::FAILURE;
        }

        $daraja = app(MpesaDarajaService::class);
        $q = $daraja->stkQuery($checkout);

        $this->line('query_ok: '.(($q['ok'] ?? false) ? 'true' : 'false'));
        $this->line('query_message: '.(string) ($q['message'] ?? ''));

        if (($q['ok'] ?? false) !== true) {
            $body = is_array($q['body'] ?? null) ? $q['body'] : [];
            $resultCode = (string) ($body['ResultCode'] ?? '');
            $resultDesc = (string) ($body['ResultDesc'] ?? ($q['message'] ?? 'Not confirmed'));
            if ($resultCode !== '' && $resultCode !== '0') {
                app(PropertyPaymentSettlementService::class)->settlePending(
                    $payment->id,
                    'failed',
                    null,
                    null,
                    $resultDesc,
                    'daraja_stk_query',
                    null,
                );
                $this->warn('Payment marked failed from STK query: '.$resultCode.' '.$resultDesc);
            }

            return self::FAILURE;
        }

        $body = is_array($q['body'] ?? null) ? $q['body'] : [];
        $resultDesc = (string) ($body['ResultDesc'] ?? ($q['message'] ?? 'Confirmed via STK query'));

        app(PropertyPaymentSettlementService::class)->settlePending(
            $payment->id,
            'success',
            $payment->external_ref,
            now(),
            $resultDesc,
            'daraja_stk_query',
            (float) $payment->amount,
        );

        $fresh = $payment->fresh();
        $this->info('Payment settled.');
        $this->line('status: '.(string) $fresh->status);
        $this->line('amount: '.(string) $fresh->amount);
        $this->line('external_ref: '.(string) ($fresh->external_ref ?? ''));

        return self::SUCCESS;
    }
}

