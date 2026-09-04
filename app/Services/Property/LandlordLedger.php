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
}
