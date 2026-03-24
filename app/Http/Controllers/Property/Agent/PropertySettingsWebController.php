<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmPermission;
use App\Models\PmRole;
use App\Models\User;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PropertySettingsWebController extends Controller
{
    public function roles(): View
    {
        $portalUsers = User::query()
            ->whereNotNull('property_portal_role')
            ->with('pmRoles:id,name')
            ->orderBy('property_portal_role')
            ->orderBy('name')
            ->get();

        $roleLabels = [
            'agent' => 'Agent',
            'landlord' => 'Landlord',
            'tenant' => 'Tenant',
        ];

        $rows = $portalUsers->map(function (User $u) use ($roleLabels) {
            $role = $roleLabels[$u->property_portal_role] ?? ucfirst((string) $u->property_portal_role);
            $portfolios = '—';
            if ($u->property_portal_role === 'landlord') {
                $n = $u->landlordProperties()->count();
                $portfolios = $n === 0 ? '—' : (string) $n.' properties';
            }

            $actions = new HtmlString('<a href="'.route('property.settings.system_setup.access').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Review access</a>');
            if ($u->property_portal_role === 'landlord') {
                $actions = new HtmlString('<a href="'.route('property.landlords.index').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open landlords</a>');
            } elseif ($u->property_portal_role === 'tenant') {
                $actions = new HtmlString('<a href="'.route('property.tenants.directory').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open tenants</a>');
            }

            return [
                $u->name,
                $u->email,
                $role,
                $portfolios,
                $u->updated_at?->format('Y-m-d') ?? '—',
                $u->pmRoles->pluck('name')->implode(', ') ?: 'Follow org policy',
                $actions,
            ];
        })->all();

        return view('property.agent.settings.roles', [
            'stats' => [
                ['label' => 'Portal users', 'value' => (string) $portalUsers->count(), 'hint' => 'Agent / landlord / tenant'],
                ['label' => 'Agents', 'value' => (string) $portalUsers->where('property_portal_role', 'agent')->count(), 'hint' => ''],
                ['label' => 'Landlords', 'value' => (string) $portalUsers->where('property_portal_role', 'landlord')->count(), 'hint' => ''],
                ['label' => 'Custom roles', 'value' => (string) (Schema::hasTable('pm_roles') ? PmRole::query()->count() : 0), 'hint' => 'System setup'],
            ],
            'columns' => ['User', 'Email', 'Role', 'Portfolios', 'Last updated', 'MFA', 'Actions'],
            'tableRows' => $rows,
        ]);
    }

    public function permissions(): View
    {
        if (! Schema::hasTable('pm_permissions')) {
            return view('property.agent.settings.permissions', [
                'stats' => [
                    ['label' => 'Permissions', 'value' => '0', 'hint' => 'Run migrations first'],
                ],
                'columns' => ['Permission', 'Key', 'Group', 'Used by roles'],
                'tableRows' => [],
            ]);
        }

        $permissions = PmPermission::query()
            ->withCount('roles')
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return view('property.agent.settings.permissions', [
            'stats' => [
                ['label' => 'Permissions', 'value' => (string) $permissions->count(), 'hint' => 'System-wide'],
                ['label' => 'Groups', 'value' => (string) $permissions->pluck('group')->filter()->unique()->count(), 'hint' => 'Categories'],
                ['label' => 'Role links', 'value' => (string) $permissions->sum('roles_count'), 'hint' => 'Assignments'],
            ],
            'permissions' => $permissions,
        ]);
    }
}
