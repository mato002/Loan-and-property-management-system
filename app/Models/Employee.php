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
        'personal_email',
        'phone',
        'department',
        'job_title',
        'employment_status',
        'work_type',
        'gender',
        'national_id',
        'next_of_kin_name',
        'next_of_kin_phone',
        'branch',
        'supervisor_employee_id',
        'assigned_tools',
        'kra_pin',
        'bank_name',
        'bank_account_number',
        'nhif_number',
        'nssf_number',
        'employment_contract_scan',
        'hire_date',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function supervisor()
    {
        return $this->belongsTo(self::class, 'supervisor_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_employee_id');
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
