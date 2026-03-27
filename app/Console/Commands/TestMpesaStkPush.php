<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\Integrations\MpesaDarajaService;
use Illuminate\Console\Command;

class TestMpesaStkPush extends Command
{
    protected $signature = 'mpesa:stk-test
        {phone : Phone number (e.g. 07xx..., 2547xx..., +2547xx...)}
        {amount=1 : Amount in KES (integer recommended for sandbox)}
        {--tenant_id= : Existing pm_tenants.id to attach this payment to (default: first tenant)}
        {--account_ref= : AccountReference (default: PM-{payment_id})}
        {--desc=Property payment : TransactionDesc}';

    protected $description = 'Initiate a Daraja STK Push using the configured MPESA_* settings and persist the pending payment record.';

    public function handle(): int
    {
        $daraja = app(MpesaDarajaService::class);

        if (! $daraja->isConfigured()) {
            $missing = implode(', ', $daraja->missingConfigKeys());
            $this->error('Daraja is not configured. Missing: '.$missing);
            $this->line('Fix your .env (MPESA_*) then run: php artisan config:clear');
            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');
        $msisdn = $daraja->normalizeMsisdn($phone);

        $amountRaw = $this->argument('amount');
        $amount = (int) round((float) $amountRaw);
        if ($amount <= 0) {
            $this->error('Amount must be > 0.');
            return self::FAILURE;
        }

        $tenantIdOpt = $this->option('tenant_id');
        /** @var PmTenant|null $tenant */
        $tenant = $tenantIdOpt
            ? PmTenant::query()->find((int) $tenantIdOpt)
            : PmTenant::query()->orderBy('id')->first();

        if (! $tenant) {
            $this->error('No tenant found. Provide --tenant_id=<pm_tenants.id> (and ensure you have seeded tenants).');
            return self::FAILURE;
        }

        /** @var PmPayment $payment */
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa_stk',
            'amount' => $amount,
            'external_ref' => null,
            'paid_at' => null,
            'status' => PmPayment::STATUS_PENDING,
            'meta' => [
                'intent' => 'stk_push_test',
                'phone' => $msisdn,
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        $accountRef = (string) ($this->option('account_ref') ?: ('PM-'.$payment->id));
        $desc = (string) ($this->option('desc') ?: 'Property payment');

        $this->info('Initiating STK push...');
        $this->line('Tenant: #'.$tenant->id.' '.$tenant->name);
        $this->line('MSISDN: '.$msisdn);
        $this->line('Amount: '.$amount);
        $this->line('PartyB(STK Shortcode): '.(string) config('services.mpesa.stk_shortcode'));
        $this->line('CallbackURL: '.(string) config('services.mpesa.stk_callback_url'));

        $init = $daraja->stkPush([
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $msisdn,
            'PartyB' => (string) config('services.mpesa.stk_shortcode'),
            'PhoneNumber' => $msisdn,
            'AccountReference' => $accountRef,
            'TransactionDesc' => $desc,
        ]);

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['daraja'] = array_merge(is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [], [
            'initiated_at' => now()->toIso8601String(),
            'request' => [
                'msisdn' => $msisdn,
                'amount' => $amount,
                'account_ref' => $accountRef,
                'desc' => $desc,
            ],
            'response' => $init,
        ]);
        $payment->update(['meta' => $meta]);

        if (($init['ok'] ?? false) !== true) {
            $this->error('STK initiation failed: '.(string) ($init['message'] ?? 'Unknown error'));
            $this->line(json_encode($init, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        $this->info('STK prompt sent. Check the phone to complete payment.');
        $this->line('Payment ID: '.$payment->id);
        $this->line(json_encode($init['body'] ?? $init, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}

