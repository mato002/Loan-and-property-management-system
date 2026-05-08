<?php

namespace App\Services\Property;

use App\Models\PmLandlordPayout;
use App\Models\PmPayment;
use App\Models\PmTenantDeposit;
use RuntimeException;

class PropertyTrustAccountingService
{
    private const ACC_CASH_BANK = '1100';
    private const ACC_AR = '1200';
    private const ACC_LANDLORD_PAYABLE = '2100';
    private const ACC_TENANT_DEPOSIT_LIABILITY = '2200';

    public function postLandlordPayout(PmLandlordPayout $payout, ?int $actorId = null): void
    {
        if ((float) $payout->total_amount <= 0) {
            throw new RuntimeException('Payout amount must be positive.');
        }

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => optional($payout->paid_at)->toDateString() ?? now()->toDateString(),
            'description' => 'Landlord payout paid',
            'source_type' => 'pm_landlord_payout',
            'source_id' => (int) $payout->id,
            'event_type' => 'landlord_payout_paid',
            'source_key' => 'pm_landlord_payout:'.$payout->id.':paid',
            'agent_user_id' => $payout->agent_user_id,
            'created_by' => $actorId ?? $payout->created_by,
            'posted_by' => $actorId ?? $payout->approved_by,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_PAYABLE),
                'debit' => (float) $payout->total_amount,
                'credit' => 0,
                'reference' => 'LPO-'.$payout->id,
                'agent_user_id' => $payout->agent_user_id,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_CASH_BANK),
                'debit' => 0,
                'credit' => (float) $payout->total_amount,
                'reference' => 'LPO-'.$payout->id,
                'agent_user_id' => $payout->agent_user_id,
            ],
        ]);
    }

    public function postTenantDepositReceived(PmTenantDeposit $deposit, ?int $actorId = null): void
    {
        $this->postTenantDeposit($deposit, 'deposit_received', [
            'debit_code' => self::ACC_CASH_BANK,
            'credit_code' => self::ACC_TENANT_DEPOSIT_LIABILITY,
            'description' => 'Tenant deposit received',
            'actor_id' => $actorId,
        ]);
    }

    public function postTenantDepositUsed(PmTenantDeposit $deposit, PmPayment $payment, ?int $actorId = null): void
    {
        $this->postTenantDeposit($deposit, 'deposit_used', [
            'debit_code' => self::ACC_TENANT_DEPOSIT_LIABILITY,
            'credit_code' => self::ACC_AR,
            'description' => 'Tenant deposit used against receivable',
            'actor_id' => $actorId,
            'reference' => 'PAY-'.$payment->id,
        ]);
    }

    public function postTenantDepositRefund(PmTenantDeposit $deposit, ?int $actorId = null): void
    {
        $this->postTenantDeposit($deposit, 'deposit_refund', [
            'debit_code' => self::ACC_TENANT_DEPOSIT_LIABILITY,
            'credit_code' => self::ACC_CASH_BANK,
            'description' => 'Tenant deposit refunded',
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function postTenantDeposit(PmTenantDeposit $deposit, string $eventType, array $config): void
    {
        if ((float) $deposit->amount <= 0) {
            throw new RuntimeException('Deposit amount must be positive.');
        }

        $journal = app(PropertyJournalService::class);
        $reference = (string) ($config['reference'] ?? ('DEP-'.$deposit->id));
        $journal->postBatch([
            'date' => now()->toDateString(),
            'description' => (string) $config['description'],
            'source_type' => 'pm_tenant_deposit',
            'source_id' => (int) $deposit->id,
            'event_type' => $eventType,
            'source_key' => 'pm_tenant_deposit:'.$deposit->id.':'.$eventType,
            'agent_user_id' => $deposit->agent_user_id,
            'created_by' => $config['actor_id'] ?? null,
            'posted_by' => $config['actor_id'] ?? null,
        ], [
            [
                'account_id' => $journal->accountIdByCode((string) $config['debit_code']),
                'debit' => (float) $deposit->amount,
                'credit' => 0,
                'reference' => $reference,
                'tenant_id' => $deposit->tenant_id,
                'agent_user_id' => $deposit->agent_user_id,
            ],
            [
                'account_id' => $journal->accountIdByCode((string) $config['credit_code']),
                'debit' => 0,
                'credit' => (float) $deposit->amount,
                'reference' => $reference,
                'tenant_id' => $deposit->tenant_id,
                'agent_user_id' => $deposit->agent_user_id,
            ],
        ]);
    }
}

