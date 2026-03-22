<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInteraction extends Model
{
    protected $fillable = [
        'loan_client_id',
        'user_id',
        'interaction_type',
        'subject',
        'notes',
        'interacted_at',
    ];

    protected function casts(): array
    {
        return [
            'interacted_at' => 'datetime',
        ];
    }

    public function loanClient(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
