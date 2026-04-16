<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PmPermission;
use App\Models\PmRole;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Support\TabularExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminUserController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $q = trim((string) $request->string('q'));
        $role = trim((string) $request->query('role', ''));
        if (! in_array($role, ['', 'agent', 'landlord', 'tenant', 'super_admin', 'none'], true)) {
            $role = '';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $userQuery = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($role !== '', function ($query) use ($role) {
                if ($role === 'super_admin') {
                    $query->where('is_super_admin', true);
                    return;
                }
                if ($role === 'none') {
                    $query->whereNull('property_portal_role');
                    return;
                }
                $query->where('property_portal_role', $role);
            })
            ->orderByDesc('id');

        if (Schema::hasTable('user_module_accesses')) {
            $userQuery->with([
                'moduleAccesses' => fn ($q) => $q->select('id', 'user_id', 'module', 'status'),
            ]);
        }

        $users = $userQuery->paginate($perPage)->withQueryString();

        $loanRoleLabels = [
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'officer' => 'Loan officer',
            'accountant' => 'Accountant',
            'applicant' => 'Applicant',
            'user' => 'General user',
        ];

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = User::query()
                ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                }))
                ->when($role !== '', function ($query) use ($role) {
                    if ($role === 'super_admin') {
                        $query->where('is_super_admin', true);
                        return;
                    }
                    if ($role === 'none') {
                        $query->whereNull('property_portal_role');
                        return;
                    }
                    $query->where('property_portal_role', $role);
                })
                ->orderByDesc('id')
                ->limit(5000)
                ->get(['name', 'email', 'is_super_admin', 'property_portal_role', 'loan_role', 'created_at']);

            return TabularExport::stream(
                'superadmin-users-'.now()->format('Ymd_His'),
                ['Name', 'Email', 'Super admin', 'Property role', 'Loan role', 'Created'],
                function () use ($rows) {
                    foreach ($rows as $u) {
                        yield [
                            (string) $u->name,
                            (string) $u->email,
                            (bool) $u->is_super_admin ? 'Yes' : 'No',
                            (string) ($u->property_portal_role ?? '—'),
                            (string) ($u->loan_role ?? '—'),
                            optional($u->created_at)->format('Y-m-d H:i:s') ?? '',
                        ];
                    }
                },
                $export
            );
        }

        return view('superadmin.users.index', [
            'users' => $users,
            'q' => $q,
            'role' => $role,
            'perPage' => $perPage,
            'loanRoleLabels' => $loanRoleLabels,
            'hasModuleAccessTable' => Schema::hasTable('user_module_accesses'),
        ]);
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_kind' => ['required', Rule::in(['property_role', 'module_property', 'module_loan'])],
            'bulk_value' => ['required', 'string', 'max:32'],
            'ids' => ['required', 'array', 'max:300'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $actorId = $request->user()?->id;

        if ($data['bulk_kind'] === 'property_role') {
            $allowed = ['agent', 'landlord', 'tenant', 'none'];
            if (! in_array($data['bulk_value'], $allowed, true)) {
                return back()->withErrors(['bulk' => 'Invalid property portal role.']);
            }
            $portalRole = $data['bulk_value'] === 'none' ? null : $data['bulk_value'];
            $updated = User::query()->whereIn('id', $ids)->update(['property_portal_role' => $portalRole]);

            return back()->with('success', 'Updated property portal role for '.(int) $updated.' user(s).');
        }

        if (! Schema::hasTable('user_module_accesses')) {
            return back()->withErrors(['bulk' => 'Module access table is not ready.']);
        }

        $module = $data['bulk_kind'] === 'module_property' ? 'property' : 'loan';
        if (! in_array($data['bulk_value'], [
            UserModuleAccess::STATUS_APPROVED,
            UserModuleAccess::STATUS_PENDING,
            UserModuleAccess::STATUS_REVOKED,
        ], true)) {
            return back()->withErrors(['bulk' => 'Invalid module access status.']);
        }

        $status = $data['bulk_value'];
        $count = 0;

        DB::transaction(function () use ($ids, $module, $status, $actorId, &$count) {
            foreach ($ids as $userId) {
                $this->upsertModuleAccess((int) $userId, $module, $status, $actorId);
                $count++;
            }
        });

        return back()->with('success', 'Updated '.$module.' module access for '.$count.' user(s).');
    }

    public function create(): View
    {
        return view('superadmin.users.create', [
            'hasModuleAccessTable' => Schema::hasTable('user_module_accesses'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'is_super_admin' => ['nullable', 'boolean'],
            'property_portal_role' => ['nullable', 'string', Rule::in(['agent', 'landlord', 'tenant'])],
            'loan_role' => ['nullable', 'string', Rule::in(self::loanRoleOptions())],
        ];

        if (Schema::hasTable('user_module_accesses')) {
            $rules['module_property'] = ['required', Rule::in([
                UserModuleAccess::STATUS_APPROVED,
                UserModuleAccess::STATUS_PENDING,
                UserModuleAccess::STATUS_REVOKED,
            ])];
            $rules['module_loan'] = ['required', Rule::in([
                UserModuleAccess::STATUS_APPROVED,
                UserModuleAccess::STATUS_PENDING,
                UserModuleAccess::STATUS_REVOKED,
            ])];
        }

        $validated = $request->validate($rules);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_super_admin' => (bool) ($validated['is_super_admin'] ?? false),
            'property_portal_role' => $validated['property_portal_role'] ?? null,
            'loan_role' => $validated['loan_role'] ?? null,
            'email_verified_at' => now(),
        ]);

        if (Schema::hasTable('user_module_accesses')) {
            $approverId = $request->user()?->id;
            $this->upsertModuleAccess($user->id, 'property', $validated['module_property'], $approverId);
            $this->upsertModuleAccess($user->id, 'loan', $validated['module_loan'], $approverId);
        }

        $this->applyDefaultPropertyRolesOnCreate($user);

        return redirect()
            ->route('superadmin.users.edit', $user)
            ->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $moduleAccess = [
            'property' => $user->resolvedModuleAccessStatusForAdmin('property'),
            'loan' => $user->resolvedModuleAccessStatusForAdmin('loan'),
        ];

        $pmRoles = Schema::hasTable('pm_roles')
            ? PmRole::query()->orderBy('name')->get()
            : collect();

        $pmPermissions = Schema::hasTable('pm_permissions')
            ? PmPermission::query()->orderBy('group')->orderBy('name')->get()
            : collect();

        $user->loadMissing(['pmRoles:id', 'pmPermissions:id']);

        $selectedRoleIds = $user->pmRoles->pluck('id')->all();
        $selectedPermissionIds = $user->pmPermissions->pluck('id')->all();

        $suggestedPropertyPmRoleIds = $moduleAccess['property'] === UserModuleAccess::STATUS_APPROVED
            ? $this->defaultPropertyPmRoleIdsForPortalRole($user->property_portal_role)
            : [];
        $propertyPmRoleCheckboxDefaults = $selectedRoleIds !== []
            ? $selectedRoleIds
            : $suggestedPropertyPmRoleIds;

        return view('superadmin.users.edit', compact(
            'user',
            'moduleAccess',
            'pmRoles',
            'pmPermissions',
            'selectedRoleIds',
            'selectedPermissionIds',
            'propertyPmRoleCheckboxDefaults',
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'is_super_admin' => ['nullable', 'boolean'],
            'property_portal_role' => ['nullable', 'string', Rule::in(['agent', 'landlord', 'tenant'])],
            'loan_role' => ['nullable', 'string', Rule::in(self::loanRoleOptions())],

            'module_property' => ['required', Rule::in([UserModuleAccess::STATUS_APPROVED, UserModuleAccess::STATUS_PENDING, UserModuleAccess::STATUS_REVOKED])],
            'module_loan' => ['required', Rule::in([UserModuleAccess::STATUS_APPROVED, UserModuleAccess::STATUS_PENDING, UserModuleAccess::STATUS_REVOKED])],

            'pm_role_ids' => ['array'],
            'pm_role_ids.*' => ['integer'],
            'pm_permission_ids' => ['array'],
            'pm_permission_ids.*' => ['integer'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_super_admin' => (bool) ($validated['is_super_admin'] ?? false),
            'property_portal_role' => $validated['property_portal_role'] ?? null,
            'loan_role' => $validated['loan_role'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (Schema::hasTable('user_module_accesses')) {
            $this->upsertModuleAccess($user->id, 'property', $validated['module_property'], $request->user()?->id);
            $this->upsertModuleAccess($user->id, 'loan', $validated['module_loan'], $request->user()?->id);
        }

        if (Schema::hasTable('pm_roles') && Schema::hasTable('pm_user_role')) {
            $roleIds = array_values(array_unique(array_map('intval', $validated['pm_role_ids'] ?? [])));
            if ($validated['module_property'] === UserModuleAccess::STATUS_APPROVED && $roleIds === []) {
                $roleIds = $this->defaultPropertyPmRoleIdsForPortalRole($validated['property_portal_role'] ?? null);
            }
            $user->pmRoles()->sync($roleIds);
        }

        if (Schema::hasTable('pm_permissions') && Schema::hasTable('pm_user_permission')) {
            $permIds = array_values(array_unique(array_map('intval', $validated['pm_permission_ids'] ?? [])));
            $user->pmPermissions()->sync($permIds);
        }

        return back()->with('success', 'User updated.');
    }

    private function upsertModuleAccess(int $userId, string $module, string $status, ?int $approvedBy): void
    {
        $payload = [
            'status' => $status,
        ];

        if ($status === UserModuleAccess::STATUS_APPROVED) {
            $payload['approved_by'] = $approvedBy;
            $payload['approved_at'] = now();
        }

        UserModuleAccess::query()->updateOrCreate(
            ['user_id' => $userId, 'module' => $module],
            $payload,
        );
    }

    /**
     * @return list<string>
     */
    private static function loanRoleOptions(): array
    {
        return ['admin', 'officer', 'manager', 'applicant', 'accountant', 'user'];
    }

    /**
     * Default Property RBAC role slugs per portal role (common operational bundle for agents).
     *
     * @return list<string>
     */
    private function defaultPropertyPmRoleSlugsForPortalRole(?string $portalRole): array
    {
        $portalRole = $portalRole !== null ? strtolower(trim($portalRole)) : '';

        return match ($portalRole) {
            'agent' => ['property_manager', 'leasing_officer', 'finance_clerk'],
            'landlord' => ['landlord_portal_user'],
            'tenant' => ['tenant_portal_user'],
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    private function defaultPropertyPmRoleIdsForPortalRole(?string $portalRole): array
    {
        if (! Schema::hasTable('pm_roles')) {
            return [];
        }

        $slugs = $this->defaultPropertyPmRoleSlugsForPortalRole($portalRole);
        if ($slugs === []) {
            return [];
        }

        $ids = PmRole::query()->whereIn('slug', $slugs)->pluck('id')->all();

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Assign default Property RBAC roles when a portal role is set. Module approval is set only via the form.
     */
    private function applyDefaultPropertyRolesOnCreate(User $user): void
    {
        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_user_role')) {
            return;
        }

        $ids = $this->defaultPropertyPmRoleIdsForPortalRole($user->property_portal_role);
        if ($ids !== []) {
            $user->pmRoles()->syncWithoutDetaching($ids);
        }
    }
}

