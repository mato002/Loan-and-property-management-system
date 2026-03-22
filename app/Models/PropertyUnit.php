<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyUnit extends Model
{
    public const STATUS_VACANT = 'vacant';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_NOTICE = 'notice';

    protected $fillable = [
        'property_id',
        'label',
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

    public function primaryPublicImageUrl(): ?string
    {
        $first = $this->relationLoaded('publicImages')
            ? $this->publicImages->first()
            : $this->publicImages()->first();

        return $first?->publicUrl();
    }
}
