<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PropertyUnit extends Model
{
    public const STATUS_VACANT = 'vacant';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_NOTICE = 'notice';
    public const TYPE_APARTMENT = 'apartment';
    public const TYPE_SINGLE_ROOM = 'single_room';
    public const TYPE_BEDSITTER = 'bedsitter';
    public const TYPE_STUDIO = 'studio';
    public const TYPE_BUNGALOW = 'bungalow';
    public const TYPE_MAISONETTE = 'maisonette';
    public const TYPE_VILLA = 'villa';
    public const TYPE_TOWNHOUSE = 'townhouse';
    public const TYPE_COMMERCIAL = 'commercial';

    protected $fillable = [
        'property_id',
        'label',
        'unit_type',
        'bedrooms',
        'rent_amount',
        'status',
        'vacant_since',
        'public_listing_published',
        'public_listing_description',
    ];

    protected function casts(): array
    {
        return [
            'rent_amount' => 'decimal:2',
            'vacant_since' => 'date',
            'public_listing_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->whereIn('property_id', function ($sub) use ($user) {
                $sub->select('id')
                    ->from('properties')
                    ->where('agent_user_id', $user->id);
            });
        });
    }

    /**
     * Vacant units eligible for the public directory (Discover, home, details).
     * Agents can still refine copy and photos under Listings; publish only affects ordering on the home page.
     *
     * @param  Builder<PropertyUnit>  $query
     * @return Builder<PropertyUnit>
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VACANT);
    }

    /**
     * Vacant units the agent has explicitly marked live (photos + publish toggle satisfied).
     *
     * @param  Builder<PropertyUnit>  $query
     * @return Builder<PropertyUnit>
     */
    public function scopePublicListingPublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_VACANT)
            ->where('public_listing_published', true);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function leases(): BelongsToMany
    {
        return $this->belongsToMany(PmLease::class, 'pm_lease_unit', 'property_unit_id', 'pm_lease_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PmInvoice::class, 'property_unit_id');
    }

    /**
     * ERP-style link: all tenants who have been billed on this unit.
     */
    public function tenants(): HasManyThrough
    {
        return $this->hasManyThrough(
            PmTenant::class,
            PmInvoice::class,
            'property_unit_id',
            'id',
            'id',
            'pm_tenant_id'
        );
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(PmMaintenanceRequest::class, 'property_unit_id');
    }

    /**
     * @return HasMany<PropertyUnitPublicImage, $this>
     */
    public function publicImages(): HasMany
    {
        return $this->hasMany(PropertyUnitPublicImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<PmAmenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(PmAmenity::class, 'pm_amenity_unit', 'property_unit_id', 'pm_amenity_id')
            ->withTimestamps();
    }

    /**
     * @return HasMany<PmUnitUtilityCharge, $this>
     */
    public function utilityCharges(): HasMany
    {
        return $this->hasMany(PmUnitUtilityCharge::class, 'property_unit_id');
    }

    public function depositDefinitions(): HasMany
    {
        return $this->hasMany(DepositDefinition::class, 'property_unit_id');
    }

    public function expenseDefinitions(): HasMany
    {
        return $this->hasMany(ExpenseDefinition::class, 'property_unit_id');
    }

    public function primaryPublicImageUrl(): ?string
    {
        $first = $this->relationLoaded('publicImages')
            ? $this->publicImages->first()
            : $this->publicImages()->first();

        return $first?->publicUrl();
    }

    /**
     * @return array<string,string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_APARTMENT => 'Apartment',
            self::TYPE_SINGLE_ROOM => 'Single room',
            self::TYPE_BEDSITTER => 'Bedsitter',
            self::TYPE_STUDIO => 'Studio',
            self::TYPE_BUNGALOW => 'Bungalow',
            self::TYPE_MAISONETTE => 'Maisonette',
            self::TYPE_VILLA => 'Villa',
            self::TYPE_TOWNHOUSE => 'Townhouse',
            self::TYPE_COMMERCIAL => 'Commercial',
        ];
    }

    public function unitTypeLabel(): string
    {
        if (isset(self::typeOptions()[$this->unit_type])) {
            return self::typeOptions()[$this->unit_type];
        }

        return (string) \Illuminate\Support\Str::of((string) $this->unit_type)
            ->replace(['_', '-'], ' ')
            ->title();
    }

    public function bedroomsLabel(): string
    {
        $count = (int) $this->bedrooms;
        if ($count <= 0) {
            return match ($this->unit_type) {
                self::TYPE_SINGLE_ROOM => 'Single room',
                self::TYPE_BEDSITTER => 'Bedsitter',
                self::TYPE_STUDIO => 'Studio',
                default => 'No separate bedroom',
            };
        }

        return $count.' '.\Illuminate\Support\Str::plural('bedroom', $count);
    }
}
