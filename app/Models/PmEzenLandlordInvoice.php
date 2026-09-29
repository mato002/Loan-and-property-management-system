<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmEzenLandlordInvoice extends Model
{
    protected $table = 'pm_ezen_landlord_invoices';

    protected $fillable = [
        'agent_user_id',
        'source_key',
        'ezen_invoice_no',
        'invoice_date',
        'due_date',
        'property_code',
        'property_name',
        'period_label',
        'period_month',
        'particulars',
        'total_amount',
        'total_paid',
        'amount_due',
        'listing_status',
        'property_id',
        'landlord_user_id',
        'link_status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $query->where('pm_ezen_landlord_invoices.agent_user_id', (int) auth()->id());
        });
    }

    public function scopeForAgent(Builder $query, int $agentUserId): Builder
    {
        return $query->withoutGlobalScopes()->where('agent_user_id', $agentUserId);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }
}
