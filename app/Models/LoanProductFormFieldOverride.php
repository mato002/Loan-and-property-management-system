<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductFormFieldOverride extends Model
{
    protected $fillable = [
        'product_id',
        'form_kind',
        'field_key',
        'is_included',
        'is_required',
        'prefill_from_previous',
        'visible_to',
        'display_status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_included' => 'boolean',
            'is_required' => 'boolean',
            'prefill_from_previous' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'product_id');
    }
}
