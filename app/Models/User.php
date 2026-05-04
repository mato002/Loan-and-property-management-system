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
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'profile_photo_path', 'property_portal_role', 'loan_role', 'is_super_admin'])]
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

    public function loanTemporaryAccessRequests(): HasMany
    {
        return $this->hasMany(LoanTemporaryAccessRequest::class, 'requester_user_id');
    }

    public function loanAccessRoles(): BelongsToMany
    {
        return $this->belongsToMany(LoanRole::class, 'loan_user_role')->withTimestamps();
    }

    public function activeLoanAccessRole(): ?LoanRole
    {
        if (! Schema::hasTable('loan_roles') || ! Schema::hasTable('loan_user_role')) {
            return null;
        }

        return $this->loanAccessRoles()
            ->where('is_active', true)
            ->orderBy('loan_roles.id')
            ->first();
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

        $modules = [];
        if ($this->isModuleApproved('property')) {
            $modules[] = 'property';
        }
        if ($this->isModuleApproved('loan')) {
            $modules[] = 'loan';
        }

        return $modules;
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

        $access = $this->moduleAccesses()->where('module', $module)->first();

        if ($access && $access->status === UserModuleAccess::STATUS_REVOKED) {
            return false;
        }

        if ($access && $access->status === UserModuleAccess::STATUS_APPROVED) {
            return true;
        }

        // Row missing or still "pending": match real access for users created before module rows
        // were synced (or rows defaulted to pending). Only "revoked" blocks outright above.
        return match ($module) {
            'property' => (bool) ($this->property_portal_role ?? null),
            'loan' => trim((string) ($this->loan_role ?? '')) !== ''
                || ! (bool) ($this->property_portal_role ?? null),
            default => false,
        };
    }

    public function effectiveLoanRole(): string
    {
        $explicit = strtolower(trim((string) ($this->loan_role ?? '')));
        if (in_array($explicit, ['admin', 'officer', 'manager', 'applicant', 'accountant', 'user'], true)) {
            return $explicit;
        }

        if (Schema::hasTable('loan_roles') && Schema::hasTable('loan_user_role')) {
            $assignedBaseRole = strtolower(trim((string) ($this->loanAccessRoles()
                ->where('is_active', true)
                ->value('base_role') ?? '')));
            if (in_array($assignedBaseRole, ['admin', 'officer', 'manager', 'applicant', 'accountant', 'user'], true)) {
                return $assignedBaseRole;
            }
        }

        $email = strtolower(trim((string) ($this->email ?? '')));
        if ($email === '' || ! Schema::hasTable('employees')) {
            return '';
        }

        $jobTitle = trim((string) (Employee::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->value('job_title') ?? ''));
        if ($jobTitle === '') {
            return '';
        }

        // Prefer explicit setup mapping (job title code => loan role key).
        if (Schema::hasTable('loan_job_titles')) {
            $mapped = strtolower(trim((string) (LoanJobTitle::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [strtolower($jobTitle)])
                ->value('code') ?? '')));
            if (in_array($mapped, ['admin', 'officer', 'manager', 'applicant', 'accountant', 'user'], true)) {
                return $mapped;
            }
        }

        $t = strtolower($jobTitle);

        return match (true) {
            str_contains($t, 'admin') => 'admin',
            str_contains($t, 'manager') || str_contains($t, 'lead') || str_contains($t, 'supervisor') => 'manager',
            str_contains($t, 'account') || str_contains($t, 'finance') => 'accountant',
            str_contains($t, 'loan officer'),
            str_contains($t, 'officer'),
            str_contains($t, 'credit'),
            str_contains($t, 'collector') => 'officer',
            str_contains($t, 'applicant'),
            str_contains($t, 'customer care') => 'applicant',
            default => 'user',
        };
    }

    /**
     * @return list<string>
     */
    public function loanPermissionKeys(): array
    {
        if (! Schema::hasTable('loan_roles') || ! Schema::hasTable('loan_user_role')) {
            return [];
        }

        $role = $this->activeLoanAccessRole();
        if (! $role) {
            return [];
        }

        $raw = $role->permissions;
        $items = is_array($raw)
            ? $raw
            : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

        if (! is_array($items)) {
            return [];
        }

        $flat = [];
        foreach ($items as $key) {
            $flat[] = strtolower(trim((string) $key));
        }

        return array_values(array_unique(array_filter($flat, fn ($k) => $k !== '')));
    }

    public function hasLoanPermission(string $permissionKey): bool
    {
        if (($this->is_super_admin ?? false) === true) {
            return true;
        }

        $permissionKey = strtolower(trim($permissionKey));
        if ($permissionKey === '') {
            return false;
        }

        $assignedRole = $this->activeLoanAccessRole();
        $keys = $this->loanPermissionKeys();
        if ($keys !== []) {
            if (in_array('*', $keys, true) || in_array($permissionKey, $keys, true)) {
                return true;
            }
            foreach ($this->loanPermissionLegacyFallbacks($permissionKey) as $legacyKey) {
                if (in_array($legacyKey, $keys, true)) {
                    return true;
                }
            }

            return false;
        }
        if ($assignedRole !== null) {
            return $this->hasActiveTemporaryLoanPermission($permissionKey);
        }

        $role = $this->effectiveLoanRole();
        $defaultByRole = $this->defaultLoanPermissionsByRole();
        $allowed = $defaultByRole[$role] ?? [];

        if (in_array('*', $allowed, true) || in_array($permissionKey, $allowed, true)) {
            return true;
        }
        foreach ($this->loanPermissionLegacyFallbacks($permissionKey) as $legacyKey) {
            if (in_array($legacyKey, $allowed, true)) {
                return true;
            }
        }

        return $this->hasActiveTemporaryLoanPermission($permissionKey);
    }

    private function hasActiveTemporaryLoanPermission(string $permissionKey): bool
    {
        if (! Schema::hasTable('loan_temporary_access_requests')) {
            return false;
        }

        $now = now();
        $active = LoanTemporaryAccessRequest::query()
            ->where('requester_user_id', $this->id)
            ->where('status', LoanTemporaryAccessRequest::STATUS_APPROVED)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->pluck('permission_key')
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->values()
            ->all();

        if (in_array('*', $active, true) || in_array($permissionKey, $active, true)) {
            return true;
        }

        foreach ($this->loanPermissionLegacyFallbacks($permissionKey) as $legacyKey) {
            if (in_array($legacyKey, $active, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultLoanPermissionsByRole(): array
    {
        return [
            'admin' => ['*'],
            'manager' => ['*'],
            'accountant' => [
                'dashboard.view',
                'clients.view',
                'payments.view',
                'accounting.view',
                'financial.view',
                'reports.view',
                'system.help.view',
                'journals.view',
                'journals.create',
                'journals.update',
                'journals.approve',
                'chart_of_accounts.view',
                'audit_logs.view',
                'wallets.view',
                'wallets.pay_loan',
                'wallets.refund_request',
                'wallets.refund_approve',
                'wallets.freeze',
            ],
            'officer' => [
                'dashboard.view',
                'clients.view',
                'wallets.view',
                'wallets.pay_loan',
                'wallets.refund_request',
                'clients.create',
                'clients.update',
                'loan_applications.view',
                'loan_applications.create',
                'loan_applications.update',
                'loans.view',
                'loans.create',
                'loans.update',
                'disbursements.view',
                'collections.view',
                'collections.create',
                'collections.update',
                'payments.view',
                'reports.view',
                'my_account.view',
                'system.help.view',
            ],
            'user' => [
                'dashboard.view',
                'clients.view',
                'loan_applications.view',
                'loan_applications.create',
                'loans.view',
                'collections.view',
                'payments.view',
                'my_account.view',
                'system.help.view',
            ],
            'applicant' => ['dashboard.view', 'my_account.view', 'system.help.view'],
        ];
    }

    /**
     * Transitional compatibility map from granular permission to legacy coarse keys.
     *
     * @return list<string>
     */
    private function loanPermissionLegacyFallbacks(string $permissionKey): array
    {
        $map = [
            'wallets.view' => ['clients.view', 'financial.view', 'payments.view'],
            'wallets.pay_loan' => ['payments.view', 'clients.view'],
            'wallets.refund_request' => ['clients.view', 'payments.view'],
            'wallets.refund_approve' => ['financial.view', 'accounting.view', 'journals.approve'],
            'wallets.freeze' => ['financial.view', 'clients.view'],
            'wallets.adjust' => ['financial.view', 'accounting.view'],
            'dashboard.view' => ['dashboard.view'],
            'employees.view' => ['employees.view'],
            'branches.view' => ['branches.view'],
            'analytics.view' => ['analytics.view'],
            'bulksms.view' => ['bulksms.view'],
            'my_account.view' => ['my_account.view'],
            'system.help.view' => ['system.help.view'],
        ];

        if (isset($map[$permissionKey])) {
            return $map[$permissionKey];
        }

        $prefixMap = [
            'wallets.' => ['clients.view', 'payments.view', 'financial.view'],
            'clients.' => ['clients.view'],
            'loan_applications.' => ['loanbook.view'],
            'loans.' => ['loanbook.view'],
            'disbursements.' => ['loanbook.view', 'payments.view'],
            'collections.' => ['payments.view'],
            'payments.' => ['payments.view'],
            'wallets.' => ['payments.view', 'financial.view'],
            'accounting.' => ['accounting.view', 'financial.view'],
            'journals.' => ['accounting.view', 'financial.view'],
            'chart_of_accounts.' => ['accounting.view', 'financial.view'],
            'automated_cash_mappings.' => ['accounting.view', 'financial.view'],
            'reports.' => ['analytics.view', 'financial.view'],
            'system_setup.' => ['system.help.view'],
            'audit_logs.' => ['system.help.view'],
            'access_roles.' => ['system.help.view'],
        ];

        foreach ($prefixMap as $prefix => $legacyKeys) {
            if (str_starts_with($permissionKey, $prefix)) {
                return $legacyKeys;
            }
        }

        return [];
    }

    /**
     * Status shown on Super Admin user edit (aligned with {@see self::isModuleApproved()}).
     */
    public function resolvedModuleAccessStatusForAdmin(string $module): string
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return $this->isModuleApproved($module)
                ? UserModuleAccess::STATUS_APPROVED
                : UserModuleAccess::STATUS_PENDING;
        }

        $stored = $this->moduleAccesses()->where('module', $module)->value('status');

        if ($stored === UserModuleAccess::STATUS_REVOKED) {
            return UserModuleAccess::STATUS_REVOKED;
        }

        return $this->isModuleApproved($module)
            ? UserModuleAccess::STATUS_APPROVED
            : UserModuleAccess::STATUS_PENDING;
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

        if (! $subscription) {
            return 'none';
        }

        if ($subscription->isExpired()) {
            return 'expired';
        }

        if ($subscription->status === AgentSubscription::STATUS_SUSPENDED) {
            return 'suspended';
        }

        if (! $subscription->price_paid || $subscription->payment_method === null) {
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

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $raw = trim((string) ($this->profile_photo_path ?? ''));
        if ($raw === '') {
            return null;
        }

        if (Str::startsWith($raw, ['http://', 'https://', '/'])) {
            return $raw;
        }

        // Use a relative URL so the image works regardless of APP_URL host/port.
        if (Str::startsWith($raw, 'storage/')) {
            return '/'.ltrim($raw, '/');
        }

        return '/storage/'.ltrim($raw, '/');
    }
}
