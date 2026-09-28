<?php

namespace App\Services\Property;

use App\Mail\LandlordRemittanceAdviceMail;
use App\Models\PmLandlordPayout;
use App\Models\User;
use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LandlordRemittanceAdviceNotifier
{
    public function __construct(private readonly BulkSmsService $sms) {}

    /**
     * @return array{sent_email:bool, sent_sms:bool, message:?string}
     */
    public function notify(PmLandlordPayout $payout): array
    {
        $payout->loadMissing(['items.landlord', 'items.property']);
        $meta = is_array($payout->payout_meta) ? $payout->payout_meta : [];
        if (! empty($meta['remittance_notified_at'])) {
            return ['sent_email' => false, 'sent_sms' => false, 'message' => 'Already notified.'];
        }

        $item = $payout->items->first();
        $landlord = $item?->landlord;
        if (! $landlord instanceof User) {
            return ['sent_email' => false, 'sent_sms' => false, 'message' => 'No landlord on payout.'];
        }

        $advice = [
            'payout_id' => (int) $payout->id,
            'amount' => PropertyMoney::kes((float) $payout->total_amount),
            'property' => (string) ($item?->property?->name ?? 'Property'),
            'period' => (string) ($item?->period_month ?? '—'),
            'paid_at' => $payout->paid_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
            'mpesa_txn' => $payout->payout_transaction_id ? (string) $payout->payout_transaction_id : null,
            'phone' => $payout->payout_phone ? (string) $payout->payout_phone : (string) ($landlord->phone ?? ''),
        ];

        $sentEmail = false;
        $sentSms = false;

        $email = trim((string) ($landlord->email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($email)->send(new LandlordRemittanceAdviceMail(
                    landlordName: (string) $landlord->name,
                    advice: $advice,
                ));
                $sentEmail = true;
            } catch (Throwable $e) {
                Log::warning('Remittance advice email failed', [
                    'payout_id' => $payout->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $phone = trim((string) ($advice['phone'] ?? ''));
        if ($phone !== '') {
            try {
                $phones = $this->sms->normalizeRecipientList($phone);
                if ($phones !== []) {
                    $brand = trim((string) (\App\Support\Property\PropertyWorkspaceBranding::get('company_name', config('app.name')) ?? config('app.name')));
                    $body = sprintf(
                        '%s remittance: %s paid for %s (%s). Payout #%d%s. Thank you.',
                        $brand,
                        $advice['amount'],
                        $advice['property'],
                        $advice['period'],
                        $advice['payout_id'],
                        $advice['mpesa_txn'] ? '. Ref '.$advice['mpesa_txn'] : ''
                    );
                    $result = $this->sms->sendNow($body, $phones, null, null, 'property');
                    $sentSms = ($result['ok'] ?? false) === true;
                }
            } catch (Throwable $e) {
                Log::warning('Remittance advice SMS failed', [
                    'payout_id' => $payout->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sentEmail || $sentSms) {
            $meta['remittance_notified_at'] = now()->toIso8601String();
            $meta['remittance_channels'] = [
                'email' => $sentEmail,
                'sms' => $sentSms,
            ];
            $payout->update(['payout_meta' => $meta]);
        }

        return [
            'sent_email' => $sentEmail,
            'sent_sms' => $sentSms,
            'message' => ($sentEmail || $sentSms) ? null : 'No landlord email/SMS channel available.',
        ];
    }
}
