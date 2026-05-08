<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class AccountingJournalBatch extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'date',
        'description',
        'source_type',
        'source_id',
        'event_type',
        'source_key',
        'status',
        'agent_user_id',
        'created_by',
        'posted_by',
        'approved_by',
        'reversed_by',
        'reversed_from_batch_id',
        'posted_at',
        'approved_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class, 'batch_id');
    }

    public function reversedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_from_batch_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}

