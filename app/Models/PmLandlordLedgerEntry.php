<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmLandlordLedgerEntry extends Model
{
    protected $table = 'pm_landlord_ledger_entries';

    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'user_id',
        'property_id',
        'agent_user_id',
        'direction',
        'amount',
        'balance_after',
        'description',
        'reference_type',
        'reference_id',
        'reversal_of_id',
        'reversed_by',
        'reversed_at',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'reversed_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Agent isolation: prefer the direct `agent_user_id` column when
     * present; fall back to the property workspace check for older rows
     * that pre-date the column.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $userId = (int) \Illuminate\Support\Facades\Auth::id();

            $query->where(function (Builder $scope) use ($userId) {
                $scope->where('pm_landlord_ledger_entries.agent_user_id', $userId)
                    ->orWhere(function (Builder $legacy) use ($userId) {
                        $legacy->whereNull('pm_landlord_ledger_entries.agent_user_id')
                            ->whereIn('pm_landlord_ledger_entries.property_id', function ($sub) use ($userId) {
                                $sub->select('id')->from('properties')->where('agent_user_id', $userId);
                            });
                    });
            });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
