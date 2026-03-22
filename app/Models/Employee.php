<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department',
        'job_title',
        'branch',
        'hire_date',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function staffLeaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class);
    }

    public function staffGroups(): BelongsToMany
    {
        return $this->belongsToMany(StaffGroup::class, 'staff_group_employee')->withTimestamps();
    }

    public function staffPortfolios(): HasMany
    {
        return $this->hasMany(StaffPortfolio::class);
    }

    public function staffLoanApplications(): HasMany
    {
        return $this->hasMany(StaffLoanApplication::class);
    }

    public function staffLoans(): HasMany
    {
        return $this->hasMany(StaffLoan::class);
    }
}
