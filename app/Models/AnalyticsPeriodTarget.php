<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsPeriodTarget extends Model
{
    protected $table = 'analytics_period_targets';

    protected $fillable = [
        'branch',
        'period_year',
        'period_month',
        'disbursement_target',
        'collection_target',
        'accrual_target',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_target' => 'decimal:2',
            'collection_target' => 'decimal:2',
            'accrual_target' => 'decimal:2',
        ];
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
