<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'agent_user_id',
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
        'probation_ends_on',
        'onboarding_completed_at',
        'exit_date',
        'exit_reason',
        'offboarding_notes',
        'offboarded_by_user_id',
    ];

    public const STATUSES = [
        'onboarding' => 'Onboarding',
        'active' => 'Active',
        'on_leave' => 'On leave',
        'terminated' => 'Offboarded',
    ];

    public const EXIT_REASONS = [
        'resignation' => 'Resignation',
        'end_of_contract' => 'End of contract',
        'dismissal' => 'Dismissal',
        'redundancy' => 'Redundancy',
        'retirement' => 'Retirement',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'probation_ends_on' => 'date',
            'onboarding_completed_at' => 'datetime',
            'exit_date' => 'date',
        ];
    }

    public function employmentStatusKey(): string
    {
        $status = strtolower(trim((string) $this->employment_status));

        return array_key_exists($status, self::STATUSES) ? $status : 'active';
    }

    public function employmentStatusLabel(): string
    {
        return self::STATUSES[$this->employmentStatusKey()] ?? 'Active';
    }

    public function isOnboarding(): bool
    {
        return $this->employmentStatusKey() === 'onboarding';
    }

    public function isActiveEmployment(): bool
    {
        return in_array($this->employmentStatusKey(), ['active', 'on_leave'], true);
    }

    public function isOffboarded(): bool
    {
        return $this->employmentStatusKey() === 'terminated';
    }

    public function offboardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offboarded_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function fieldOfficerProfile(): HasOne
    {
        return $this->hasOne(PmFieldOfficer::class, 'employee_id');
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

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function trainingRecords(): HasMany
    {
        return $this->hasMany(StaffTrainingRecord::class);
    }
}
