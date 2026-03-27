<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use Illuminate\Console\Command;

class ShowPmPayment extends Command
{
    protected $signature = 'pm:payment-show {id : pm_payments.id}';

    protected $description = 'Show a property management payment (pm_payments) status and key callback fields.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        /** @var PmPayment|null $p */
        $p = PmPayment::query()->find($id);
        if (! $p) {
            $this->error('Payment not found: id='.$id);
            return self::FAILURE;
        }

        $this->info('pm_payments #'.$p->id);
        $this->line('status: '.$p->status);
        $this->line('amount: '.$p->amount);
        $this->line('paid_at: '.($p->paid_at?->toDateTimeString() ?? '—'));
        $this->line('external_ref: '.($p->external_ref ?? '—'));
        $this->line('channel: '.$p->channel);

        $checkout = (string) data_get($p->meta, 'daraja.checkout_request_id', '');
        $resultCode = data_get($p->meta, 'daraja.result_code');
        $resultDesc = (string) data_get($p->meta, 'daraja.result_desc', '');
        $cbAmount = data_get($p->meta, 'callback.amount');

        $this->line('daraja.checkout_request_id: '.($checkout !== '' ? $checkout : '—'));
        $this->line('daraja.result_code: '.($resultCode !== null ? (string) $resultCode : '—'));
        $this->line('daraja.result_desc: '.($resultDesc !== '' ? $resultDesc : '—'));
        $this->line('callback.amount: '.($cbAmount !== null ? (string) $cbAmount : '—'));

        return self::SUCCESS;
    }
}

