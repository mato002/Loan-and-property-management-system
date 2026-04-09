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
use App\Support\TabularExport;

class SuperAdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));
        $role = trim((string) $request->query('role', ''));
        if (! in_array($role, ['', 'agent', 'landlord', 'tenant', 'super_admin', 'none'], true)) {
            $role = '';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 20)));

        $users = User::query()
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
            ->paginate($perPage)
            ->withQueryString();

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
                ->get(['name', 'email', 'is_super_admin', 'property_portal_role', 'created_at']);

            return TabularExport::stream(
                'superadmin-users-'.now()->format('Ymd_His'),
                ['Name', 'Email', 'Super admin', 'Property role', 'Created'],
                function () use ($rows) {
                    foreach ($rows as $u) {
                        yield [
                            (string) $u->name,
                            (string) $u->email,
                            (bool) $u->is_super_admin ? 'Yes' : 'No',
                            (string) ($u->property_portal_role ?? '—'),
                            optional($u->created_at)->format('Y-m-d H:i:s') ?? '',
                        ];
                    }
                },
                $export
            );
        }

        return view('superadmin.users.index', compact('users', 'q', 'role', 'perPage'));
    }

    public function create(): View
    {
        return view('superadmin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'is_super_admin' => ['nullable', 'boolean'],
            'property_portal_role' => ['nullable', 'string', Rule::in(['agent', 'landlord', 'tenant'])],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_super_admin' => (bool) ($validated['is_super_admin'] ?? false),
            'property_portal_role' => $validated['property_portal_role'] ?? null,
            'email_verified_at' => now(),
        ]);

        $this->applyDefaultPropertyAccessOnCreate($user, $request->user()?->id);

        return redirect()
            ->route('superadmin.users.edit', $user)
            ->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $moduleAccess = [
            'property' => $user->moduleAccessStatus('property') ?? UserModuleAccess::STATUS_PENDING,
            'loan' => $user->moduleAccessStatus('loan') ?? UserModuleAccess::STATUS_PENDING,
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

        return view('superadmin.users.edit', compact(
            'user',
            'moduleAccess',
            'pmRoles',
            'pmPermissions',
            'selectedRoleIds',
            'selectedPermissionIds',
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

    private function applyDefaultPropertyAccessOnCreate(User $user, ?int $approvedBy): void
    {
        $portalRole = (string) ($user->property_portal_role ?? '');
        if ($portalRole === '') {
            return;
        }

        if (Schema::hasTable('user_module_accesses')) {
            $this->upsertModuleAccess($user->id, 'property', UserModuleAccess::STATUS_APPROVED, $approvedBy);
        }

        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_user_role')) {
            return;
        }

        $defaultRoleSlugByPortalRole = [
            'agent' => 'property_manager',
            'landlord' => 'landlord_portal_user',
            'tenant' => 'tenant_portal_user',
        ];

        $defaultRole = null;
        $expectedSlug = $defaultRoleSlugByPortalRole[$portalRole] ?? null;
        if ($expectedSlug !== null) {
            $defaultRole = PmRole::query()->where('slug', $expectedSlug)->first();
        }

        if (! $defaultRole) {
            $defaultRole = PmRole::query()
                ->where('portal_scope', $portalRole)
                ->orderBy('name')
                ->first();
        }

        if ($defaultRole) {
            $user->pmRoles()->syncWithoutDetaching([$defaultRole->id]);
        }
    }
}

