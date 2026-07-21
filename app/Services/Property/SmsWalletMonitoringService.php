<?php

namespace App\Services\Property;

use App\Models\PmMessageLog;
use App\Models\PmMessageRecipient;
use App\Services\BulkSmsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SmsWalletMonitoringService
{
    public function __construct(private readonly BulkSmsService $bulkSms)
    {
    }

    public function monitorAndNotify(): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        $wallet = $this->bulkSms->walletStatusForUi();
        $this->notifyForWalletStatus($wallet);
        $this->notifyForQueuedPressure();
    }

    /**
     * @param  array<string, mixed>  $wallet
     */
    public function notifyForWalletStatus(array $wallet): void
    {
        $status = (string) ($wallet['status'] ?? 'unknown');
        $currency = (string) ($wallet['currency'] ?? 'KES');
        $balance = number_format((float) ($wallet['balance'] ?? 0), 2);
        $maxRecipients = (int) ($wallet['max_recipients'] ?? 0);
        $detail = (string) ($wallet['detail'] ?? '');

        if ($status === 'empty') {
            $this->raiseSystemAlert(
                'empty',
                '[SMS WALLET] Balance depleted',
                "SMS wallet is empty ({$balance} {$currency}). Outbound SMS will fail until you top up. {$detail}",
                cooldownMinutes: 120,
            );
        } elseif ($status === 'low') {
            $this->raiseSystemAlert(
                'low',
                '[SMS WALLET] Low balance warning',
                "Only about {$maxRecipients} SMS remaining ({$balance} {$currency}). Top up soon to avoid failed sends. {$detail}",
                cooldownMinutes: 360,
            );
        } elseif ($status === 'unknown') {
            $this->raiseSystemAlert(
                'unknown',
                '[SMS WALLET] Balance could not be verified',
                (string) ($wallet['provider_error'] ?? $detail ?: 'Provider balance is unreachable. Sending may fail until connectivity is restored.'),
                cooldownMinutes: 180,
            );
        }
    }

    public function handleBalanceChange(?float $previousBalance, float $newBalance): void
    {
        if ($previousBalance !== null && $newBalance < $previousBalance) {
            $drop = round($previousBalance - $newBalance, 4);
            $threshold = max($this->bulkSms->costPerSms() * 5, 1.0);
            if ($drop >= $threshold) {
                $currency = $this->bulkSms->currency();
                $this->raiseSystemAlert(
                    'balance_drop',
                    '[SMS WALLET] Balance decreased',
                    sprintf(
                        'Provider SMS balance dropped by %s %s (was %s, now %s). Review queued sends and top up if needed.',
                        number_format($drop, 2),
                        $currency,
                        number_format($previousBalance, 2),
                        number_format($newBalance, 2),
                    ),
                    cooldownMinutes: 60,
                );
            }
        }

        $this->monitorAndNotify();
    }

    /**
     * @param  array{ok?:bool,error?:string,required?:float,available?:float,currency?:string}  $afford
     */
    public function notifyBatchShortfall(int $recipientCount, array $afford, ?int $messageId = null): void
    {
        if ($recipientCount < 1) {
            return;
        }

        $available = isset($afford['available']) ? (float) $afford['available'] : null;
        $required = isset($afford['required']) ? (float) $afford['required'] : round($recipientCount * $this->bulkSms->costPerSms(), 4);
        $currency = (string) ($afford['currency'] ?? $this->bulkSms->currency());
        $maxAffordable = $this->bulkSms->costPerSms() > 0 && $available !== null
            ? (int) floor($available / $this->bulkSms->costPerSms())
            : 0;
        $shortfall = max(0, $recipientCount - $maxAffordable);

        if ($shortfall <= 0 && ($afford['ok'] ?? false)) {
            return;
        }

        $dedupe = 'batch_shortfall'.($messageId ? ':'.$messageId : '');
        $this->raiseSystemAlert(
            $dedupe,
            '[SMS WALLET] Insufficient balance for queued SMS',
            sprintf(
                '%d SMS queued but balance covers about %d (%s %s available, need %s %s). %d message(s) may fail unless you top up.',
                $recipientCount,
                $maxAffordable,
                number_format((float) ($available ?? 0), 2),
                $currency,
                number_format($required, 2),
                $currency,
                $shortfall > 0 ? $shortfall : $recipientCount,
            ),
            cooldownMinutes: 30,
        );
    }

    public function notifyForQueuedPressure(?int $messageId = null): void
    {
        $pending = $this->countPendingSmsRecipients();
        if ($pending < 1) {
            return;
        }

        $afford = $this->bulkSms->canAffordRecipients($pending);
        if ($afford['ok'] ?? false) {
            return;
        }

        $this->notifyBatchShortfall($pending, $afford, $messageId);
    }

    public function notifySendFailureDueToBalance(string $reason, ?int $userId = null): void
    {
        if (! str_contains(strtolower($reason), 'insufficient')) {
            return;
        }

        $this->raiseSystemAlert(
            'send_blocked',
            '[SMS WALLET] SMS send blocked — insufficient balance',
            $reason.' Top up your SMS wallet to resume queued sends.',
            $userId,
            cooldownMinutes: 15,
        );
        $this->notifyForQueuedPressure();
    }

    public function countPendingSmsRecipients(): int
    {
        if (! Schema::hasTable('pm_message_recipients')) {
            return 0;
        }

        return (int) PmMessageRecipient::query()
            ->withoutGlobalScopes()
            ->where('channel', 'sms')
            ->whereIn('status', ['queued', 'scheduled', 'sending'])
            ->count();
    }

    private function raiseSystemAlert(
        string $type,
        string $subject,
        string $body,
        ?int $userId = null,
        int $cooldownMinutes = 60,
    ): void {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        $cooldownMinutes = max(5, (int) config('bulksms.alert_cooldown_minutes', $cooldownMinutes));
        $cacheKey = 'sms_wallet_system_alert:'.sha1($type);

        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            PmMessageLog::query()->create([
                'user_id' => $userId,
                'channel' => 'system',
                'to_address' => null,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => 'sent',
                'delivery_error' => null,
                'sent_at' => now(),
            ]);

            Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));
        } catch (\Throwable) {
            // Never block sends or webhooks if alerting fails.
        }
    }
}
