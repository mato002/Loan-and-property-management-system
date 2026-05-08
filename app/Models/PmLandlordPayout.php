<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmLandlordPayout extends Model
{
    protected $table = 'pm_landlord_payouts';

    protected $fillable = [
        'agent_user_id',
        'total_amount',
        'status',
        'created_by',
        'approved_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_landlord_payouts', 'agent_user_id');
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PmLandlordPayoutItem::class, 'payout_id');
    }
}

