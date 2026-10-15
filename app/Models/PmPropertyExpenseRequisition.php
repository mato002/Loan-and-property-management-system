<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmPropertyExpenseRequisition extends Model
{
    protected $table = 'pm_property_expense_requisitions';

    protected $fillable = [
        'agent_user_id', 'pm_vendor_id', 'property_id', 'title', 'description',
        'vendor_invoice_no', 'attachment_path', 'requested_date', 'due_date', 'amount', 'status',
        'requested_by', 'reviewed_by', 'reviewed_at', 'pm_ezen_bill_id', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query): void {
            if (AgentWorkspaceScope::shouldApply()) {
                AgentWorkspaceScope::whereWorkspaceOwner($query, 'pm_property_expense_requisitions.agent_user_id');
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(PmVendor::class, 'pm_vendor_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PmEzenBill::class, 'pm_ezen_bill_id');
    }
}
