<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TellerSession extends Model
{
    protected $fillable = [
        'branch_label',
        'opened_by',
        'opening_float',
        'closing_float',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'closing_float' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TellerMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    public function cashInTotal(): string
    {
        return (string) $this->movements()->where('kind', 'cash_in')->sum('amount');
    }

    public function cashOutTotal(): string
    {
        return (string) $this->movements()->where('kind', 'cash_out')->sum('amount');
    }
}
