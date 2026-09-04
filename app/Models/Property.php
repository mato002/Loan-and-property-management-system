<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Property extends Model
{
    public const MANAGEMENT_ACTIVE = 'active';

    public const MANAGEMENT_OFFBOARDING = 'offboarding';

    public const MANAGEMENT_ARCHIVED = 'archived';

    public const MANAGEMENT_ENDED = 'ended_management';

    /** @var list<string> */
    public const MANAGEMENT_STATUSES = [
        self::MANAGEMENT_ACTIVE,
        self::MANAGEMENT_OFFBOARDING,
        self::MANAGEMENT_ARCHIVED,
        self::MANAGEMENT_ENDED,
    ];

    /** @var list<string> */
    public const READ_ONLY_MANAGEMENT_STATUSES = [
        self::MANAGEMENT_ARCHIVED,
        self::MANAGEMENT_ENDED,
    ];

    /** @var list<string> */
    public const NON_OPERATIONAL_MANAGEMENT_STATUSES = [
        self::MANAGEMENT_ARCHIVED,
        self::MANAGEMENT_ENDED,
    ];

    protected $fillable = [
        'name',
        'code',
        'address_line',
        'city',
        'agent_user_id',
        'field_officer_id',
        'rent_due_day',
        'management_status',
        'management_ended_at',
        'management_end_reason',
        'archived_at',
        'archived_by',
        'offboarding_notes',
    ];

    protected function casts(): array
    {
        return [
            'rent_due_day' => 'integer',
            'management_ended_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isManagementActive(): bool
    {
        return $this->managementStatus() === self::MANAGEMENT_ACTIVE;
    }

    public function isOffboarding(): bool
    {
        return $this->managementStatus() === self::MANAGEMENT_OFFBOARDING;
    }

    public function isManagementReadOnly(): bool
    {
        return in_array($this->managementStatus(), self::READ_ONLY_MANAGEMENT_STATUSES, true);
    }

    public function isNonOperational(): bool
    {
        return in_array($this->managementStatus(), self::NON_OPERATIONAL_MANAGEMENT_STATUSES, true);
    }

    public function managementStatus(): string
    {
        if (! Schema::hasColumn('properties', 'management_status')) {
            return self::MANAGEMENT_ACTIVE;
        }

        return (string) ($this->management_status ?? self::MANAGEMENT_ACTIVE);
    }

    public function managementStatusLabel(): string
    {
        return match ($this->managementStatus()) {
            self::MANAGEMENT_OFFBOARDING => 'Offboarding',
            self::MANAGEMENT_ARCHIVED => 'Archived',
            self::MANAGEMENT_ENDED => 'Ended management',
            default => 'Active',
        };
    }

    /**
     * Exclude archived / ended-management properties from operational dashboards.
     */
    public function scopeOperational($query)
    {
        if (! Schema::hasColumn('properties', 'management_status')) {
            return $query;
        }

        return $query->whereNotIn('management_status', self::NON_OPERATIONAL_MANAGEMENT_STATUSES);
    }

    /**
     * @param  list<string>|string|null  $statuses
     */
    public function scopeManagementStatus($query, array|string|null $statuses)
    {
        if (! Schema::hasColumn('properties', 'management_status')) {
            return $query;
        }

        if ($statuses === null || $statuses === '' || $statuses === 'all') {
            return $query;
        }

        $list = is_array($statuses) ? $statuses : [$statuses];

        return $query->whereIn('management_status', $list);
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function ($query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->where('properties.agent_user_id', $user->id);
        });
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class);
    }

    public function agentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function fieldOfficer(): BelongsTo
    {
        return $this->belongsTo(PmFieldOfficer::class, 'field_officer_id');
    }

    /**
     * All invoices linked to this property through its units.
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(
            PmInvoice::class,
            PropertyUnit::class,
            'property_id',
            'property_unit_id',
            'id',
            'id'
        );
    }

    public function landlords(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'property_landlord')
            ->withPivot('ownership_percent')
            ->withTimestamps();
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(PmAmenity::class, 'pm_amenity_property', 'property_id', 'pm_amenity_id')
            ->withTimestamps();
    }

    public function depositDefinitions(): HasMany
    {
        return $this->hasMany(DepositDefinition::class, 'property_id');
    }

    public function expenseDefinitions(): HasMany
    {
        return $this->hasMany(ExpenseDefinition::class, 'property_id');
    }
}
