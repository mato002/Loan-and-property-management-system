<?php

namespace App\Repositories\Equity;

use App\Models\EquitySyncRun;
use App\Models\Payment;
use App\Models\PmPayment;
use App\Models\UnassignedPayment;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EquityPaymentRepository
{
    public function transactionExists(string $transactionId): bool
    {
        return Payment::query()->where('transaction_id', $transactionId)->exists();
    }

    public function latestTransactionDate(): ?Carbon
    {
        $value = Payment::query()->max('transaction_date');
        if (! $value) {
            return null;
        }

        return Carbon::parse((string) $value);
    }

    public function storeMatched(array $tx, int $tenantId, string $matchedBy, array $options = []): Payment
    {
        return DB::transaction(function () use ($tx, $tenantId, $matchedBy, $options) {
            $paymentMethod = (string) ($options['payment_method'] ?? 'equity');
            $channel = (string) ($options['channel'] ?? 'equity_paybill');
            $source = (string) ($options['source'] ?? 'equity_api');
            $provider = (string) ($options['provider'] ?? 'equity');

            $payment = Payment::query()->create([
                'tenant_id' => $tenantId,
                'amount' => (float) $tx['amount'],
                'transaction_id' => (string) $tx['transaction_id'],
                'account_number' => $tx['account_number'] ?? null,
                'phone' => $tx['phone'] ?? null,
                'reference' => $tx['reference'] ?? null,
                'payment_method' => $paymentMethod,
                'status' => 'matched',
                'transaction_date' => $tx['transaction_date'] ?? now(),
                'raw_payload' => $tx['raw_payload'] ?? null,
            ]);

            $pmPayment = PmPayment::query()->create([
                'pm_tenant_id' => $tenantId,
                'channel' => $channel,
                'amount' => (float) $tx['amount'],
                'external_ref' => (string) $tx['transaction_id'],
                'paid_at' => $tx['transaction_date'] ?? now(),
                'status' => PmPayment::STATUS_PENDING,
                'meta' => [
                    'source' => $source,
                    'provider' => $provider,
                    'matched_by' => $matchedBy,
                    'account_number' => $tx['account_number'] ?? null,
                    'phone' => $tx['phone'] ?? null,
                    'reference' => $tx['reference'] ?? null,
                    'raw_payload' => $tx['raw_payload'] ?? null,
                ],
            ]);

            app(PropertyPaymentSettlementService::class)->complete(
                $pmPayment,
                (string) $tx['transaction_id'],
                $tx['transaction_date'] ?? now(),
                (string) ($options['message'] ?? 'Automatically settled from Equity API sync.'),
                $source,
                (float) $tx['amount']
            );

            $payment->pm_payment_id = $pmPayment->id;
            $payment->save();

            return $payment;
        });
    }

    public function storeUnmatched(array $tx, string $reason, array $options = []): Payment
    {
        return DB::transaction(function () use ($tx, $reason, $options) {
            $payment = Payment::query()->create([
                'tenant_id' => null,
                'amount' => (float) $tx['amount'],
                'transaction_id' => (string) $tx['transaction_id'],
                'account_number' => $tx['account_number'] ?? null,
                'phone' => $tx['phone'] ?? null,
                'reference' => $tx['reference'] ?? null,
                'payment_method' => (string) ($options['payment_method'] ?? 'equity'),
                'status' => 'unmatched',
                'transaction_date' => $tx['transaction_date'] ?? now(),
                'raw_payload' => $tx['raw_payload'] ?? null,
            ]);

            UnassignedPayment::query()->updateOrCreate(
                ['transaction_id' => (string) $tx['transaction_id']],
                [
                    'amount' => (float) $tx['amount'],
                    'account_number' => $tx['account_number'] ?? null,
                    'phone' => $tx['phone'] ?? null,
                    'reason' => $reason,
                    'created_at' => now(),
                ]
            );

            return $payment;
        });
    }

    public function startSyncRun(string $trigger): EquitySyncRun
    {
        return EquitySyncRun::query()->create([
            'status' => 'running',
            'trigger' => $trigger,
            'started_at' => now(),
            'fetched_count' => 0,
            'matched_count' => 0,
            'unmatched_count' => 0,
            'duplicate_count' => 0,
            'error_count' => 0,
            'message' => null,
        ]);
    }

    public function completeSyncRun(EquitySyncRun $run, array $stats, string $status = 'success', ?string $message = null): EquitySyncRun
    {
        $run->update([
            'status' => $status,
            'finished_at' => now(),
            'fetched_count' => (int) ($stats['fetched'] ?? 0),
            'matched_count' => (int) ($stats['matched'] ?? 0),
            'unmatched_count' => (int) ($stats['unmatched'] ?? 0),
            'duplicate_count' => (int) ($stats['duplicates'] ?? 0),
            'error_count' => (int) ($stats['errors'] ?? 0),
            'message' => $message,
        ]);

        return $run->fresh();
    }
}

