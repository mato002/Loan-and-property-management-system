<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLeadLossReason extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_lead_id',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function clientLead(): BelongsTo
    {
        return $this->belongsTo(ClientLead::class);
    }
}
