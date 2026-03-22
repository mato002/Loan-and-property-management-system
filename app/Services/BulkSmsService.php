<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\SmsSchedule;
use App\Models\SmsWallet;
use App\Models\SmsWalletTopup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BulkSmsService
{
    public function costPerSms(): float
    {
        return max(0.0001, (float) config('bulksms.cost_per_sms', 0.5));
    }

    public function currency(): string
    {
        return (string) config('bulksms.currency', 'KES');
    }

    /**
     * @return list<string>
     */
    public function normalizeRecipientList(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $digits = preg_replace('/\D+/', '', trim($p));
            if ($digits !== '' && strlen($digits) >= 9) {
                $out[] = $digits;
            }
        }

        return array_values(array_unique($out));
    }

    public function walletBalance(): string
    {
        return (string) SmsWallet::singleton()->balance;
    }

    /**
     * @param  list<string>  $phones
     * @return array{ok: bool, error?: string, sent?: int, charged?: float}
     */
    public function sendNow(string $message, array $phones, ?int $userId = null, ?int $scheduleId = null): array
    {
        if ($phones === []) {
            return ['ok' => false, 'error' => 'Add at least one valid phone number.'];
        }

        $cost = $this->costPerSms();
        $total = round(count($phones) * $cost, 4);

        return DB::transaction(function () use ($message, $phones, $userId, $scheduleId, $cost, $total) {
            /** @var SmsWallet $wallet */
            $wallet = SmsWallet::query()->lockForUpdate()->firstOrFail();

            if ((float) $wallet->balance < $total) {
                return [
                    'ok' => false,
                    'error' => sprintf(
                        'Insufficient wallet balance. Need %s %s for %d message(s); available %s %s.',
                        number_format($total, 2),
                        $this->currency(),
                        count($phones),
                        number_format((float) $wallet->balance, 2),
                        $this->currency()
                    ),
                ];
            }

            $now = now();
            foreach ($phones as $phone) {
                SmsLog::create([
                    'user_id' => $userId,
                    'sms_schedule_id' => $scheduleId,
                    'phone' => $phone,
                    'message' => $message,
                    'status' => 'sent',
                    'charged_amount' => $cost,
                    'sent_at' => $now,
                ]);
            }

            $wallet->balance = round((float) $wallet->balance - $total, 2);
            $wallet->save();

            return ['ok' => true, 'sent' => count($phones), 'charged' => $total];
        });
    }

    public function topup(float $amount, ?string $reference, ?string $notes): void
    {
        DB::transaction(function () use ($amount, $reference, $notes) {
            /** @var SmsWallet $wallet */
            $wallet = SmsWallet::query()->lockForUpdate()->firstOrFail();
            $wallet->balance = round((float) $wallet->balance + $amount, 2);
            $wallet->save();

            SmsWalletTopup::create([
                'user_id' => Auth::id(),
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
            ]);
        });
    }

    public function createSchedule(
        string $message,
        array $phones,
        \DateTimeInterface $when,
        ?int $templateId,
        ?int $userId
    ): SmsSchedule {
        return SmsSchedule::create([
            'user_id' => $userId,
            'sms_template_id' => $templateId,
            'body' => $message,
            'recipients' => $phones,
            'scheduled_at' => $when,
            'status' => 'pending',
        ]);
    }

    public function dispatchSchedule(SmsSchedule $schedule): bool
    {
        if ($schedule->status !== 'pending') {
            return false;
        }

        $schedule->update(['status' => 'processing']);

        try {
            $phones = is_array($schedule->recipients) ? $schedule->recipients : [];
            $result = $this->sendNow(
                $schedule->body,
                $phones,
                $schedule->user_id,
                $schedule->id
            );

            if ($result['ok']) {
                $schedule->update([
                    'status' => 'sent',
                    'processed_at' => now(),
                    'failure_reason' => null,
                ]);

                return true;
            }

            $schedule->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $result['error'] ?? 'Send failed.',
            ]);
        } catch (\Throwable $e) {
            $schedule->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
