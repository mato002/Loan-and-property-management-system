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
use Illuminate\Support\Facades\Schema;

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
     * @return BelongsToMany<PmRole, $this>
     */
    public function pmRoles(): BelongsToMany
    {
        return $this->belongsToMany(PmRole::class, 'pm_user_role', 'user_id', 'pm_role_id')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<PmPermission, $this>
     */
    public function pmPermissions(): BelongsToMany
    {
        return $this->belongsToMany(PmPermission::class, 'pm_user_permission', 'user_id', 'pm_permission_id')
            ->withTimestamps();
    }

    public function hasPmPermission(string $permissionKey): bool
    {
        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_permissions') || ! Schema::hasTable('pm_user_role')) {
            return true; // Legacy-safe until RBAC tables are migrated.
        }

        $roles = $this->pmRoles()->with('permissions:id,key')->get();
        if ($roles->isEmpty()) {
            return true; // Keep existing behavior until roles are assigned.
        }

        $roleHas = $roles
            ->flatMap(fn (PmRole $role) => $role->permissions->pluck('key'))
            ->contains($permissionKey);

        if ($roleHas) {
            return true;
        }

        return $this->pmPermissions()->where('key', $permissionKey)->exists();
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
