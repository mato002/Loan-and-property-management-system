<?php

namespace App\Services\Property;

use App\Models\PmLandlordLedgerEntry;
use App\Models\Property;
use App\Models\User;
use RuntimeException;

final class LandlordLedger
{
    public static function post(
        User $user,
        string $direction,
        float $amount,
        string $description,
        ?Property $property = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?\DateTimeInterface $occurredAt = null,
        ?int $agentUserId = null,
    ): PmLandlordLedgerEntry {
        if ($amount <= 0) {
            throw new RuntimeException('Ledger amount must be greater than zero.');
        }

        $last = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        $balance = $last ? (float) $last->balance_after : 0.0;
        if ($direction === PmLandlordLedgerEntry::DIRECTION_CREDIT) {
            $balance += $amount;
        } else {
            $balance -= $amount;
        }

        $entry = PmLandlordLedgerEntry::query()->create([
            'user_id' => $user->id,
            'property_id' => $property?->id,
            'agent_user_id' => $agentUserId ?? $property?->agent_user_id,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'occurred_at' => $occurredAt ?? now(),
        ]);

        return $entry;
    }

    public static function balance(User $user): float
    {
        $last = PmLandlordLedgerEntry::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    /** Post a paid property expense across owners according to their registered shares. */
    public static function postPropertyExpenseShares(
        Property $property,
        float $amount,
        string $description,
        string $referenceType,
        int $referenceId,
        ?\DateTimeInterface $occurredAt = null,
        ?int $agentUserId = null,
    ): int {
        if ($amount <= 0) {
            return 0;
        }

        $landlords = $property->landlords()->get()->filter(
            fn (User $landlord) => (float) ($landlord->pivot->ownership_percent ?? 0) > 0,
        )->values();
        if ($landlords->isEmpty()) {
            return 0;
        }

        $weights = $landlords->map(fn (User $landlord) => (float) $landlord->pivot->ownership_percent)->values();
        $weightTotal = $weights->sum();
        if ($weightTotal <= 0) {
            return 0;
        }

        $amount = round($amount, 2);
        $remaining = $amount;
        $posted = 0;
        foreach ($landlords as $index => $landlord) {
            $share = $index === $landlords->count() - 1
                ? $remaining
                : round($amount * ($weights[$index] / $weightTotal), 2);
            $remaining = round($remaining - $share, 2);
            if ($share <= 0) {
                continue;
            }

            $exists = PmLandlordLedgerEntry::query()
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('user_id', $landlord->id)
                ->where('property_id', $property->id)
                ->where('direction', PmLandlordLedgerEntry::DIRECTION_DEBIT)
                ->whereNull('reversal_of_id')
                ->exists();
            if ($exists) {
                continue;
            }

            self::post(
                $landlord,
                PmLandlordLedgerEntry::DIRECTION_DEBIT,
                $share,
                $description,
                $property,
                $referenceType,
                $referenceId,
                $occurredAt,
                $agentUserId,
            );
            $posted++;
        }

        return $posted;
    }
}
