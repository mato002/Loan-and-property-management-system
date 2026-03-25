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

class SuperAdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.users.index', compact('users', 'q'));
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
}

