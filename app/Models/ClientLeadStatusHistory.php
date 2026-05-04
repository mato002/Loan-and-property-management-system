<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLeadStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'client_lead_status_history';

    protected $fillable = [
        'client_lead_id',
        'from_stage',
        'to_stage',
        'changed_by',
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

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
