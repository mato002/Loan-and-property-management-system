<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmAccountingEntry;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use App\Models\User;

class PropertyAccountingPostingService
{
    private const ACC_CASH_BANK = '1100';
    private const ACC_AR = '1200';
    private const ACC_SUSPENSE = '1250';
    private const ACC_LANDLORD_CLEARING = '1300';
    private const ACC_LANDLORD_PAYABLE = '2100';
    private const ACC_ACCOUNTS_PAYABLE = '2300';
    private const ACC_RENTAL_INCOME = '4100';
    private const ACC_MANAGEMENT_FEE_INCOME = '4200';
    private const ACC_MAINTENANCE_EXPENSE = '5101';

    public static function postInvoiceIssued(PmInvoice $invoice, ?User $actor = null): void
    {
        if ((float) $invoice->amount <= 0) {
            return;
        }

        $invoice->loadMissing('unit');
        $propertyId = optional($invoice->unit)->property_id;
        $agentUserId = optional(optional($invoice->unit)->property)->agent_user_id;
        $date = $invoice->issue_date?->toDateString() ?? now()->toDateString();

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => $date,
            'description' => 'Invoice issued',
            'source_type' => 'pm_invoice',
            'source_id' => (int) $invoice->id,
            'event_type' => 'invoice_issued',
            'source_key' => 'pm_invoice:'.$invoice->id.':issued',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_AR),
                'debit' => (float) $invoice->amount,
                'credit' => 0,
                'reference' => $invoice->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_RENTAL_INCOME),
                'debit' => 0,
                'credit' => (float) $invoice->amount,
                'reference' => $invoice->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('accounts_receivable', 'Accounts Receivable'),
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => (float) $invoice->amount,
            'reference' => $invoice->invoice_no,
            'description' => 'Invoice issued',
            'source_key' => 'invoice_issued',
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('rental_income', 'Rental Income'),
            'category' => PmAccountingEntry::CATEGORY_INCOME,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => (float) $invoice->amount,
            'reference' => $invoice->invoice_no,
            'description' => 'Invoice issued',
            'source_key' => 'invoice_issued',
        ]);
    }

    public static function postPaymentReceived(PmPayment $payment, ?User $actor = null): void
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED || (float) $payment->amount <= 0) {
            return;
        }

        $payment->loadMissing('allocations.invoice.unit.property');
        $firstInvoice = optional($payment->allocations->first())->invoice;
        $propertyId = optional($firstInvoice?->unit)->property_id;
        $agentUserId = optional(optional($firstInvoice?->unit)->property)->agent_user_id;
        $reference = $payment->external_ref ?: ('PAY-'.$payment->id);
        $entryDate = $payment->paid_at?->toDateString() ?? now()->toDateString();

        $commissionPct = self::defaultCommissionPercentForProperty($propertyId);
        $gross = (float) $payment->amount;
        $commission = round($gross * ($commissionPct / 100), 2);
        $landlordNet = max(0.0, round($gross - $commission, 2));

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => $entryDate,
            'description' => 'Tenant payment received and settled',
            'source_type' => 'pm_payment',
            'source_id' => (int) $payment->id,
            'event_type' => 'payment_received',
            'source_key' => 'pm_payment:'.$payment->id.':received',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_CASH_BANK),
                'debit' => $gross,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_AR),
                'debit' => 0,
                'credit' => $gross,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_CLEARING),
                'debit' => $gross,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_PAYABLE),
                'debit' => 0,
                'credit' => $landlordNet,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_MANAGEMENT_FEE_INCOME),
                'debit' => 0,
                'credit' => $commission,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $entryDate,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('cash_bank', 'Cash / Bank'),
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => (float) $payment->amount,
            'reference' => $reference,
            'description' => 'Tenant payment received',
            'source_key' => 'payment_received',
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $entryDate,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('accounts_receivable', 'Accounts Receivable'),
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => (float) $payment->amount,
            'reference' => $reference,
            'description' => 'Tenant payment allocation',
            'source_key' => 'payment_received',
        ]);
    }

    public static function postMaintenanceExpense(PmMaintenanceJob $job, ?User $actor = null): void
    {
        $amount = (float) ($job->quote_amount ?? 0);
        if ($amount <= 0) {
            return;
        }
        if (! in_array($job->status, ['approved', 'in_progress', 'done'], true)) {
            return;
        }

        $job->loadMissing('request.unit');
        $propertyId = optional($job->request?->unit)->property_id;
        $agentUserId = optional(optional($job->request?->unit)->property)->agent_user_id;
        $reference = 'MNT-'.$job->id;
        $entryDate = $job->completed_at?->toDateString() ?? now()->toDateString();

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => $entryDate,
            'description' => 'Maintenance expense',
            'source_type' => 'pm_maintenance_job',
            'source_id' => (int) $job->id,
            'event_type' => 'maintenance_expense',
            'source_key' => 'pm_maintenance_job:'.$job->id.':expense',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_MAINTENANCE_EXPENSE),
                'debit' => $amount,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'unit_id' => optional($job->request)->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_ACCOUNTS_PAYABLE),
                'debit' => 0,
                'credit' => $amount,
                'reference' => $reference,
                'property_id' => $propertyId,
                'unit_id' => optional($job->request)->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $entryDate,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('maintenance_expense', 'Maintenance Expense'),
            'category' => PmAccountingEntry::CATEGORY_EXPENSE,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => $amount,
            'reference' => $reference,
            'description' => 'Maintenance job '.$job->status,
            'source_key' => 'maintenance_expense',
        ]);

        self::firstOrCreateEntry([
            'entry_date' => $entryDate,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName('accounts_payable', 'Accounts Payable'),
            'category' => PmAccountingEntry::CATEGORY_LIABILITY,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => $amount,
            'reference' => $reference,
            'description' => 'Maintenance liability',
            'source_key' => 'maintenance_expense',
        ]);
    }

    public static function postUnmatchedPaymentToSuspense(PmPayment $payment, ?User $actor = null): ?AccountingJournalBatch
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED || (float) $payment->amount <= 0) {
            return null;
        }

        $entryDate = $payment->paid_at?->toDateString() ?? now()->toDateString();
        $reference = $payment->external_ref ?: ('PAY-'.$payment->id);
        $journal = app(PropertyJournalService::class);

        return $journal->postBatch([
            'date' => $entryDate,
            'description' => 'Unidentified tenant payment routed to suspense',
            'source_type' => 'pm_payment',
            'source_id' => (int) $payment->id,
            'event_type' => 'payment_unmatched_suspense',
            'source_key' => 'pm_payment:'.$payment->id.':suspense',
            'agent_user_id' => null,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_CASH_BANK),
                'debit' => (float) $payment->amount,
                'credit' => 0,
                'reference' => $reference,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_SUSPENSE),
                'debit' => 0,
                'credit' => (float) $payment->amount,
                'reference' => $reference,
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function firstOrCreateEntry(array $data): void
    {
        PmAccountingEntry::query()->firstOrCreate([
            'entry_date' => $data['entry_date'],
            'account_name' => $data['account_name'],
            'entry_type' => $data['entry_type'],
            'amount' => $data['amount'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
        ], $data);
    }

    /**
     * @return array<string,string>
     */
    public static function accountMap(): array
    {
        $default = [
            'accounts_receivable' => 'Accounts Receivable',
            'rental_income' => 'Rental Income',
            'cash_bank' => 'Cash / Bank',
            'maintenance_expense' => 'Maintenance Expense',
            'accounts_payable' => 'Accounts Payable',
        ];

        $raw = PropertyPortalSetting::query()->where('key', 'property_accounting_account_map')->value('value');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($decoded)) {
            return $default;
        }

        return array_merge($default, array_filter($decoded, fn ($v) => is_string($v) && trim($v) !== ''));
    }

    private static function accountName(string $key, string $fallback): string
    {
        $map = self::accountMap();

        return (string) ($map[$key] ?? $fallback);
    }

    private static function defaultCommissionPercentForProperty(?int $propertyId): float
    {
        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $defaultPct = is_numeric($defaultRaw) ? (float) $defaultRaw : 10.0;
        $defaultPct = max(0.0, $defaultPct);
        $overrideRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($overrideRaw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        if ($propertyId && is_numeric($overrides[(string) $propertyId] ?? null)) {
            return max(0.0, (float) $overrides[(string) $propertyId]);
        }

        return $defaultPct;
    }
}

