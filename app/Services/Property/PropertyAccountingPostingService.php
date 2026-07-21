<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
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
    private const ACC_UTILITY_AR = '1210';
    private const ACC_SUSPENSE = '1250';
    private const ACC_LANDLORD_CLEARING = '1300';
    private const ACC_LANDLORD_PAYABLE = '2100';
    private const ACC_TENANT_CREDIT_LIABILITY = '2260';
    private const ACC_ACCOUNTS_PAYABLE = '2300';
    private const ACC_RENTAL_INCOME = '4100';
    private const ACC_MANAGEMENT_FEE_INCOME = '4200';
    private const ACC_UTILITY_RECOVERY_INCOME = '4300';
    private const ACC_WATER_REVENUE = '4310';
    private const ACC_PENALTY_INCOME = '4400';
    private const ACC_UTILITY_PENALTY_INCOME = '4410';
    private const ACC_OPENING_BALANCE_EQUITY = '3900';
    private const ACC_MAINTENANCE_EXPENSE = '5101';

    /**
     * Post an invoice-issued journal batch. Idempotent: if a posted batch
     * already exists for this invoice (event_type = 'invoice_issued') we do
     * nothing. Cancellations/edits must call reverseInvoiceIssued() first.
     *
     * Cancelled invoices never post a journal.
     */
    public static function postInvoiceIssued(PmInvoice $invoice, ?User $actor = null): void
    {
        if ((float) $invoice->amount <= 0) {
            return;
        }
        if ((string) $invoice->status === PmInvoice::STATUS_CANCELLED) {
            return;
        }
        // Skip credit notes here; they have their own posting path.
        if ((string) ($invoice->invoice_kind ?? PmInvoice::KIND_INVOICE) === PmInvoice::KIND_CREDIT_NOTE) {
            return;
        }

        // Idempotency guard: if we already have a posted (or even reversed)
        // batch for this invoice's "issued" event, don't try to post again
        // through this method. Caller should call reverseInvoiceIssued()
        // first if they want to re-post under a different revision.
        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->first();
        if ($existing && $existing->status === AccountingJournalBatch::STATUS_POSTED) {
            return;
        }

        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $agentUserId = (int) ($invoice->agent_user_id
            ?? optional(optional($invoice->unit)->property)->agent_user_id
            ?? 0) ?: null;
        $date = $invoice->issue_date?->toDateString() ?? now()->toDateString();

        $journal = app(PropertyJournalService::class);
        $receivableCode = self::receivableAccountCodeForInvoice($invoice);
        $incomeCode = self::incomeAccountCodeForInvoice($invoice);

        $journal->postBatch([
            'date' => $date,
            'description' => 'Invoice '.$invoice->invoice_no.' issued',
            'source_type' => 'pm_invoice',
            'source_id' => (int) $invoice->id,
            'event_type' => 'invoice_issued',
            'source_key' => 'pm_invoice:'.$invoice->id.':issued',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode($receivableCode),
                'debit' => (float) $invoice->amount,
                'credit' => 0,
                'reference' => $invoice->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode($incomeCode),
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
            'account_name' => self::accountName(
                $receivableCode === self::ACC_UTILITY_AR ? 'utility_accounts_receivable' : 'accounts_receivable',
                $receivableCode === self::ACC_UTILITY_AR ? 'Utility Accounts Receivable' : 'Accounts Receivable'
            ),
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
            'account_name' => self::accountName(
                self::incomeAccountMapKey($invoice),
                self::incomeAccountLabel($invoice)
            ),
            'category' => PmAccountingEntry::CATEGORY_INCOME,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => (float) $invoice->amount,
            'reference' => $invoice->invoice_no,
            'description' => 'Invoice issued',
            'source_key' => 'invoice_issued',
        ]);
    }

    /**
     * Post incremental water penalty to utility AR / utility penalty income.
     */
    public static function postWaterPenalty(PmInvoice $invoice, float $penaltyAmount, int $applicationId, ?User $actor = null): void
    {
        if ($penaltyAmount <= 0 || (string) $invoice->invoice_type !== PmInvoice::TYPE_WATER) {
            return;
        }

        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice_penalty_application')
            ->where('source_id', $applicationId)
            ->where('event_type', 'water_penalty_applied')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return;
        }

        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $agentUserId = (int) ($invoice->agent_user_id
            ?? optional(optional($invoice->unit)->property)->agent_user_id
            ?? 0) ?: null;
        $date = now()->toDateString();
        $reference = $invoice->invoice_no.'-PEN-'.$applicationId;

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => $date,
            'description' => 'Water penalty on '.$invoice->invoice_no,
            'source_type' => 'pm_invoice_penalty_application',
            'source_id' => $applicationId,
            'event_type' => 'water_penalty_applied',
            'source_key' => 'water_penalty:'.$applicationId,
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_UTILITY_AR),
                'debit' => $penaltyAmount,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::resolveAccountCode(self::ACC_UTILITY_PENALTY_INCOME, self::ACC_PENALTY_INCOME)),
                'debit' => 0,
                'credit' => $penaltyAmount,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);
    }

    public static function reverseWaterPenalty(PmInvoice $invoice, float $penaltyAmount, int $applicationId, ?User $actor = null, ?string $reason = null): void
    {
        if ($penaltyAmount <= 0) {
            return;
        }

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice_penalty_application')
            ->where('source_id', $applicationId)
            ->where('event_type', 'water_penalty_applied')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->first();

        if ($batch) {
            app(PropertyJournalService::class)->reverseBatch($batch, $actor?->id, $reason ?: 'Water penalty reversed');
        }
    }

    public static function receivableAccountCodeForInvoice(PmInvoice $invoice): string
    {
        if (self::isCarryForwardInvoice($invoice)) {
            return (string) ($invoice->invoice_type ?? PmInvoice::TYPE_RENT) === PmInvoice::TYPE_WATER
                ? self::resolveAccountCode(self::ACC_UTILITY_AR, self::ACC_AR)
                : self::ACC_AR;
        }

        $type = (string) ($invoice->invoice_type ?? PmInvoice::TYPE_RENT);

        return match ($type) {
            PmInvoice::TYPE_WATER => self::resolveAccountCode(self::ACC_UTILITY_AR, self::ACC_AR),
            PmInvoice::TYPE_MIXED => self::resolveAccountCode(self::ACC_UTILITY_AR, self::ACC_AR),
            default => self::ACC_AR,
        };
    }

    public static function incomeAccountCodeForInvoice(PmInvoice $invoice): string
    {
        if (self::isCarryForwardInvoice($invoice)) {
            return self::resolveAccountCode(
                self::ACC_OPENING_BALANCE_EQUITY,
                self::ACC_RENTAL_INCOME
            );
        }

        $type = (string) ($invoice->invoice_type ?? PmInvoice::TYPE_RENT);

        return match ($type) {
            PmInvoice::TYPE_WATER => self::resolveAccountCode(self::ACC_WATER_REVENUE, self::ACC_UTILITY_RECOVERY_INCOME),
            PmInvoice::TYPE_MIXED => self::resolveAccountCode(self::ACC_UTILITY_RECOVERY_INCOME, self::ACC_RENTAL_INCOME),
            default => self::ACC_RENTAL_INCOME,
        };
    }

    private static function incomeAccountMapKey(PmInvoice $invoice): string
    {
        if (self::isCarryForwardInvoice($invoice)) {
            return 'opening_balance_equity';
        }

        return match ((string) ($invoice->invoice_type ?? PmInvoice::TYPE_RENT)) {
            PmInvoice::TYPE_WATER => 'water_revenue',
            PmInvoice::TYPE_MIXED => 'utility_recovery_income',
            default => 'rental_income',
        };
    }

    private static function incomeAccountLabel(PmInvoice $invoice): string
    {
        if (self::isCarryForwardInvoice($invoice)) {
            return 'Opening Balance Equity';
        }

        return match ((string) ($invoice->invoice_type ?? PmInvoice::TYPE_RENT)) {
            PmInvoice::TYPE_WATER => 'Water Revenue',
            PmInvoice::TYPE_MIXED => 'Utility Recovery Income',
            default => 'Rental Income',
        };
    }

    /**
     * Prefer primary code; fall back if chart account missing (legacy installs).
     */
    private static function resolveAccountCode(string $primary, string $fallback): string
    {
        if (AccountingChartAccount::query()->where('code', $primary)->exists()) {
            return $primary;
        }

        return $fallback;
    }

    public static function isCarryForwardInvoice(PmInvoice $invoice): bool
    {
        return str_starts_with((string) $invoice->description, FinanceFirebreakService::CARRY_FORWARD_PREFIX);
    }

    /**
     * Reverse the invoice-issued journal batch (e.g. on cancellation,
     * deletion, or before re-posting after an amount change). Idempotent:
     * if no posted batch exists, or it's already reversed, this is a no-op.
     */
    public static function reverseInvoiceIssued(PmInvoice $invoice, ?User $actor = null, ?string $reason = null): void
    {
        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->first();

        if (! $batch) {
            return;
        }
        if ($batch->status !== AccountingJournalBatch::STATUS_POSTED) {
            return;
        }

        $journal = app(PropertyJournalService::class);
        $journal->reverseBatch($batch, $actor?->id, $reason ?: 'Invoice '.$invoice->invoice_no.' reversed');

        // Mirror reversal entries on the legacy informational ledger.
        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $date = now()->toDateString();
        $ref = $invoice->invoice_no.' (reversed)';

        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName(
                self::receivableAccountCodeForInvoice($invoice) === self::ACC_UTILITY_AR ? 'utility_accounts_receivable' : 'accounts_receivable',
                self::receivableAccountCodeForInvoice($invoice) === self::ACC_UTILITY_AR ? 'Utility Accounts Receivable' : 'Accounts Receivable'
            ),
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => (float) $invoice->amount,
            'reference' => $ref,
            'description' => 'Invoice reversed',
            'source_key' => 'invoice_reversed',
        ]);
        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName(self::incomeAccountMapKey($invoice), self::incomeAccountLabel($invoice)),
            'category' => PmAccountingEntry::CATEGORY_INCOME,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => (float) $invoice->amount,
            'reference' => $ref,
            'description' => 'Invoice reversed',
            'source_key' => 'invoice_reversed',
        ]);
    }

    /**
     * Reverse every posted forward invoice issuance batch (invoice_issued and
     * invoice_issued_rev_*). Used on cancellation/deletion so AR and revenue
     * are fully unwound.
     */
    public static function reverseAllInvoiceIssuanceBatches(PmInvoice $invoice, ?User $actor = null, ?string $reason = null): void
    {
        $batches = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $invoice->id)
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->orderBy('id')
            ->get()
            ->filter(function (AccountingJournalBatch $batch) {
                $type = (string) $batch->event_type;

                return $type === 'invoice_issued'
                    || str_starts_with($type, 'invoice_issued_rev_');
            });

        if ($batches->isEmpty()) {
            return;
        }

        $journal = app(PropertyJournalService::class);
        foreach ($batches as $batch) {
            $journal->reverseBatch($batch, $actor?->id, $reason ?: 'Invoice '.$invoice->invoice_no.' reversed');
        }

        self::mirrorInvoiceIssuanceReversalEntries($invoice, $actor);
    }

    /**
     * Post a credit memo journal for a credit note (DR revenue / CR AR).
     * Idempotent on credit_memo_issued batches.
     */
    public static function postCreditMemoIssued(PmInvoice $creditNote, ?User $actor = null): void
    {
        if (! $creditNote->isCreditNote()) {
            return;
        }

        $amount = round(abs((float) $creditNote->amount), 2);
        if ($amount <= 0) {
            return;
        }

        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $creditNote->id)
            ->where('event_type', 'credit_memo_issued')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return;
        }

        $creditNote->loadMissing('unit.property', 'originalInvoice');
        $accountSource = $creditNote->originalInvoice ?? $creditNote;
        $propertyId = optional($creditNote->unit)->property_id;
        $agentUserId = (int) ($creditNote->agent_user_id
            ?? optional(optional($creditNote->unit)->property)->agent_user_id
            ?? 0) ?: null;
        $date = $creditNote->issue_date?->toDateString() ?? now()->toDateString();
        $receivableCode = self::receivableAccountCodeForInvoice($accountSource);
        $incomeCode = self::incomeAccountCodeForInvoice($accountSource);

        $journal = app(PropertyJournalService::class);
        $lines = [
            [
                'account_id' => $journal->accountIdByCode($incomeCode),
                'debit' => $amount,
                'credit' => 0,
                'reference' => $creditNote->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $creditNote->pm_tenant_id,
                'unit_id' => $creditNote->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode($receivableCode),
                'debit' => 0,
                'credit' => $amount,
                'reference' => $creditNote->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $creditNote->pm_tenant_id,
                'unit_id' => $creditNote->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ];

        $landlordNet = self::creditMemoLandlordNetAmount($creditNote, $accountSource, $amount);
        if ($landlordNet > 0) {
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_PAYABLE),
                'debit' => $landlordNet,
                'credit' => 0,
                'reference' => $creditNote->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $creditNote->pm_tenant_id,
                'unit_id' => $creditNote->property_unit_id,
                'agent_user_id' => $agentUserId,
            ];
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_CLEARING),
                'debit' => 0,
                'credit' => $landlordNet,
                'reference' => $creditNote->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $creditNote->pm_tenant_id,
                'unit_id' => $creditNote->property_unit_id,
                'agent_user_id' => $agentUserId,
            ];
        }

        $journal->postBatch([
            'date' => $date,
            'description' => 'Credit memo '.$creditNote->invoice_no,
            'source_type' => 'pm_invoice',
            'source_id' => (int) $creditNote->id,
            'event_type' => 'credit_memo_issued',
            'source_key' => 'pm_invoice:'.$creditNote->id.':credit_memo',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], $lines);
    }

    /**
     * Reverse a posted credit memo batch (e.g. credit note voided).
     */
    public static function reverseCreditMemoIssued(PmInvoice $creditNote, ?User $actor = null, ?string $reason = null): void
    {
        if (! $creditNote->isCreditNote()) {
            return;
        }

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $creditNote->id)
            ->where('event_type', 'credit_memo_issued')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->first();

        if (! $batch) {
            return;
        }

        app(PropertyJournalService::class)->reverseBatch(
            $batch,
            $actor?->id,
            $reason ?: 'Credit memo '.$creditNote->invoice_no.' reversed'
        );
    }

    public static function reverseTenantCreditApplied(int $creditTransactionId, ?User $actor = null, ?string $reason = null): void
    {
        if ($creditTransactionId <= 0) {
            return;
        }

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_tenant_credit_transaction')
            ->where('source_id', $creditTransactionId)
            ->where('event_type', 'tenant_credit_applied')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->first();

        if ($batch) {
            app(PropertyJournalService::class)->reverseBatch(
                $batch,
                $actor?->id,
                $reason ?: 'Tenant credit application reversed'
            );
        }
    }

    private static function mirrorInvoiceIssuanceReversalEntries(PmInvoice $invoice, ?User $actor = null): void
    {
        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $date = now()->toDateString();
        $ref = $invoice->invoice_no.' (reversed)';

        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName(
                self::receivableAccountCodeForInvoice($invoice) === self::ACC_UTILITY_AR ? 'utility_accounts_receivable' : 'accounts_receivable',
                self::receivableAccountCodeForInvoice($invoice) === self::ACC_UTILITY_AR ? 'Utility Accounts Receivable' : 'Accounts Receivable'
            ),
            'category' => PmAccountingEntry::CATEGORY_ASSET,
            'entry_type' => PmAccountingEntry::TYPE_CREDIT,
            'amount' => (float) $invoice->amount,
            'reference' => $ref,
            'description' => 'Invoice reversed',
            'source_key' => 'invoice_reversed',
        ]);
        self::firstOrCreateEntry([
            'entry_date' => $date,
            'property_id' => $propertyId,
            'recorded_by_user_id' => $actor?->id,
            'account_name' => self::accountName(self::incomeAccountMapKey($invoice), self::incomeAccountLabel($invoice)),
            'category' => PmAccountingEntry::CATEGORY_INCOME,
            'entry_type' => PmAccountingEntry::TYPE_DEBIT,
            'amount' => (float) $invoice->amount,
            'reference' => $ref,
            'description' => 'Invoice reversed',
            'source_key' => 'invoice_reversed',
        ]);
    }

    private static function creditMemoLandlordNetAmount(PmInvoice $creditNote, PmInvoice $accountSource, float $creditAmount): float
    {
        if ($creditAmount <= 0 || (float) $accountSource->amount <= 0) {
            return 0.0;
        }

        $creditNote->loadMissing('originalInvoice.unit.property');
        $propertyId = (int) optional(optional($accountSource->unit)->property)->id;
        if ($propertyId <= 0) {
            return 0.0;
        }

        $hasOwners = \Illuminate\Support\Facades\DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->where('ownership_percent', '>', 0)
            ->exists();
        if (! $hasOwners) {
            return 0.0;
        }

        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $commissionPct = is_numeric($defaultRaw) ? (float) $defaultRaw : 10.0;
        $propertyPct = PropertyPortalSetting::getValue('commission_percent_property_'.$propertyId);
        if ($propertyPct !== null && is_numeric($propertyPct)) {
            $commissionPct = (float) $propertyPct;
        }

        $proportion = min(1.0, $creditAmount / abs((float) $accountSource->amount));

        return round(max(0.0, $creditAmount * $proportion * (1 - ($commissionPct / 100))), 2);
    }

    /**
     * Re-post after a material change to an invoice (typically: amount).
     * Reverses the existing batch and posts a new one with a fresh
     * event_type that includes the latest amount, so the audit trail keeps
     * the old + new versions visible side by side.
     */
    public static function repostInvoiceAfterEdit(PmInvoice $invoice, ?User $actor = null): void
    {
        self::reverseInvoiceIssued($invoice, $actor, 'Invoice '.$invoice->invoice_no.' edited');

        // Issue under a revised event_type so the firstOrCreate on the
        // journal batch table doesn't collide with the now-reversed
        // original. The original "invoice_issued" row stays in the audit
        // trail; reports should sum over all non-reversed batches anyway.
        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $agentUserId = (int) ($invoice->agent_user_id
            ?? optional(optional($invoice->unit)->property)->agent_user_id
            ?? 0) ?: null;
        $date = $invoice->issue_date?->toDateString() ?? now()->toDateString();
        // Count *forward* postings only — exclude reversal batches so the
        // version number tracks the number of times we've issued the invoice.
        // NOTE: SQL LIKE treats `_` as a single-char wildcard, so we can't
        // use `invoice_issued_rev_%` (that would also match
        // `invoice_issued_reversal`). Filter in PHP instead.
        $eventTypes = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', (int) $invoice->id)
            ->pluck('event_type')
            ->all();
        $forwardCount = 0;
        foreach ($eventTypes as $type) {
            if ($type === 'invoice_issued'
                || (is_string($type) && str_starts_with($type, 'invoice_issued_rev_'))) {
                $forwardCount++;
            }
        }
        $eventVersion = $forwardCount + 1;

        $journal = app(PropertyJournalService::class);
        $receivableCode = self::receivableAccountCodeForInvoice($invoice);
        $incomeCode = self::incomeAccountCodeForInvoice($invoice);
        $journal->postBatch([
            'date' => $date,
            'description' => 'Invoice '.$invoice->invoice_no.' re-issued (rev '.$eventVersion.')',
            'source_type' => 'pm_invoice',
            'source_id' => (int) $invoice->id,
            'event_type' => 'invoice_issued_rev_'.$eventVersion,
            'source_key' => 'pm_invoice:'.$invoice->id.':issued_rev_'.$eventVersion,
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode($receivableCode),
                'debit' => (float) $invoice->amount,
                'credit' => 0,
                'reference' => $invoice->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode($incomeCode),
                'debit' => 0,
                'credit' => (float) $invoice->amount,
                'reference' => $invoice->invoice_no,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);
    }

    public static function postPaymentReceived(PmPayment $payment, ?User $actor = null): void
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED || (float) $payment->amount <= 0) {
            return;
        }

        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', 'payment_received')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return;
        }

        if (AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', 'payment_unmatched_suspense')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists()) {
            return;
        }

        $payment->loadMissing('allocations.invoice.unit.property');
        $firstInvoice = optional($payment->allocations->first())->invoice;
        $propertyId = optional($firstInvoice?->unit)->property_id;
        $agentUserId = optional(optional($firstInvoice?->unit)->property)->agent_user_id;
        $reference = $payment->external_ref ?: ('PAY-'.$payment->id);
        $entryDate = $payment->paid_at?->toDateString() ?? now()->toDateString();

        $allocatedTotal = round((float) $payment->allocations
            ->filter(fn ($allocation) => ! $allocation->is_reversed)
            ->sum('amount'), 2);
        $gross = round((float) $payment->amount, 2);
        $creditToLiability = round(max(0.0, $gross - $allocatedTotal), 2);
        $arSettled = round(min($allocatedTotal, $gross), 2);

        $commissionPct = self::defaultCommissionPercentForProperty($propertyId);
        $commission = round($arSettled * ($commissionPct / 100), 2);
        $landlordNet = max(0.0, round($arSettled - $commission, 2));

        $journal = app(PropertyJournalService::class);
        $lines = [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_CASH_BANK),
                'debit' => $gross,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ],
        ];

        if ($arSettled > 0) {
            $arByAccount = [];
            foreach ($payment->allocations as $allocation) {
                if ($allocation->is_reversed) {
                    continue;
                }
                $invoice = $allocation->invoice;
                if (! $invoice) {
                    continue;
                }
                $code = self::receivableAccountCodeForInvoice($invoice);
                $arByAccount[$code] = ($arByAccount[$code] ?? 0.0) + (float) $allocation->amount;
            }
            if ($arByAccount === []) {
                $arByAccount[self::ACC_AR] = $arSettled;
            }
            foreach ($arByAccount as $accountCode => $amount) {
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = [
                    'account_id' => $journal->accountIdByCode($accountCode),
                    'debit' => 0,
                    'credit' => round($amount, 2),
                    'reference' => $reference,
                    'property_id' => $propertyId,
                    'tenant_id' => $payment->pm_tenant_id,
                    'agent_user_id' => $agentUserId,
                ];
            }
        }

        if ($creditToLiability > 0) {
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_TENANT_CREDIT_LIABILITY),
                'debit' => 0,
                'credit' => $creditToLiability,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ];
        }

        if ($arSettled > 0) {
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_CLEARING),
                'debit' => $arSettled,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ];
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_LANDLORD_PAYABLE),
                'debit' => 0,
                'credit' => $landlordNet,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ];
            $lines[] = [
                'account_id' => $journal->accountIdByCode(self::ACC_MANAGEMENT_FEE_INCOME),
                'debit' => 0,
                'credit' => $commission,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $payment->pm_tenant_id,
                'agent_user_id' => $agentUserId,
            ];
        }

        $journal->postBatch([
            'date' => $entryDate,
            'description' => $creditToLiability > 0
                ? 'Tenant payment received (invoices + advance credit)'
                : 'Tenant payment received and settled',
            'source_type' => 'pm_payment',
            'source_id' => (int) $payment->id,
            'event_type' => 'payment_received',
            'source_key' => 'pm_payment:'.$payment->id.':received',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], $lines);

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
            'amount' => $arSettled > 0 ? $arSettled : (float) $payment->amount,
            'reference' => $reference,
            'description' => 'Tenant payment allocation',
            'source_key' => 'payment_received',
        ]);
    }

    /**
     * Apply tenant advance credit to an open invoice.
     * DR Tenant Credit Liability / CR Accounts Receivable
     */
    public static function postTenantCreditApplied(PmInvoice $invoice, float $amount, int $creditTransactionId, ?User $actor = null): void
    {
        if ($amount <= 0) {
            return;
        }

        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_tenant_credit_transaction')
            ->where('source_id', $creditTransactionId)
            ->where('event_type', 'tenant_credit_applied')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return;
        }

        $invoice->loadMissing('unit.property');
        $propertyId = optional($invoice->unit)->property_id;
        $agentUserId = (int) ($invoice->agent_user_id
            ?? optional(optional($invoice->unit)->property)->agent_user_id
            ?? 0) ?: null;
        $date = now()->toDateString();
        $reference = $invoice->invoice_no.'-CREDIT-'.$creditTransactionId;

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => $date,
            'description' => 'Tenant advance credit applied to '.$invoice->invoice_no,
            'source_type' => 'pm_tenant_credit_transaction',
            'source_id' => $creditTransactionId,
            'event_type' => 'tenant_credit_applied',
            'source_key' => 'pm_tenant_credit:'.$creditTransactionId.':applied',
            'agent_user_id' => $agentUserId,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_TENANT_CREDIT_LIABILITY),
                'debit' => $amount,
                'credit' => 0,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_AR),
                'debit' => 0,
                'credit' => $amount,
                'reference' => $reference,
                'property_id' => $propertyId,
                'tenant_id' => $invoice->pm_tenant_id,
                'unit_id' => $invoice->property_unit_id,
                'agent_user_id' => $agentUserId,
            ],
        ]);
    }

    /**
     * Refund unused tenant credit to cash.
     * DR Tenant Credit Liability / CR Cash/Bank
     */
    public static function postTenantCreditRefund(int $tenantId, float $amount, ?string $reference, ?User $actor = null): void
    {
        if ($amount <= 0 || $tenantId <= 0) {
            return;
        }

        $ref = $reference ?: ('CREDIT-REFUND-'.$tenantId.'-'.now()->format('YmdHis'));
        $sourceKey = 'pm_tenant_credit_refund:'.$tenantId.':'.$ref;

        $existing = AccountingJournalBatch::query()
            ->where('source_key', $sourceKey)
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return;
        }

        $journal = app(PropertyJournalService::class);
        $journal->postBatch([
            'date' => now()->toDateString(),
            'description' => 'Tenant advance credit refunded',
            'source_type' => 'pm_tenant',
            'source_id' => $tenantId,
            'event_type' => 'tenant_credit_refunded',
            'source_key' => $sourceKey,
            'agent_user_id' => null,
            'created_by' => $actor?->id,
            'posted_by' => $actor?->id,
        ], [
            [
                'account_id' => $journal->accountIdByCode(self::ACC_TENANT_CREDIT_LIABILITY),
                'debit' => $amount,
                'credit' => 0,
                'reference' => $ref,
                'tenant_id' => $tenantId,
            ],
            [
                'account_id' => $journal->accountIdByCode(self::ACC_CASH_BANK),
                'debit' => 0,
                'credit' => $amount,
                'reference' => $ref,
                'tenant_id' => $tenantId,
            ],
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

        $existing = AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', 'payment_unmatched_suspense')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
        if ($existing) {
            return null;
        }

        if (AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', 'payment_received')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists()) {
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
            'utility_accounts_receivable' => 'Utility Accounts Receivable',
            'rental_income' => 'Rental Income',
            'water_revenue' => 'Water Revenue',
            'utility_recovery_income' => 'Utility Recovery Income',
            'utility_penalty_income' => 'Utility Penalty Income',
            'cash_bank' => 'Cash / Bank',
            'maintenance_expense' => 'Maintenance Expense',
            'accounts_payable' => 'Accounts Payable',
            'opening_balance_equity' => 'Opening Balance Equity',
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

