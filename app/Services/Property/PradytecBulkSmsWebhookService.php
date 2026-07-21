<?php

namespace App\Services\Property;

use App\Models\MpesaPlatformTransaction;
use App\Services\BulkSmsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PradytecBulkSmsWebhookService
{
    public function __construct(
        private BulkSmsService $bulkSms,
        private PropertyCommunicationService $communications,
    ) {}

    public function handle(string $event, array $payload): void
    {
        $data = (array) ($payload['data'] ?? []);

        match ($event) {
            'balance.updated' => $this->handleBalanceUpdated($data),
            'topup.completed' => $this->handleTopupCompleted($data),
            'topup.failed' => $this->handleTopupFailed($data),
            'message.delivered' => $this->handleMessageStatus($data, 'delivered'),
            'message.failed' => $this->handleMessageStatus($data, 'failed'),
            default => Log::info('Pradytec webhook: unhandled event', ['event' => $event]),
        };
    }

    private function handleBalanceUpdated(array $data): void
    {
        $previous = $this->bulkSms->cachedProviderBalance();
        $balance = \Illuminate\Support\Arr::get($data, 'new_balance');
        if ($balance === null || $balance === '') {
            return;
        }

        $newBalance = (float) $balance;
        $this->bulkSms->rememberProviderBalance($newBalance, 'balance.updated');
        app(SmsWalletMonitoringService::class)->handleBalanceChange($previous, $newBalance);
    }

    private function handleTopupCompleted(array $data): void
    {
        $previous = $this->bulkSms->cachedProviderBalance();
        $balance = \Illuminate\Support\Arr::get($data, 'new_balance');
        if ($balance !== null && $balance !== '') {
            $newBalance = (float) $balance;
            $this->bulkSms->rememberProviderBalance($newBalance, 'topup.completed');
            app(SmsWalletMonitoringService::class)->handleBalanceChange($previous, $newBalance);
        }

        $this->syncTopupTransaction($data, 'completed');
    }

    private function handleTopupFailed(array $data): void
    {
        $this->syncTopupTransaction($data, 'failed');
    }

    private function syncTopupTransaction(array $data, string $status): void
    {
        if (! Schema::hasTable('mpesa_platform_transactions')) {
            return;
        }

        $transactionId = trim((string) Arr::get($data, 'transaction_id', ''));
        if ($transactionId === '') {
            return;
        }

        $txn = MpesaPlatformTransaction::query()
            ->where('transaction_id', $transactionId)
            ->orWhere('meta->provider->transaction_id', $transactionId)
            ->latest('id')
            ->first();

        if (! $txn) {
            return;
        }

        $meta = (array) ($txn->meta ?? []);
        $meta['provider'] = array_merge((array) ($meta['provider'] ?? []), [
            'webhook_status' => $status,
            'webhook_at' => now()->toIso8601String(),
            'mpesa_receipt' => Arr::get($data, 'mpesa_receipt', Arr::get($data, 'receipt')),
        ]);

        $txn->update([
            'status' => $status === 'completed' ? 'completed' : 'failed',
            'meta' => $meta,
            'result_desc' => (string) Arr::get($data, 'failure_reason', Arr::get($data, 'message', '')),
        ]);
    }

    private function handleMessageStatus(array $data, string $status): void
    {
        $messageId = trim((string) Arr::get($data, 'message_id', Arr::get($data, 'provider_message_id', '')));
        if ($messageId === '') {
            return;
        }

        $payload = array_merge($data, [
            'status' => $status,
            'delivery_status' => $status,
        ]);

        $this->communications->markProviderCallback('bulksms', $messageId, $payload);
        app(\App\Services\Loan\LoanCommunicationService::class)->markProviderCallback('bulksms', $messageId, $payload);
    }
}
