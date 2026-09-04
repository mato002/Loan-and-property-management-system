<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmPropertyTakeonBalance extends Model
{
    protected $table = 'pm_property_takeon_balances';

    protected $fillable = [
        'property_id',
        'landlord_id',
        'agent_user_id',
        'balance',
        'balance_date',
        'notes',
        'ledger_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'balance_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $userId = (int) auth()->id();
            $query->where(function (Builder $scope) use ($userId): void {
                $scope->where('pm_property_takeon_balances.agent_user_id', $userId)
                    ->orWhereIn('pm_property_takeon_balances.property_id', function ($sub) use ($userId): void {
                        $sub->select('id')->from('properties')->where('agent_user_id', $userId);
                    });
            });
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(PmLandlordLedgerEntry::class, 'ledger_entry_id');
    }
}
