<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanBranch extends Model
{
    protected $fillable = [
        'loan_region_id',
        'code',
        'name',
        'address',
        'phone',
        'manager_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(LoanRegion::class, 'loan_region_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LoanBookLoan::class, 'loan_branch_id');
    }

    public function regionChanges(): HasMany
    {
        return $this->hasMany(LoanBranchRegionChange::class, 'loan_branch_id');
    }
}
