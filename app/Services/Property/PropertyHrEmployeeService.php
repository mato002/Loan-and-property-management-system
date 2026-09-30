<?php

namespace App\Services\Property;

use App\Mail\PropertyStaffCredentialsMail;
use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\Employee;
use App\Models\PmFieldOfficer;
use App\Models\PmLease;
use App\Models\PmMessageLog;
use App\Models\PmRole;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PropertyHrEmployeeService
{
    public const DEPARTMENTS = [
        'Property Management',
        'Leasing',
        'Maintenance',
        'Finance',
        'Administration',
    ];

    public const JOB_TITLES = [
        'Field Officer',
        'Property Manager',
        'Leasing Officer',
        'Maintenance Officer',
        'Accountant',
        'Finance Clerk',
        'Office Administrator',
        'General Staff',
    ];

    public const FIELD_OFFICER_JOB_TITLE = 'Field Officer';

    public const EMPLOYMENT_STATUSES = Employee::STATUSES;

    public const EXIT_REASONS = Employee::EXIT_REASONS;

    public const LEAVE_TYPES = [
        'Annual leave',
        'Sick leave',
        'Compassionate leave',
        'Unpaid leave',
        'Maternity / paternity',
    ];

    /**
     * @return list<int>
     */
    public function employeeIdsForActor(?User $user = null): array
    {
        return $this->queryForActor($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function propertyRolesForForm(): Collection
    {
        if (! Schema::hasTable('pm_roles')) {
            return collect();
        }

        return PmRole::query()
            ->whereIn('portal_scope', ['agent', 'any'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->unique('id')
            ->values();
    }

    /**
     * @param  list<int>  $roleIds
     * @return array{user: User, plain_password: string}
     */
    public function provisionPropertyLogin(Employee $employee, array $roleIds, User $actor): array
    {
        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_user_role')) {
            throw ValidationException::withMessages([
                'provision_login' => 'Property roles are not configured yet. Run migrations and set up access control.',
            ]);
        }

        $email = Str::lower(trim((string) ($employee->email ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Work email is required to create a property portal login.',
            ]);
        }

        if ($employee->user_id) {
            throw ValidationException::withMessages([
                'provision_login' => 'This employee already has a linked portal user.',
            ]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user account with this email already exists.',
            ]);
        }

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            throw ValidationException::withMessages([
                'role_ids' => 'Select at least one property role for portal access.',
            ]);
        }

        $matched = PmRole::query()
            ->whereIn('portal_scope', ['agent', 'any'])
            ->whereIn('id', $roleIds)
            ->count();

        if ($matched !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => 'One or more selected roles are not valid for property staff.',
            ]);
        }

        $plainPassword = Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($employee, $email, $plainPassword, $roleIds, $actor) {
            $payload = [
                'name' => $employee->full_name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'agent',
                'email_verified_at' => now(),
            ];

            if (Schema::hasColumn('users', 'phone') && filled($employee->phone)) {
                $payload['phone'] = $employee->phone;
            }

            $user = User::query()->create($payload);

            if (Schema::hasTable('user_module_accesses')) {
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'property'],
                    [
                        'status' => UserModuleAccess::STATUS_APPROVED,
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ],
                );
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'loan'],
                    ['status' => UserModuleAccess::STATUS_REVOKED],
                );
            }

            $user->pmRoles()->sync($roleIds);
            $employee->update(['user_id' => $user->id]);

            return $user;
        });

        return ['user' => $user, 'plain_password' => $plainPassword];
    }

    /**
     * Create or reset a property login and email the temporary password.
     *
     * @param  list<int>  $roleIds
     * @return array{user: User, plain_password: string, mailed: bool, created: bool, mail_error: ?string}
     */
    public function issueLoginAndEmail(Employee $employee, User $actor, array $roleIds = []): array
    {
        $employee->loadMissing('user.pmRoles');
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if ($roleIds === []) {
            $roleIds = $employee->user?->pmRoles?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];
        }
        if ($roleIds === []) {
            $roleIds = $this->defaultRoleIdsForEmployee($employee);
        }

        $created = false;
        if ($employee->user_id && $employee->user) {
            $result = $this->resetLinkedLogin($employee, $roleIds);
        } else {
            $existing = $this->existingUserForEmployeeEmail($employee);
            if ($existing) {
                $result = $this->linkAndResetExistingUser($employee, $existing, $roleIds, $actor);
            } else {
                $result = $this->provisionPropertyLogin($employee->fresh(), $roleIds, $actor);
                $created = true;
            }
        }

        $delivery = $this->sendLoginEmail($employee->fresh(), $result['user']->loadMissing('pmRoles'), $result['plain_password']);

        return [
            'user' => $result['user'],
            'plain_password' => $result['plain_password'],
            'mailed' => $delivery['mailed'],
            'created' => $created,
            'mail_error' => $delivery['error'],
        ];
    }

    /**
     * @param  list<int>  $roleIds
     * @return array{user: User, plain_password: string}
     */
    public function resetLinkedLogin(Employee $employee, array $roleIds = []): array
    {
        $user = $employee->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'provision_login' => 'This employee has no linked portal user yet.',
            ]);
        }

        $plainPassword = Str::password(12, symbols: false);
        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'property_portal_role' => $user->property_portal_role ?: 'agent',
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($roleIds !== [] && Schema::hasTable('pm_user_role')) {
            $user->pmRoles()->sync($roleIds);
        }

        $this->approvePropertyModule($user);

        return ['user' => $user->fresh(['pmRoles']), 'plain_password' => $plainPassword];
    }

    /**
     * @return list<int>
     */
    public function defaultRoleIdsForEmployee(Employee $employee): array
    {
        $roles = $this->propertyRolesForForm();
        if ($roles->isEmpty()) {
            return [];
        }

        $prefer = [];
        if ($this->isFieldOfficerEmployee($employee)) {
            $prefer = ['field-officer', 'field_officer', 'officer'];
        } else {
            $title = Str::slug((string) $employee->job_title);
            if ($title !== '') {
                $prefer[] = $title;
            }
            $prefer[] = 'staff';
            $prefer[] = 'agent';
        }

        foreach ($prefer as $needle) {
            $match = $roles->first(function (PmRole $role) use ($needle) {
                return str_contains(Str::slug($role->slug), $needle)
                    || str_contains(Str::slug($role->name), $needle);
            });
            if ($match) {
                return [(int) $match->id];
            }
        }

        return [(int) $roles->first()->id];
    }

    public function revokePortalAccess(Employee $employee): void
    {
        $user = $employee->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'provision_login' => 'This employee has no portal login to revoke.',
            ]);
        }

        if (Schema::hasTable('user_module_accesses')) {
            UserModuleAccess::query()->updateOrCreate(
                ['user_id' => $user->id, 'module' => 'property'],
                ['status' => UserModuleAccess::STATUS_REVOKED],
            );
        }

        if ($employee->fieldOfficerProfile) {
            $employee->fieldOfficerProfile->update(['portal_access' => false]);
        }
    }

    public function restorePortalAccess(Employee $employee, User $actor): void
    {
        $user = $employee->user;
        if (! $user) {
            throw ValidationException::withMessages([
                'provision_login' => 'This employee has no portal login to restore.',
            ]);
        }

        $this->approvePropertyModule($user, $actor);

        if ($employee->fieldOfficerProfile) {
            $employee->fieldOfficerProfile->update(['portal_access' => true]);
        }
    }

    public function setEmploymentStatus(Employee $employee, string $status): Employee
    {
        $status = Str::lower(trim($status));
        if (! array_key_exists($status, Employee::STATUSES)) {
            throw ValidationException::withMessages([
                'employment_status' => 'Choose onboarding, active, on leave, or offboarded.',
            ]);
        }

        if ($status === 'terminated') {
            return $this->offboardEmployee($employee, [
                'exit_date' => now()->toDateString(),
                'exit_reason' => 'other',
                'offboarding_notes' => null,
                'unassign_properties' => true,
                'revoke_portal' => true,
            ], Auth::user());
        }

        $payload = ['employment_status' => $status];

        if ($status === 'active') {
            $payload['onboarding_completed_at'] = $employee->onboarding_completed_at ?? now();
            $payload['exit_date'] = null;
            $payload['exit_reason'] = null;
            $payload['offboarding_notes'] = null;
            $payload['offboarded_by_user_id'] = null;
            if (! $employee->hire_date) {
                $payload['hire_date'] = now()->toDateString();
            }
        }

        $employee->update($payload);

        return $employee->fresh();
    }

    public function completeOnboarding(Employee $employee): Employee
    {
        if ($employee->isOffboarded()) {
            throw ValidationException::withMessages([
                'employment_status' => 'Re-activate this employee before completing onboarding.',
            ]);
        }

        $payload = [
            'employment_status' => 'active',
            'onboarding_completed_at' => now(),
        ];
        if (! $employee->hire_date) {
            $payload['hire_date'] = now()->toDateString();
        }

        $employee->update($payload);

        return $employee->fresh();
    }

    /**
     * @return list<array{key: string, label: string, done: bool}>
     */
    public function onboardingChecklist(Employee $employee): array
    {
        $hasContact = trim((string) $employee->email) !== '' || trim((string) $employee->phone) !== '';

        return [
            ['key' => 'identity', 'label' => 'National ID on file', 'done' => trim((string) $employee->national_id) !== ''],
            ['key' => 'contact', 'label' => 'Work email or phone', 'done' => $hasContact],
            ['key' => 'role', 'label' => 'Department and job title', 'done' => trim((string) $employee->department) !== '' && trim((string) $employee->job_title) !== ''],
            ['key' => 'hire', 'label' => 'Hire date', 'done' => $employee->hire_date !== null],
            ['key' => 'kin', 'label' => 'Next of kin', 'done' => trim((string) $employee->next_of_kin_name) !== ''],
            ['key' => 'payroll', 'label' => 'Bank account for payroll', 'done' => trim((string) $employee->bank_name) !== '' && trim((string) $employee->bank_account_number) !== ''],
            ['key' => 'login', 'label' => 'Portal login (optional)', 'done' => (bool) $employee->user_id],
        ];
    }

    /**
     * @param  array{
     *     exit_date?: mixed,
     *     exit_reason?: mixed,
     *     offboarding_notes?: mixed,
     *     unassign_properties?: mixed,
     *     revoke_portal?: mixed
     * }  $data
     */
    public function offboardEmployee(Employee $employee, array $data, ?User $actor = null): Employee
    {
        $exitDate = $data['exit_date'] ?? now()->toDateString();
        $reason = Str::lower(trim((string) ($data['exit_reason'] ?? 'other')));
        if (! array_key_exists($reason, Employee::EXIT_REASONS)) {
            $reason = 'other';
        }

        $employee->update([
            'employment_status' => 'terminated',
            'exit_date' => $exitDate,
            'exit_reason' => $reason,
            'offboarding_notes' => trim((string) ($data['offboarding_notes'] ?? '')) ?: null,
            'offboarded_by_user_id' => $actor?->id,
        ]);

        if (! empty($data['unassign_properties'])) {
            $this->unassignAllPropertiesFromEmployee($employee->fresh());
        }

        $employee = $employee->fresh();
        if (! empty($data['revoke_portal']) && $employee?->user) {
            $this->revokePortalAccess($employee);
        }

        return $employee->fresh();
    }

    public function unassignAllPropertiesFromEmployee(Employee $employee): int
    {
        $fieldOfficer = $this->resolveFieldOfficerForEmployee($employee);
        if (! $fieldOfficer) {
            return 0;
        }

        return Property::query()
            ->where('field_officer_id', $fieldOfficer->id)
            ->update(['field_officer_id' => null]);
    }

    /**
     * @return array{mailed: bool, error: ?string}
     */
    public function sendLoginEmail(Employee $employee, User $user, string $plainPassword): array
    {
        $role = $user->pmRoles->pluck('name')->filter()->join(', ') ?: ($employee->job_title ?: 'Staff');
        $subject = __('Your property workspace login');
        $logBody = __('Staff login credentials emailed to :name (:role). Temporary password omitted from this log.', [
            'name' => $employee->full_name,
            'role' => $role,
        ]);
        $actorId = $employee->agent_user_id ?: Auth::id();
        $actorId = $actorId ? (int) $actorId : null;

        try {
            Mail::to($user->email)->send(new PropertyStaffCredentialsMail(
                employeeName: $employee->full_name,
                role: $role,
                email: $user->email,
                plainPassword: $plainPassword,
                loginUrl: route('login'),
                workspaceUrl: route('property.dashboard'),
            ));

            $this->logOutboundEmail(
                toAddress: (string) $user->email,
                subject: $subject,
                body: $logBody,
                userId: $actorId ? (int) $actorId : null,
                deliveryStatus: 'sent',
            );

            return ['mailed' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::error('property_staff_credentials_mail_failed', [
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            $this->logOutboundEmail(
                toAddress: (string) $user->email,
                subject: $subject,
                body: $logBody,
                userId: $actorId ? (int) $actorId : null,
                deliveryStatus: 'failed',
                deliveryError: $e->getMessage(),
            );

            return ['mailed' => false, 'error' => $e->getMessage()];
        }
    }

    private function logOutboundEmail(
        string $toAddress,
        string $subject,
        string $body,
        ?int $userId,
        string $deliveryStatus,
        ?string $deliveryError = null,
    ): void {
        if (! Schema::hasTable('pm_message_logs') || $toAddress === '') {
            return;
        }

        try {
            PmMessageLog::query()->create([
                'user_id' => $userId,
                'channel' => 'email',
                'to_address' => $toAddress,
                'subject' => $subject,
                'body' => $body,
                'delivery_status' => $deliveryStatus,
                'delivery_error' => $deliveryError,
                'sent_at' => $deliveryStatus === 'sent' ? now() : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('property_outbound_email_log_failed', [
                'to' => $toAddress,
                'subject' => $subject,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function existingUserForEmployeeEmail(Employee $employee): ?User
    {
        $email = Str::lower(trim((string) ($employee->email ?? '')));
        if ($email === '') {
            return null;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    /**
     * @param  list<int>  $roleIds
     * @return array{user: User, plain_password: string}
     */
    private function linkAndResetExistingUser(Employee $employee, User $user, array $roleIds, User $actor): array
    {
        $taken = Employee::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $employee->id)
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'email' => 'This email already belongs to another employee login.',
            ]);
        }

        $portal = (string) ($user->property_portal_role ?? '');
        if (in_array($portal, ['landlord', 'tenant'], true)) {
            throw ValidationException::withMessages([
                'email' => 'This email is already used by a landlord or tenant portal account.',
            ]);
        }

        $plainPassword = Str::password(12, symbols: false);
        DB::transaction(function () use ($employee, $user, $plainPassword, $roleIds, $actor): void {
            $user->forceFill([
                'name' => $employee->full_name,
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'agent',
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if ($roleIds !== []) {
                $user->pmRoles()->sync($roleIds);
            }

            $this->approvePropertyModule($user, $actor);
            $employee->update(['user_id' => $user->id]);
        });

        return ['user' => $user->fresh(['pmRoles']), 'plain_password' => $plainPassword];
    }

    private function approvePropertyModule(User $user, ?User $actor = null): void
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return;
        }

        UserModuleAccess::query()->updateOrCreate(
            ['user_id' => $user->id, 'module' => 'property'],
            [
                'status' => UserModuleAccess::STATUS_APPROVED,
                'approved_by' => $actor?->id,
                'approved_at' => now(),
            ],
        );
    }

    public function queryForActor(?User $user = null): Builder
    {
        $user ??= Auth::user();

        return Employee::query()
            ->when(
                $this->shouldScopeToAgent($user),
                fn (Builder $q) => $q->where(function (Builder $inner) use ($user) {
                    $inner->where('agent_user_id', (int) $user->id)
                        ->orWhereNull('agent_user_id');
                })
            )
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    public function resolveAgentUserIdForStore(Request $request): int
    {
        if (AgentWorkspaceScope::shouldApply()) {
            return (int) $request->user()->id;
        }

        return (int) $request->input('agent_user_id', $request->user()->id);
    }

    public function generateNextEmployeeNumber(): string
    {
        $maxNumeric = 1000;

        foreach (Employee::query()->pluck('employee_number') as $employeeNumber) {
            if (preg_match('/(\d+)$/', (string) $employeeNumber, $matches) === 1) {
                $maxNumeric = max($maxNumeric, (int) $matches[1]);
            }
        }

        $next = $maxNumeric + 1;
        do {
            $candidate = 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $exists = Employee::query()->where('employee_number', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $employeeData
     */
    public function syncFieldOfficerFromEmployee(Employee $employee, bool $isFieldOfficer, bool $portalAccess = false): ?PmFieldOfficer
    {
        if (! $isFieldOfficer && ! $this->isFieldOfficerJobTitle($employee->job_title)) {
            $existing = $employee->fieldOfficerProfile;
            if ($existing) {
                $existing->update(['employee_id' => null]);
            }

            return null;
        }

        $agentUserId = (int) ($employee->agent_user_id ?? 0);
        if ($agentUserId <= 0) {
            return null;
        }

        $officer = PmFieldOfficer::query()
            ->where('employee_id', $employee->id)
            ->first();

        if (! $officer) {
            $officer = PmFieldOfficer::query()
                ->where('agent_user_id', $agentUserId)
                ->where('name', $employee->full_name)
                ->first();
        }

        $payload = [
            'agent_user_id' => $agentUserId,
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'phone' => $employee->phone,
            'portal_access' => $portalAccess,
            'user_id' => $employee->user_id,
        ];

        if ($officer) {
            $officer->update($payload);

            return $officer->fresh();
        }

        return PmFieldOfficer::query()->create($payload);
    }

    public function ensureEmployeeForFieldOfficer(PmFieldOfficer $officer): Employee
    {
        if ($officer->employee_id) {
            $employee = Employee::query()->find($officer->employee_id);
            if ($employee) {
                return $employee;
            }
        }

        $parts = preg_split('/\s+/', trim((string) $officer->name), 2) ?: [];
        $firstName = (string) ($parts[0] ?? 'Field');
        $lastName = (string) ($parts[1] ?? 'Officer');

        $employee = Employee::query()->create([
            'agent_user_id' => $officer->agent_user_id,
            'employee_number' => $this->generateNextEmployeeNumber(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $officer->phone,
            'department' => 'Property Management',
            'job_title' => self::FIELD_OFFICER_JOB_TITLE,
            'employment_status' => 'active',
            'hire_date' => now()->toDateString(),
        ]);

        $officer->update(['employee_id' => $employee->id]);

        return $employee;
    }

    public function backfillFieldOfficerEmployees(): int
    {
        $count = 0;

        PmFieldOfficer::query()
            ->whereNull('employee_id')
            ->orderBy('id')
            ->each(function (PmFieldOfficer $officer) use (&$count): void {
                $this->ensureEmployeeForFieldOfficer($officer);
                $count++;
            });

        return $count;
    }

    public function isFieldOfficerJobTitle(?string $jobTitle): bool
    {
        return Str::lower(trim((string) $jobTitle)) === Str::lower(self::FIELD_OFFICER_JOB_TITLE);
    }

    public function isFieldOfficerEmployee(Employee $employee): bool
    {
        return $this->isFieldOfficerJobTitle($employee->job_title)
            || $employee->fieldOfficerProfile()->exists();
    }

    public function resolveFieldOfficerForEmployee(Employee $employee): ?PmFieldOfficer
    {
        $employee->loadMissing('fieldOfficerProfile');

        if ($employee->fieldOfficerProfile) {
            return $employee->fieldOfficerProfile;
        }

        if (! $this->isFieldOfficerEmployee($employee)) {
            return null;
        }

        return $this->syncFieldOfficerFromEmployee($employee, true, false);
    }

    public function assignPropertyToEmployee(Employee $employee, int $propertyId): Property
    {
        $fieldOfficer = $this->resolveFieldOfficerForEmployee($employee);
        if (! $fieldOfficer) {
            throw ValidationException::withMessages([
                'property_id' => 'Enable the field officer role on this employee before assigning properties.',
            ]);
        }

        $property = Property::query()->findOrFail($propertyId);
        $this->assertPropertyAssignableToOfficer($property, $fieldOfficer);

        if ((int) $property->field_officer_id === (int) $fieldOfficer->id) {
            return $property;
        }

        if ($property->field_officer_id !== null) {
            throw ValidationException::withMessages([
                'property_id' => 'Property is already assigned to another field officer. Unassign it first.',
            ]);
        }

        $property->update(['field_officer_id' => $fieldOfficer->id]);

        return $property->fresh();
    }

    public function detachPropertyFromEmployee(Employee $employee, int $propertyId): Property
    {
        $fieldOfficer = $this->resolveFieldOfficerForEmployee($employee);
        if (! $fieldOfficer) {
            throw ValidationException::withMessages([
                'property_id' => 'This employee is not a field officer.',
            ]);
        }

        $property = Property::query()->findOrFail($propertyId);

        if ((int) $property->field_officer_id !== (int) $fieldOfficer->id) {
            throw ValidationException::withMessages([
                'property_id' => 'This property is not assigned to this employee.',
            ]);
        }

        $property->update(['field_officer_id' => null]);

        return $property->fresh();
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     city: string,
     *     units: int,
     *     tenants: int,
     *     rent: float,
     *     show_url: string
     * }>
     */
    public function assignedPropertyRows(PmFieldOfficer $fieldOfficer): array
    {
        $properties = $fieldOfficer->properties()
            ->operational()
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        if ($properties->isEmpty()) {
            return [];
        }

        $propertyIds = $properties->pluck('id');

        $unitCounts = PropertyUnit::query()
            ->withoutGlobalScopes()
            ->whereIn('property_id', $propertyIds)
            ->selectRaw('property_id, COUNT(*) as cnt')
            ->groupBy('property_id')
            ->pluck('cnt', 'property_id');

        $tenantStats = DB::table('pm_leases as l')
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as u', 'u.id', '=', 'lu.property_unit_id')
            ->whereIn('u.property_id', $propertyIds)
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->selectRaw('u.property_id, COUNT(DISTINCT l.pm_tenant_id) as tenants, COALESCE(SUM(l.monthly_rent), 0) as rent')
            ->groupBy('u.property_id')
            ->get()
            ->keyBy('property_id');

        $rows = [];
        foreach ($properties as $property) {
            $stat = $tenantStats->get($property->id);
            $rows[] = [
                'id' => (int) $property->id,
                'name' => (string) $property->name,
                'city' => (string) ($property->city ?: '—'),
                'units' => (int) ($unitCounts[$property->id] ?? 0),
                'tenants' => (int) ($stat->tenants ?? 0),
                'rent' => (float) ($stat->rent ?? 0),
                'show_url' => route('property.properties.show', ['property' => $property->id], false),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{id: int, name: string, city: string}>
     */
    public function unassignedPropertiesForOfficer(PmFieldOfficer $fieldOfficer): array
    {
        return Property::query()
            ->operational()
            ->where('agent_user_id', $fieldOfficer->agent_user_id)
            ->whereNull('field_officer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'city'])
            ->map(fn (Property $p) => [
                'id' => (int) $p->id,
                'name' => (string) $p->name,
                'city' => (string) ($p->city ?: '—'),
            ])
            ->values()
            ->all();
    }

    /**
     * Dropdown options for assigning a field officer on property forms.
     *
     * @return list<array{id: int, label: string, employee_id: int|null}>
     */
    public function fieldOfficerSelectOptions(int $agentUserId): array
    {
        return PmFieldOfficer::query()
            ->where('agent_user_id', $agentUserId)
            ->with('employee:id,first_name,last_name,employee_number,employment_status')
            ->orderBy('name')
            ->get()
            ->map(function (PmFieldOfficer $officer): array {
                $employee = $officer->employee;
                $label = $employee?->full_name ?: (string) $officer->name;

                if ($employee?->employee_number) {
                    $label .= ' ('.$employee->employee_number.')';
                }

                $status = trim((string) ($employee?->employment_status ?? ''));
                if ($status !== '' && $status !== 'active') {
                    $label .= ' — '.ucfirst($status);
                }

                return [
                    'id' => (int) $officer->id,
                    'label' => $label,
                    'employee_id' => $employee ? (int) $employee->id : null,
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function assertPropertyAssignableToOfficer(Property $property, PmFieldOfficer $fieldOfficer): void
    {
        if ((int) $property->agent_user_id !== (int) $fieldOfficer->agent_user_id) {
            throw ValidationException::withMessages([
                'property_id' => 'Property must belong to the same agent workspace as the employee.',
            ]);
        }
    }

    private function shouldScopeToAgent(?User $user): bool
    {
        if (! $user || $user->is_super_admin) {
            return false;
        }

        if (! Schema::hasColumn('employees', 'agent_user_id')) {
            return false;
        }

        return $user->property_portal_role === 'agent';
    }
}
