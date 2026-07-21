<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmCommunicationExport extends Model
{
    protected $table = 'lm_communication_exports';

    protected $fillable = [
        'exported_by_user_id',
        'report_type',
        'format',
        'export_reason',
        'row_count',
        'filters',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'exported_at' => 'datetime',
        ];
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by_user_id');
    }
}
