<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquitySyncRun extends Model
{
    protected $table = 'equity_sync_runs';

    protected $fillable = [
        'status',
        'trigger',
        'started_at',
        'finished_at',
        'fetched_count',
        'matched_count',
        'unmatched_count',
        'duplicate_count',
        'error_count',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

