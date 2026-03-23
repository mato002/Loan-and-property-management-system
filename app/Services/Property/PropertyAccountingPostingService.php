<?php

namespace App\Services\Property;

use App\Models\PmAccountingEntry;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use App\Models\User;

class PropertyAccountingPostingService
{
    public static function postInvoiceIssued(PmInvoice $invoice, ?User $actor = null): void
    {
        if ((float) $invoice->amount <= 0) {
            return;
        }

        self::firstOrCreateEntry([
            'entry_date' => $invoice->issue_date?->toDateString() ?? now()->toDateString(),
            'property_id' => $invoice->property_unit_id ? optional($invoice->unit)->property_id : null,
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
            'entry_date' => $invoice->issue_date?->toDateString() ?? now()->toDateString(),
            'property_id' => $invoice->property_unit_id ? optional($invoice->unit)->property_id : null,
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

        $propertyId = optional(optional($payment->allocations->first())->invoice?->unit)->property_id;
        $reference = $payment->external_ref ?: ('PAY-'.$payment->id);
        $entryDate = $payment->paid_at?->toDateString() ?? now()->toDateString();

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
        $reference = 'MNT-'.$job->id;
        $entryDate = $job->completed_at?->toDateString() ?? now()->toDateString();

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
}

