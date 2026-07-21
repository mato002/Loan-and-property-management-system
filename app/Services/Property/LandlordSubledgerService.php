<?php

namespace App\Services\Property;

use App\Models\AccountingChartAccount;
use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LandlordSubledgerService
{
    private const ACC_LANDLORD_PAYABLE = '2100';

    /** @var int|null */
    private ?int $payableAccountId = null;

    /**
     * @return array{posted: int, skipped: int}
     */
    public function postCreditsForPayment(PmPayment $payment): array
    {
        if ($payment->status !== PmPayment::STATUS_COMPLETED) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $payment->loadMissing('allocations.invoice.unit');
        $activeAllocations = ($payment->allocations ?? collect())
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed);

        if ($activeAllocations->isEmpty()) {
            return ['posted' => 0, 'skipped' => 0];
        }

        if (! $this->hasPaymentReceivedBatch($payment)) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $byProperty = $this->allocatedAmountsByProperty($activeAllocations);
        if ($byProperty === []) {
            return ['posted' => 0, 'skipped' => 0];
        }

        $posted = 0;
        $skipped = 0;
        $paymentRef = $payment->external_ref ?: ('PAY-'.$payment->id);
        $occurredAt = $payment->paid_at ?? $payment->created_at ?? now();

        foreach ($byProperty as $propertyId => $grossCollected) {
            [$commissionPct, $netToOwners] = $this->netAfterCommission((int) $propertyId, (float) $grossCollected);
            if ($netToOwners <= 0) {
                continue;
            }

            $property = Property::query()->find($propertyId);
            $links = DB::table('property_landlord')
                ->where('property_id', $propertyId)
                ->get(['user_id', 'ownership_percent']);

            foreach ($links as $link) {
                $uid = (int) ($link->user_id ?? 0);
                $ownershipPct = (float) ($link->ownership_percent ?? 0);
                if ($uid <= 0 || $ownershipPct <= 0) {
                    continue;
                }

                $share = round($netToOwners * ($ownershipPct / 100), 2);
                if ($share <= 0) {
                    continue;
                }

                if ($this->hasOwnerCreditForPayment($payment, $uid, (int) $propertyId)) {
                    $skipped++;

                    continue;
                }

                $user = User::query()->find($uid);
                if (! $user) {
                    continue;
                }

                LandlordLedger::post(
                    $user,
                    PmLandlordLedgerEntry::DIRECTION_CREDIT,
                    $share,
                    'Rent collected '.$paymentRef.' (net after '.$commissionPct.'% commission)',
                    $property,
                    'pm_payment',
                    (int) $payment->id,
                    $occurredAt
                );

                $posted++;
            }
        }

        return ['posted' => $posted, 'skipped' => $skipped];
    }

    public function expectsCreditsForPayment(PmPayment $payment): bool
    {
        $payment->loadMissing('allocations.invoice.unit');
        $activeAllocations = ($payment->allocations ?? collect())
            ->filter(fn (PmPaymentAllocation $row) => ! $row->is_reversed);

        if ($activeAllocations->isEmpty() || ! $this->hasPaymentReceivedBatch($payment)) {
            return false;
        }

        foreach ($this->allocatedAmountsByProperty($activeAllocations) as $propertyId => $grossCollected) {
            [, $netToOwners] = $this->netAfterCommission((int) $propertyId, (float) $grossCollected);
            if ($netToOwners <= 0) {
                continue;
            }

            $hasOwners = DB::table('property_landlord')
                ->where('property_id', (int) $propertyId)
                ->where('ownership_percent', '>', 0)
                ->exists();

            if ($hasOwners) {
                return true;
            }
        }

        return false;
    }

    public function hasCreditsForPayment(PmPayment $payment): bool
    {
        return PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', (int) $payment->id)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)
            ->whereNull('reversal_of_id')
            ->exists();
    }

    public function hasOwnerCreditForPayment(PmPayment $payment, int $userId, int $propertyId): bool
    {
        $entries = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', (int) $payment->id)
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)
            ->whereNull('reversal_of_id')
            ->get();

        foreach ($entries as $entry) {
            if (! $this->entryAlreadyReversed($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectGaps(?int $tenantId = null, int $limit = 100): Collection
    {
        return app(AccountingFirebreakService::class)->detectLandlordLedgerGaps($tenantId, $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectDuplicateCredits(int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_landlord_ledger_entries')) {
            return collect();
        }

        $rows = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)
            ->whereNull('reversal_of_id')
            ->whereNotNull('reference_id')
            ->select([
                'reference_id as payment_id',
                'user_id',
                'property_id',
                DB::raw('COUNT(*) as credit_count'),
                DB::raw('ROUND(SUM(amount), 2) as total_amount'),
            ])
            ->groupBy('reference_id', 'user_id', 'property_id')
            ->having('credit_count', '>', 1)
            ->orderByDesc('credit_count')
            ->limit($limit)
            ->get();

        return collect($rows)->map(fn ($row) => [
            'payment_id' => (int) $row->payment_id,
            'user_id' => (int) $row->user_id,
            'property_id' => (int) ($row->property_id ?? 0),
            'credit_count' => (int) $row->credit_count,
            'total_amount' => round((float) $row->total_amount, 2),
            'message' => sprintf(
                'Duplicate owner credit on payment #%d for user #%d (property #%d).',
                (int) $row->payment_id,
                (int) $row->user_id,
                (int) ($row->property_id ?? 0),
            ),
        ]);
    }

    /**
     * @return array{scanned: int, posted_entries: int, skipped: int, errors: array<int, string>}
     */
    public function backfillMissing(?int $tenantId = null, int $limit = 200, bool $dryRun = false): array
    {
        $gaps = $this->detectGaps($tenantId, $limit);
        $postedEntries = 0;
        $skipped = 0;
        $errors = [];

        foreach ($gaps as $gap) {
            $paymentId = (int) ($gap['payment_id'] ?? 0);
            if ($paymentId <= 0) {
                continue;
            }

            $payment = PmPayment::query()->with('allocations.invoice.unit')->find($paymentId);
            if (! $payment) {
                continue;
            }

            if ($dryRun) {
                $postedEntries++;

                continue;
            }

            try {
                $result = $this->postCreditsForPayment($payment);
                $postedEntries += (int) $result['posted'];
                $skipped += (int) $result['skipped'];

                if ($result['posted'] > 0) {
                    PmAccountingAuditLog::recordIfNew(
                        PmAccountingAuditLog::ACTION_LANDLORD_SUBLEDGER_BACKFILL,
                        'pm_payment',
                        $paymentId,
                        [
                            'pm_tenant_id' => (int) $payment->pm_tenant_id,
                            'pm_payment_id' => $paymentId,
                            'summary' => 'Backfilled landlord subledger credits for payment #'.$paymentId,
                            'payload' => $result,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $errors[$paymentId] = $e->getMessage();
                report($e);
            }
        }

        return [
            'scanned' => $gaps->count(),
            'posted_entries' => $postedEntries,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Compare GL 2100 landlord payable from payment_received vs pm_payment subledger credits.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function reconcileGl2100VsSubledger(?int $propertyId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('accounting_journal_batches') || ! Schema::hasTable('pm_landlord_ledger_entries')) {
            return collect();
        }

        $payableAccountId = $this->payableAccountId();
        if ($payableAccountId === null) {
            return collect();
        }

        $glQuery = AccountingJournalLine::query()
            ->join('accounting_journal_batches as b', 'b.id', '=', 'accounting_journal_lines.batch_id')
            ->where('b.source_type', 'pm_payment')
            ->where('b.event_type', 'payment_received')
            ->where('b.status', AccountingJournalBatch::STATUS_POSTED)
            ->where('accounting_journal_lines.account_id', $payableAccountId)
            ->whereNotNull('accounting_journal_lines.property_id')
            ->when($propertyId !== null && $propertyId > 0, fn ($q) => $q->where('accounting_journal_lines.property_id', $propertyId))
            ->groupBy('accounting_journal_lines.property_id')
            ->selectRaw('accounting_journal_lines.property_id, ROUND(SUM(accounting_journal_lines.credit - accounting_journal_lines.debit), 2) as gl_net_payable');

        $glByProperty = $glQuery->pluck('gl_net_payable', 'property_id');

        $subledgerQuery = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->whereNotNull('property_id')
            ->when($propertyId !== null && $propertyId > 0, fn ($q) => $q->where('property_id', $propertyId))
            ->groupBy('property_id')
            ->selectRaw("property_id, ROUND(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 2) as subledger_net");

        $subledgerByProperty = $subledgerQuery->pluck('subledger_net', 'property_id');

        $propertyIds = collect($glByProperty->keys())
            ->merge($subledgerByProperty->keys())
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values();

        return $propertyIds
            ->map(function ($id) use ($glByProperty, $subledgerByProperty) {
                $propertyId = (int) $id;
                $glNet = round((float) ($glByProperty[$propertyId] ?? 0), 2);
                $subNet = round((float) ($subledgerByProperty[$propertyId] ?? 0), 2);
                $drift = round($glNet - $subNet, 2);

                return [
                    'property_id' => $propertyId,
                    'gl_2100_net' => $glNet,
                    'subledger_net' => $subNet,
                    'drift' => $drift,
                    'message' => sprintf(
                        'Property #%d GL 2100 net (KES %s) vs subledger net (KES %s), drift KES %s.',
                        $propertyId,
                        number_format($glNet, 2),
                        number_format($subNet, 2),
                        number_format($drift, 2),
                    ),
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['drift']) > 0.02)
            ->sortByDesc(fn (array $row) => abs((float) $row['drift']))
            ->take($limit)
            ->values();
    }

    public function reverseForPayment(PmPayment $payment, ?int $actorId = null, ?string $reason = null): void
    {
        $entries = PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', (int) $payment->id)
            ->whereNull('reversal_of_id')
            ->get();

        foreach ($entries as $entry) {
            if ($this->entryAlreadyReversed($entry)) {
                continue;
            }

            $reversalDirection = $entry->direction === PmLandlordLedgerEntry::DIRECTION_CREDIT
                ? PmLandlordLedgerEntry::DIRECTION_DEBIT
                : PmLandlordLedgerEntry::DIRECTION_CREDIT;

            $new = LandlordLedger::post(
                $entry->user,
                $reversalDirection,
                (float) $entry->amount,
                'Reversal of landlord ledger entry #'.$entry->id.($reason ? ' - '.$reason : ''),
                $entry->property,
                'pm_payment_reversal',
                (int) $payment->id,
                now()
            );

            $new->reversal_of_id = $entry->id;
            $new->reversed_by = $actorId;
            $new->reversed_at = now();
            $new->agent_user_id = $entry->agent_user_id;
            $new->save();
        }
    }

    /**
     * Reduce landlord subledger credits when a credit memo is issued against a paid invoice.
     */
    public function adjustForCreditMemo(
        PmInvoice $creditNote,
        PmInvoice $originalInvoice,
        float $creditAmount,
        ?int $actorId = null,
    ): int {
        if ($creditAmount <= 0 || (float) $originalInvoice->amount <= 0) {
            return 0;
        }

        $originalInvoice->loadMissing('unit.property');
        $propertyId = (int) optional($originalInvoice->unit)->property_id;
        if ($propertyId <= 0) {
            return 0;
        }

        $proportion = min(1.0, $creditAmount / abs((float) $originalInvoice->amount));
        [, $netToReverse] = $this->netAfterCommission($propertyId, round($creditAmount * $proportion, 2));
        if ($netToReverse <= 0) {
            return 0;
        }

        $posted = 0;
        $links = DB::table('property_landlord')
            ->where('property_id', $propertyId)
            ->get(['user_id', 'ownership_percent']);

        foreach ($links as $link) {
            $uid = (int) ($link->user_id ?? 0);
            $ownershipPct = (float) ($link->ownership_percent ?? 0);
            if ($uid <= 0 || $ownershipPct <= 0) {
                continue;
            }

            $share = round($netToReverse * ($ownershipPct / 100), 2);
            if ($share <= 0) {
                continue;
            }

            if ($this->hasOwnerCreditMemoAdjustment($creditNote, $uid, $propertyId)) {
                continue;
            }

            $user = User::query()->find($uid);
            $property = Property::query()->find($propertyId);
            if (! $user || ! $property) {
                continue;
            }

            LandlordLedger::post(
                $user,
                PmLandlordLedgerEntry::DIRECTION_DEBIT,
                $share,
                'Credit memo '.$creditNote->invoice_no.' landlord adjustment',
                $property,
                'pm_invoice',
                (int) $creditNote->id,
                now()
            );

            $posted++;
        }

        return $posted;
    }

    private function hasOwnerCreditMemoAdjustment(PmInvoice $creditNote, int $userId, int $propertyId): bool
    {
        return PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_invoice')
            ->where('reference_id', (int) $creditNote->id)
            ->where('user_id', $userId)
            ->where('property_id', $propertyId)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)
            ->exists();
    }

    /**
     * @param  Collection<int, PmPaymentAllocation>  $activeAllocations
     * @return array<int, float>
     */
    private function allocatedAmountsByProperty(Collection $activeAllocations): array
    {
        $byProperty = [];
        foreach ($activeAllocations as $allocation) {
            $propertyId = (int) optional(optional($allocation->invoice)->unit)->property_id;
            if ($propertyId <= 0) {
                continue;
            }
            $byProperty[$propertyId] = ($byProperty[$propertyId] ?? 0.0) + (float) $allocation->amount;
        }

        return $byProperty;
    }

    /**
     * @return array{0: float, 1: float} commission percent, net to owners
     */
    private function netAfterCommission(int $propertyId, float $grossCollected): array
    {
        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));
        $defaultPct = is_numeric($defaultRaw) ? (float) $defaultRaw : 10.0;
        $defaultPct = max(0.0, $defaultPct);
        $overrideRaw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $overrides = json_decode($overrideRaw, true);
        $overrides = is_array($overrides) ? $overrides : [];

        $commissionPct = is_numeric($overrides[(string) $propertyId] ?? null)
            ? max(0.0, (float) $overrides[(string) $propertyId])
            : $defaultPct;

        $commission = $grossCollected * ($commissionPct / 100);
        $netToOwners = max(0.0, round($grossCollected - $commission, 2));

        return [$commissionPct, $netToOwners];
    }

    private function hasPaymentReceivedBatch(PmPayment $payment): bool
    {
        return AccountingJournalBatch::query()
            ->where('source_type', 'pm_payment')
            ->where('source_id', (int) $payment->id)
            ->where('event_type', 'payment_received')
            ->where('status', AccountingJournalBatch::STATUS_POSTED)
            ->exists();
    }

    private function payableAccountId(): ?int
    {
        if ($this->payableAccountId === null) {
            $this->payableAccountId = AccountingChartAccount::query()
                ->where('code', self::ACC_LANDLORD_PAYABLE)
                ->value('id');
            $this->payableAccountId = $this->payableAccountId ? (int) $this->payableAccountId : null;
        }

        return $this->payableAccountId;
    }

    private function entryAlreadyReversed(PmLandlordLedgerEntry $entry): bool
    {
        return PmLandlordLedgerEntry::query()
            ->where('reversal_of_id', $entry->id)
            ->exists();
    }
}
