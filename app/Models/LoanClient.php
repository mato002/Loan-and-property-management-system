<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanClient extends Model
{
    public const KIND_CLIENT = 'client';

    public const KIND_LEAD = 'lead';

    protected $fillable = [
        'client_number',
        'kind',
        'first_name',
        'last_name',
        'phone',
        'email',
        'id_number',
        'gender',
        'next_of_kin_name',
        'next_of_kin_contact',
        'client_photo_path',
        'id_front_photo_path',
        'id_back_photo_path',
        'biodata_meta',
        'address',
        'branch',
        'assigned_employee_id',
        'lead_status',
        'client_status',
        'notes',
        'guarantor_1_full_name',
        'guarantor_1_phone',
        'guarantor_1_id_number',
        'guarantor_1_relationship',
        'guarantor_1_address',
        'guarantor_2_full_name',
        'guarantor_2_phone',
        'guarantor_2_id_number',
        'guarantor_2_relationship',
        'guarantor_2_address',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
            'biodata_meta' => 'array',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function defaultGroups(): BelongsToMany
    {
        return $this->belongsToMany(DefaultClientGroup::class, 'default_client_group_loan_client')
            ->withTimestamps();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class)->orderByDesc('interacted_at');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ClientTransfer::class)->orderByDesc('created_at');
    }

    public function loanBookLoans(): HasMany
    {
        return $this->hasMany(LoanBookLoan::class, 'loan_client_id');
    }

    public function scopeClients($query)
    {
        return $query->where('kind', self::KIND_CLIENT);
    }

    public function scopeLeads($query)
    {
        return $query->where('kind', self::KIND_LEAD);
    }
}
