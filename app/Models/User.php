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

#[Fillable(['name', 'email', 'password', 'property_portal_role', 'is_super_admin'])]
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

    public function moduleAccesses(): HasMany
    {
        return $this->hasMany(UserModuleAccess::class, 'user_id');
    }

    /**
     * @return list<string> modules the user is approved for (e.g. ['property', 'loan'])
     */
    public function approvedModules(): array
    {
        if (($this->is_super_admin ?? false) === true) {
            return ['property', 'loan'];
        }

        if (! Schema::hasTable('user_module_accesses')) {
            // Legacy-safe: before migrations, infer module from existing field.
            // Property users have `property_portal_role` set; loan users typically do not.
            return $this->property_portal_role ? ['property'] : ['loan'];
        }

        return $this->moduleAccesses()
            ->where('status', UserModuleAccess::STATUS_APPROVED)
            ->pluck('module')
            ->all();
    }

    public function moduleAccessStatus(string $module): ?string
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return null;
        }

        return $this->moduleAccesses()
            ->where('module', $module)
            ->value('status');
    }

    public function isModuleApproved(string $module): bool
    {
        if (($this->is_super_admin ?? false) === true) {
            return true;
        }

        if (! Schema::hasTable('user_module_accesses')) {
            return match ($module) {
                'property' => (bool) ($this->property_portal_role ?? null),
                'loan' => ! (bool) ($this->property_portal_role ?? null),
                default => true,
            };
        }

        return $this->moduleAccesses()
            ->where('module', $module)
            ->where('status', UserModuleAccess::STATUS_APPROVED)
            ->exists();
    }

    public function hasPmPermission(string $permissionKey): bool
    {
        if (($this->is_super_admin ?? false) === true) {
            return true;
        }

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

    public function agentSubscription(): ?AgentSubscription
    {
        if (! Schema::hasTable('agent_subscriptions') || $this->property_portal_role !== 'agent') {
            return null;
        }

        return AgentSubscription::getActiveSubscriptionForUser($this->id);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->agentSubscription() !== null;
    }

    public function subscriptionStatus(): string
    {
        $subscription = $this->agentSubscription();
        
        if (!$subscription) {
            return 'none';
        }

        if ($subscription->isExpired()) {
            return 'expired';
        }

        if ($subscription->status === AgentSubscription::STATUS_SUSPENDED) {
            return 'suspended';
        }

        if (!$subscription->price_paid || $subscription->payment_method === null) {
            return 'payment_pending';
        }

        if ($subscription->ends_at && $subscription->ends_at->diffInDays(now()) <= 7) {
            return 'expiring';
        }

        return 'active';
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
            'is_super_admin' => 'boolean',
        ];
    }
}
