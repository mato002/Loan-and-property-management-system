<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'scope',
        'created_by',
        'is_active',
        'reference_prefix',
        'default_action',
        'template_lines',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'template_lines' => 'array',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
