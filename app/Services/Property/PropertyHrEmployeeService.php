<?php

namespace App\Services\Property;

use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\Employee;
use App\Models\PmFieldOfficer;
use App\Models\PmLease;
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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $plainPassword = Str::password(16, symbols: false);

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
