<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsPerformanceRecord extends Model
{
    protected $table = 'analytics_performance_records';

    protected $fillable = [
        'record_date',
        'branch',
        'total_outstanding',
        'disbursements_period',
        'collections_period',
        'npl_rate',
        'active_borrowers_count',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'total_outstanding' => 'decimal:2',
            'disbursements_period' => 'decimal:2',
            'collections_period' => 'decimal:2',
            'npl_rate' => 'decimal:2',
        ];
    }
}
