<?php

namespace App\Services\Property;

use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordRemittanceRequest;
use App\Models\User;
use Illuminate\Support\Collection;

final class LandlordPortalRemittanceService
{
    public function ledgerBalance(User $user): float
    {
        return LandlordLedger::balance($user);
    }

    public function pendingRemittanceTotal(User $user): float
    {
        return (float) PmLandlordRemittanceRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                PmLandlordRemittanceRequest::STATUS_PENDING,
                PmLandlordRemittanceRequest::STATUS_ACKNOWLEDGED,
            ])
            ->sum('amount');
    }

    public function availableForRequest(User $user): float
    {
        return max(0.0, $this->ledgerBalance($user) - $this->pendingRemittanceTotal($user));
    }

    /**
     * @return Collection<int, PmLandlordRemittanceRequest>
     */
    public function recentRequests(User $user, int $limit = 50): Collection
    {
        return PmLandlordRemittanceRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function createRequest(
        User $user,
        float $amount,
        string $destination,
        string $destinationDetail,
        ?string $referenceNote = null,
    ): PmLandlordRemittanceRequest {
        $available = $this->availableForRequest($user);
        if ($amount > $available) {
            throw new \InvalidArgumentException('Amount exceeds available ledger balance after pending instructions.');
        }

        return PmLandlordRemittanceRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'destination' => $destination,
            'destination_detail' => $destinationDetail,
            'reference_note' => $referenceNote,
            'status' => PmLandlordRemittanceRequest::STATUS_PENDING,
        ]);
    }

    public function acknowledge(PmLandlordRemittanceRequest $request, User $agent, ?string $notes = null): void
    {
        $request->update([
            'status' => PmLandlordRemittanceRequest::STATUS_ACKNOWLEDGED,
            'processed_by_user_id' => $agent->id,
            'acknowledged_at' => now(),
            'agency_notes' => $notes ?? $request->agency_notes,
        ]);
    }

    public function markPaid(
        PmLandlordRemittanceRequest $request,
        User $agent,
        ?string $paidReference = null,
        ?string $notes = null,
        bool $postLedgerDebit = true,
    ): void {
        if ($request->status === PmLandlordRemittanceRequest::STATUS_PAID) {
            return;
        }

        $ledgerEntryId = null;
        if ($postLedgerDebit && ! $request->ledger_entry_id) {
            $entry = LandlordLedger::post(
                $request->user,
                PmLandlordLedgerEntry::DIRECTION_DEBIT,
                (float) $request->amount,
                'Remittance paid manually ('.$request->destination.' — '.$request->destination_detail.')'
                    .($paidReference ? ' ref: '.$paidReference : ''),
                null,
                'pm_landlord_remittance_request',
                (int) $request->id,
            );
            $ledgerEntryId = (int) $entry->id;
        }

        $request->update([
            'status' => PmLandlordRemittanceRequest::STATUS_PAID,
            'processed_by_user_id' => $agent->id,
            'paid_at' => now(),
            'paid_reference' => $paidReference,
            'agency_notes' => $notes ?? $request->agency_notes,
            'ledger_entry_id' => $ledgerEntryId ?? $request->ledger_entry_id,
        ]);
    }

    public function cancel(PmLandlordRemittanceRequest $request, User $agent, ?string $notes = null): void
    {
        if ($request->status === PmLandlordRemittanceRequest::STATUS_PAID) {
            throw new \InvalidArgumentException('Paid remittance instructions cannot be cancelled.');
        }

        $request->update([
            'status' => PmLandlordRemittanceRequest::STATUS_CANCELLED,
            'processed_by_user_id' => $agent->id,
            'agency_notes' => $notes ?? $request->agency_notes,
        ]);
    }
}
