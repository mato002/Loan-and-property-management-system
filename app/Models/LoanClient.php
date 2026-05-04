<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanClient extends Model
{
    public const KIND_CLIENT = 'client';

    public const KIND_LEAD = 'lead';

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_LEAD = 'lead';

    public const SOURCE_PORTAL = 'portal';

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
        'created_by',
        'source_channel',
        'converted_by',
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

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * Client number uses the lead prefix (LD-), not business kind.
     */
    public function isLeadNumber(): bool
    {
        $n = strtoupper(trim((string) ($this->client_number ?? '')));

        return $n !== '' && str_starts_with($n, 'LD-');
    }

    /**
     * Standard internal client number prefix (CL-).
     */
    public function isClientNumber(): bool
    {
        $n = strtoupper(trim((string) ($this->client_number ?? '')));

        return $n !== '' && str_starts_with($n, 'CL-');
    }

    public function isPortalNumber(): bool
    {
        $n = strtoupper(trim((string) ($this->client_number ?? '')));

        return $n !== '' && str_starts_with($n, 'PORTAL-');
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

    public function wallet(): HasOne
    {
        return $this->hasOne(ClientWallet::class, 'loan_client_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(ClientWalletTransaction::class, 'loan_client_id')->orderByDesc('id');
    }

    public function clientLead(): HasOne
    {
        return $this->hasOne(ClientLead::class, 'loan_client_id');
    }

    /**
     * URL for the loan-portal profile: full client page for clients, lead workspace for prospects.
     */
    public function loanPortalProfileUrl(): string
    {
        return $this->kind === self::KIND_LEAD
            ? route('loan.clients.leads.show', $this)
            : route('loan.clients.show', $this);
    }

    public function walletBalance(): float
    {
        return (float) ($this->wallet?->balance ?? 0.0);
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
