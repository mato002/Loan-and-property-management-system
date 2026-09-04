<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmLandlordPayout;
use App\Models\PmPayment;
use App\Models\PmTenantDeposit;
use Carbon\Carbon;
use RuntimeException;

class PropertyTrustAccountingService
{
    private const ACC_CASH_BANK = '1100';
    private const ACC_AR = '1200';
    private const ACC_LANDLORD_CLEARING = '1300';
    private const ACC_LANDLORD_PAYABLE = '2100';
    private const ACC_TENANT_DEPOSIT_LIABILITY = '2200';
    private const ACC_MANAGEMENT_FEE_INCOME = '4200';

    public static function periodManagementFeeSourceKey(int $propertyId, int $landlordId, string $periodMonth): string
    {
        return 'landlord_fee:'.$propertyId.':'.$landlordId.':'.$periodMonth;
    }

    public function periodManagementFeePosted(int $propertyId, int $landlordId, string $periodMonth): bool
    {
        return AccountingJournalBatch::query()
            ->where('source_type', 'landlord_settlement')
            ->where('event_type', 'period_management_fee')
            ->where('source_key', self::periodManagementFeeSourceKey($propertyId, $landlordId, $periodMonth))
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
    }

    public function postPeriodManagementFee(
        int $propertyId,
        int $landlordId,
        string $periodMonth,
        float $feeAmount,
        int $agentUserId,
        ?int $actorId = null,
        ?string $propertyName = null,
    ): void {
        if ($feeAmount <= 0) {
            throw new RuntimeException('Management fee amount must be greater than zero.');
        }

        $sourceKey = self::periodManagementFeeSourceKey($propertyId, $landlordId, $periodMonth);
        if ($this->periodManagementFeePosted($propertyId, $landlordId, $periodMonth)) {
            throw new RuntimeException('Management fees already posted for this period.');
        }

        $journal = app(PropertyJournalService::class);
        $reference = 'LFEE-'.$propertyId.'-'.$periodMonth;
        $label = $propertyName !== null && $propertyName !== '' ? $propertyName : 'Property '.$propertyId;

        $journal->postBatch([
            'date' => Carbon::createFromFormat('Y-m', $periodMonth)?->endOfMonth()->toDateString() ?? now()->toDateString(),
            'description' => 'Period management fee — '.$label.' ('.$periodMonth.')',
            'source_type' => 'landlord_settlement',
            'source_id' => $propertyId,
            'event_type' => 'period_management_fee',
            'source_key' => $sourceKey,
            'agent_user_id' => $agentUserId,
            'created_by' => $actorId,
            'posted_by' => $actorId,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_CLEARING),
                'debit' => $feeAmount,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_MANAGEMENT_FEE_INCOME),
                'debit' => 0,
                'credit' => $feeAmount,
                'reference' => $reference,
                'property_id' => $propertyId,
                'agent_user_id' => $agentUserId,
            ],
        ]);
    }

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

        app(LandlordDepositRefundSettlementService::class)->postDeductionForDepositRefund($deposit, $actorId);
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

