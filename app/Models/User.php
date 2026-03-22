<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'property_portal_role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsToMany<Property, $this>
     */
    public function landlordProperties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_landlord')
            ->withPivot('ownership_percent')
            ->withTimestamps();
    }

    /**
     * @return HasOne<PmTenant, $this>
     */
    public function pmTenantProfile(): HasOne
    {
        return $this->hasOne(PmTenant::class, 'user_id');
    }

    /**
     * @return HasMany<PmTenantPortalRequest, $this>
     */
    public function pmTenantPortalRequests(): HasMany
    {
        return $this->hasMany(PmTenantPortalRequest::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
