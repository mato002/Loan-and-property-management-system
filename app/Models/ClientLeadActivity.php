<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLeadActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'client_lead_id',
        'user_id',
        'activity_type',
        'notes',
        'next_action_date',
    ];

    protected function casts(): array
    {
        return [
            'next_action_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function clientLead(): BelongsTo
    {
        return $this->belongsTo(ClientLead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
