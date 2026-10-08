<?php

namespace App\Repositories\Equity;

use App\Models\EquitySyncRun;
use App\Models\Payment;
use App\Models\PmPayment;
use App\Models\UnassignedPayment;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EquityPaymentRepository
{
    public function transactionExists(string $transactionId): bool
    {
        return Payment::query()
            ->where(function ($query) use ($transactionId): void {
                $query->where('transaction_id', $transactionId);
                if (Schema::hasColumn('payments', 'external_transaction_reference')) {
                    $query->orWhere('external_transaction_reference', $transactionId);
                }
            })
            ->exists();
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
            $agentUserId = $this->resolveAgentUserId($options, $tenantId);

            $paymentAttrs = [
                'tenant_id' => $tenantId,
                'amount' => (float) $tx['amount'],
                'transaction_id' => (string) $tx['transaction_id'],
                'account_number' => $tx['account_number'] ?? ($tx['tenant_account_number'] ?? null),
                'phone' => $tx['phone'] ?? ($tx['payer_phone'] ?? null),
                'reference' => $tx['reference'] ?? null,
                'payment_method' => $paymentMethod,
                'status' => 'matched',
                'transaction_date' => $tx['transaction_date'] ?? now(),
                'raw_payload' => $tx['raw_payload'] ?? null,
            ];
            if (Schema::hasColumn('payments', 'tenant_account_number')) {
                $paymentAttrs['tenant_account_number'] = $tx['tenant_account_number']
                    ?? $tx['account_number']
                    ?? null;
            }
            if (Schema::hasColumn('payments', 'currency')) {
                $paymentAttrs['currency'] = (string) ($tx['currency'] ?? 'KES');
            }
            if (Schema::hasColumn('payments', 'payment_provider')) {
                $paymentAttrs['payment_provider'] = (string) ($tx['payment_provider'] ?? $provider);
            }
            if (Schema::hasColumn('payments', 'payment_channel')) {
                $paymentAttrs['payment_channel'] = (string) ($tx['payment_channel'] ?? $channel);
            }
            if (Schema::hasColumn('payments', 'external_transaction_reference')) {
                $paymentAttrs['external_transaction_reference'] = (string) (
                    $tx['external_transaction_reference'] ?? $tx['transaction_id'] ?? ''
                );
            }
            if (Schema::hasColumn('payments', 'provider_reference')) {
                $paymentAttrs['provider_reference'] = $tx['provider_reference'] ?? null;
            }
            if (Schema::hasColumn('payments', 'payer_name')) {
                $paymentAttrs['payer_name'] = $tx['payer_name'] ?? null;
            }
            if (Schema::hasColumn('payments', 'payer_phone')) {
                $paymentAttrs['payer_phone'] = $tx['payer_phone'] ?? ($tx['phone'] ?? null);
            }
            if (Schema::hasColumn('payments', 'received_at')) {
                $paymentAttrs['received_at'] = $tx['received_at'] ?? ($tx['transaction_date'] ?? now());
            }
            if (Schema::hasColumn('payments', 'invoice_id')) {
                $paymentAttrs['invoice_id'] = $tx['invoice_id'] ?? null;
            }
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                $paymentAttrs['reconciliation_status'] = 'matched';
            }
            if (Schema::hasColumn('payments', 'reconciliation_notes')) {
                $paymentAttrs['reconciliation_notes'] = $tx['reconciliation_notes'] ?? ('Matched by '.$matchedBy);
            }
            if ($agentUserId !== null && Schema::hasColumn('payments', 'agent_user_id')) {
                $paymentAttrs['agent_user_id'] = $agentUserId;
            }
            $payment = Payment::query()->create($paymentAttrs);

            $meta = [
                'source' => $source,
                'provider' => $provider,
                'matched_by' => $matchedBy,
                'account_number' => $tx['account_number'] ?? ($tx['tenant_account_number'] ?? null),
                'tenant_account_number' => $tx['tenant_account_number'] ?? ($tx['account_number'] ?? null),
                'phone' => $tx['phone'] ?? ($tx['payer_phone'] ?? null),
                'reference' => $tx['reference'] ?? null,
                'raw_payload' => $tx['raw_payload'] ?? null,
                'skip_notification' => (bool) ($options['skip_notification'] ?? true),
                'match_mode' => $options['match_mode'] ?? null,
            ];
            if (! empty($tx['invoice_id'])) {
                $meta['invoice_id'] = (int) $tx['invoice_id'];
            }

            $pmPayment = PmPayment::query()->create([
                'pm_tenant_id' => $tenantId,
                'channel' => $channel,
                'amount' => (float) $tx['amount'],
                'external_ref' => (string) ($tx['external_transaction_reference'] ?? $tx['transaction_id']),
                'paid_at' => $tx['transaction_date'] ?? now(),
                'status' => PmPayment::STATUS_PENDING,
                'meta' => $meta,
            ]);

            app(PropertyPaymentSettlementService::class)->complete(
                $pmPayment,
                (string) ($tx['external_transaction_reference'] ?? $tx['transaction_id']),
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
        $hasUnassignedPaymentMethod = Schema::hasColumn('unassigned_payments', 'payment_method');
        $hasUnassignedAgent = Schema::hasColumn('unassigned_payments', 'agent_user_id');
        $hasPaymentsAgent = Schema::hasColumn('payments', 'agent_user_id');
        $agentUserId = $this->resolveAgentUserId($options, null);

        return DB::transaction(function () use (
            $tx,
            $reason,
            $options,
            $hasUnassignedPaymentMethod,
            $hasUnassignedAgent,
            $hasPaymentsAgent,
            $agentUserId
        ) {
            $paymentAttrs = [
                'tenant_id' => null,
                'amount' => (float) $tx['amount'],
                'transaction_id' => (string) $tx['transaction_id'],
                'account_number' => $tx['account_number'] ?? ($tx['tenant_account_number'] ?? null),
                'phone' => $tx['phone'] ?? ($tx['payer_phone'] ?? null),
                'reference' => $tx['reference'] ?? null,
                'payment_method' => (string) ($options['payment_method'] ?? 'equity'),
                'status' => 'unmatched',
                'transaction_date' => $tx['transaction_date'] ?? now(),
                'raw_payload' => $tx['raw_payload'] ?? null,
            ];
            if (Schema::hasColumn('payments', 'tenant_account_number')) {
                $paymentAttrs['tenant_account_number'] = $tx['tenant_account_number']
                    ?? $tx['account_number']
                    ?? null;
            }
            if (Schema::hasColumn('payments', 'currency')) {
                $paymentAttrs['currency'] = (string) ($tx['currency'] ?? 'KES');
            }
            if (Schema::hasColumn('payments', 'payment_provider')) {
                $paymentAttrs['payment_provider'] = (string) ($tx['payment_provider'] ?? ($options['provider'] ?? 'equity'));
            }
            if (Schema::hasColumn('payments', 'payment_channel')) {
                $paymentAttrs['payment_channel'] = (string) ($tx['payment_channel'] ?? ($options['channel'] ?? null));
            }
            if (Schema::hasColumn('payments', 'external_transaction_reference')) {
                $paymentAttrs['external_transaction_reference'] = (string) (
                    $tx['external_transaction_reference'] ?? $tx['transaction_id'] ?? ''
                );
            }
            if (Schema::hasColumn('payments', 'provider_reference')) {
                $paymentAttrs['provider_reference'] = $tx['provider_reference'] ?? null;
            }
            if (Schema::hasColumn('payments', 'payer_name')) {
                $paymentAttrs['payer_name'] = $tx['payer_name'] ?? null;
            }
            if (Schema::hasColumn('payments', 'payer_phone')) {
                $paymentAttrs['payer_phone'] = $tx['payer_phone'] ?? ($tx['phone'] ?? null);
            }
            if (Schema::hasColumn('payments', 'received_at')) {
                $paymentAttrs['received_at'] = $tx['received_at'] ?? ($tx['transaction_date'] ?? now());
            }
            if (Schema::hasColumn('payments', 'invoice_id')) {
                $paymentAttrs['invoice_id'] = $tx['invoice_id'] ?? null;
            }
            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                $paymentAttrs['reconciliation_status'] = 'unmatched';
            }
            if (Schema::hasColumn('payments', 'reconciliation_notes')) {
                $paymentAttrs['reconciliation_notes'] = $tx['reconciliation_notes'] ?? $reason;
            }
            if ($hasPaymentsAgent && $agentUserId !== null) {
                $paymentAttrs['agent_user_id'] = $agentUserId;
            }
            $payment = Payment::query()->create($paymentAttrs);

            $unassignedValues = [
                'amount' => (float) $tx['amount'],
                'account_number' => $tx['account_number'] ?? null,
                'phone' => $tx['phone'] ?? null,
                'reason' => $reason,
                'created_at' => $tx['transaction_date'] ?? now(),
            ];
            if ($hasUnassignedPaymentMethod) {
                $unassignedValues['payment_method'] = (string) ($options['payment_method'] ?? 'equity');
            }
            if ($hasUnassignedAgent && $agentUserId !== null) {
                $unassignedValues['agent_user_id'] = $agentUserId;
            }

            UnassignedPayment::query()->updateOrCreate(
                ['transaction_id' => (string) $tx['transaction_id']],
                $unassignedValues
            );

            return $payment;
        });
    }

    /**
     * Returns the best agent attribution for a payment.
     *
     * Priority:
     *   1) Explicit agent_user_id passed in by the caller (forwarder token,
     *      manual assignment, etc.).
     *   2) Inferred from `pm_tenants.agent_user_id` when we know the tenant.
     *
     * Returns null when neither applies — that row will then only be visible
     * to super admins.
     */
    private function resolveAgentUserId(array $options, ?int $tenantId): ?int
    {
        $explicit = isset($options['agent_user_id']) ? (int) $options['agent_user_id'] : 0;
        if ($explicit > 0) {
            return $explicit;
        }
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }
        if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return null;
        }

        $value = DB::table('pm_tenants')->where('id', $tenantId)->value('agent_user_id');

        return $value ? (int) $value : null;
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

