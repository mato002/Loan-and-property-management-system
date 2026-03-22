<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TellerMovement extends Model
{
    protected $fillable = [
        'teller_session_id',
        'kind',
        'amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function tellerSession(): BelongsTo
    {
        return $this->belongsTo(TellerSession::class);
    }
}
