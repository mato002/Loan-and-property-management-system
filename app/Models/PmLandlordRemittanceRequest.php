<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmLandlordRemittanceRequest extends Model
{
    protected $table = 'pm_landlord_remittance_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'amount',
        'destination',
        'destination_detail',
        'reference_note',
        'status',
        'processed_by_user_id',
        'acknowledged_at',
        'paid_at',
        'paid_reference',
        'ledger_entry_id',
        'agency_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'acknowledged_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(PmLandlordLedgerEntry::class, 'ledger_entry_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACKNOWLEDGED => 'Acknowledged',
            self::STATUS_PAID => 'Marked paid',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Pending',
        };
    }
}
