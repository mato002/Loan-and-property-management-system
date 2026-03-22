<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPettyCashEntry extends Model
{
    public const KIND_RECEIPT = 'receipt';

    public const KIND_DISBURSEMENT = 'disbursement';

    protected $fillable = [
        'entry_date',
        'kind',
        'amount',
        'payee_or_source',
        'description',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'entry_date' => 'date',
        ];
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
