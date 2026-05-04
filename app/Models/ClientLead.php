<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientLead extends Model
{
    public const STAGE_NEW = 'new';

    public const STAGE_CONTACTED = 'contacted';

    public const STAGE_INTERESTED = 'interested';

    public const STAGE_APPLIED = 'applied';

    public const STAGE_APPROVED = 'approved';

    public const STAGE_DISBURSED = 'disbursed';

    public const STAGE_DROPPED = 'dropped';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_DROPPED = 'dropped';

    protected $fillable = [
        'loan_client_id',
        'lead_source',
        'assigned_officer_id',
        'expected_loan_amount',
        'approved_amount',
        'disbursed_amount',
        'current_stage',
        'pipeline_status',
        'stage_entered_at',
        'first_activity_at',
        'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_loan_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'disbursed_amount' => 'decimal:2',
            'stage_entered_at' => 'datetime',
            'first_activity_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ClientLeadActivity::class)->orderByDesc('created_at');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClientLeadStatusHistory::class)->orderByDesc('created_at');
    }

    public function lossReasons(): HasMany
    {
        return $this->hasMany(ClientLeadLossReason::class)->orderByDesc('created_at');
    }

    public function isConvertedByDefinition(): bool
    {
        if ($this->current_stage === self::STAGE_DISBURSED) {
            return true;
        }

        return (float) ($this->disbursed_amount ?? 0) > 0;
    }
}
