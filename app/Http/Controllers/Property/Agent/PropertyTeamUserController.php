<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Property\Agent\PropertySettingsStoreWebController;
use App\Http\Controllers\Controller;
use App\Models\PmRole;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Property\Concerns\RespondsWithPropertyFormModal;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PropertyTeamUserController extends Controller
{
    use RespondsWithPropertyFormModal;

    public function create(Request $request): View
    {
        app(PropertySettingsStoreWebController::class)->ensureAccessControlDefaults();

        if (! Schema::hasTable('pm_roles')) {
            return property_view('property.agent.settings.team_users.create', array_merge([
                'roles' => collect(),
                'rolesReady' => false,
            ], $this->propertyFormModalViewData($request)));
        }

        $roles = PmRole::query()
            ->whereIn('portal_scope', ['agent', 'any'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'portal_scope']);

        return property_view('property.agent.settings.team_users.create', array_merge([
            'roles' => $roles,
            'rolesReady' => $roles->isNotEmpty(),
        ], $this->propertyFormModalViewData($request)));
    }

    public function store(Request $request): RedirectResponse|Response
    {
        app(PropertySettingsStoreWebController::class)->ensureAccessControlDefaults();

        if (! Schema::hasTable('pm_roles') || ! Schema::hasTable('pm_user_role')) {
            return redirect()
                ->route('property.settings.team_users.create')
                ->withErrors(['role_ids' => __('Run migrations and define at least one agent role under System setup → Access control.')]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:pm_roles,id'],
        ];
        if (Schema::hasColumn('users', 'phone')) {
            $rules['phone'] = ['nullable', 'string', 'max:32'];
        }

        $validated = $request->validate($rules);

        $roleIds = array_values(array_unique(array_map('intval', $validated['role_ids'])));
        $matched = PmRole::query()
            ->whereIn('portal_scope', ['agent', 'any'])
            ->whereIn('id', $roleIds)
            ->count();

        if ($matched !== count($roleIds)) {
            throw ValidationException::withMessages([
                'role_ids' => __('One or more selected roles are not valid for internal staff.'),
            ]);
        }

        $plainPassword = Str::password(16, symbols: false);

        $user = DB::transaction(function () use ($request, $validated, $plainPassword, $roleIds) {
            $payload = [
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => Hash::make($plainPassword),
                'property_portal_role' => 'agent',
                'email_verified_at' => now(),
            ];
            if (Schema::hasColumn('users', 'phone')) {
                $phone = trim((string) ($validated['phone'] ?? ''));
                $payload['phone'] = $phone !== '' ? $phone : null;
            }

            $user = User::query()->create($payload);

            if (Schema::hasTable('user_module_accesses')) {
                $actorId = (int) $request->user()->id;
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'property'],
                    [
                        'status' => UserModuleAccess::STATUS_APPROVED,
                        'approved_by' => $actorId,
                        'approved_at' => now(),
                    ],
                );
                UserModuleAccess::query()->updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'loan'],
                    ['status' => UserModuleAccess::STATUS_REVOKED],
                );
            }

            $user->pmRoles()->sync($roleIds);

            return $user;
        });

        $successMessage = __('Team member :name was created. Share the temporary password securely; they should change it after first login.', ['name' => $user->name]);

        return $this->redirectOrPropertyFormModalSuccess(
            $request,
            redirect()
                ->route('property.settings.roles')
                ->with('success', $successMessage)
                ->with('team_user_created', [
                    'email' => $user->email,
                    'temporary_password' => $plainPassword,
                ]),
            $successMessage,
        );
    }
}
