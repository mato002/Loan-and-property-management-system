<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PmPermission;
use App\Models\PmPortalAction;
use App\Models\PmRole;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\TabularExport;

class SuperAdminConsoleController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'users' => (int) User::query()->count(),
            'agents' => (int) User::query()->where('property_portal_role', 'agent')->count(),
            'properties' => Schema::hasTable('properties') ? (int) Property::query()->count() : 0,
            'units' => Schema::hasTable('property_units') ? (int) PropertyUnit::query()->count() : 0,
            'tenants' => Schema::hasTable('pm_tenants') ? (int) PmTenant::query()->count() : 0,
            'unmatched_payments' => Schema::hasTable('unassigned_payments') ? (int) UnassignedPayment::query()->count() : 0,
            'pending_access' => Schema::hasTable('user_module_accesses')
                ? (int) UserModuleAccess::query()->where('status', UserModuleAccess::STATUS_PENDING)->count()
                : 0,
        ];

        return view('superadmin.console.dashboard', compact('stats'));
    }

    public function accessApprovals(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $module = strtolower(trim((string) $request->query('module', '')));
        if (! in_array($module, ['', 'property', 'loan'], true)) {
            $module = '';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));
        $query = Schema::hasTable('user_module_accesses')
            ? UserModuleAccess::query()
                ->with('user:id,name,email')
                ->where('status', UserModuleAccess::STATUS_PENDING)
                ->when($module !== '', fn ($builder) => $builder->where('module', $module))
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->whereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%');
                    });
                })
            : null;

        if ($query) {
            $query->latest('id');
        }
        $export = strtolower((string) $request->query('export', ''));
        if ($query && in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'superadmin-access-approvals-'.now()->format('Ymd_His'),
                ['User', 'Email', 'Module', 'Status', 'Requested at'],
                function () use ($rows) {
                    foreach ($rows as $item) {
                        yield [
                            (string) ($item->user?->name ?? '—'),
                            (string) ($item->user?->email ?? '—'),
                            strtoupper((string) $item->module),
                            ucfirst((string) $item->status),
                            optional($item->created_at)->format('Y-m-d H:i:s') ?? '',
                        ];
                    }
                },
                $export
            );
        }

        $items = $query ? $query->paginate($perPage)->withQueryString() : collect();

        return view('superadmin.console.access_approvals', [
            'items' => $items,
            'tablesReady' => Schema::hasTable('user_module_accesses'),
            'q' => $q,
            'module' => $module,
            'perPage' => $perPage,
        ]);
    }

    public function updateAccessApproval(Request $request, UserModuleAccess $access): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,revoked,pending'],
        ]);

        $payload = [
            'status' => $data['status'],
        ];

        if ($data['status'] === UserModuleAccess::STATUS_APPROVED) {
            $payload['approved_by'] = $request->user()?->id;
            $payload['approved_at'] = now();
        } else {
            $payload['approved_by'] = null;
            $payload['approved_at'] = null;
        }

        $access->update($payload);

        return back()->with('success', 'Module access updated.');
    }

    public function bulkAccessApprovals(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return back()->withErrors(['access' => 'Module access table is not ready.']);
        }

        $data = $request->validate([
            'action' => ['required', 'in:approve,revoke'],
            'q' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:32'],
        ]);

        $status = $data['action'] === 'approve'
            ? UserModuleAccess::STATUS_APPROVED
            : UserModuleAccess::STATUS_REVOKED;
        $q = trim((string) ($data['q'] ?? ''));
        $module = trim((string) ($data['module'] ?? ''));
        if (! in_array($module, ['', 'property', 'loan'], true)) {
            $module = '';
        }

        $query = UserModuleAccess::query()
            ->where('status', UserModuleAccess::STATUS_PENDING)
            ->when($module !== '', fn ($builder) => $builder->where('module', $module))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->whereHas('user', function ($userQuery) use ($q) {
                    $userQuery->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                });
            });

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return back()->with('success', 'No pending approvals matched your current filter.');
        }

        $payload = [
            'status' => $status,
            'approved_by' => $status === UserModuleAccess::STATUS_APPROVED ? $request->user()?->id : null,
            'approved_at' => $status === UserModuleAccess::STATUS_APPROVED ? now() : null,
        ];

        UserModuleAccess::query()->whereIn('id', $ids)->update($payload);

        return back()->with('success', 'Updated '.count($ids).' access request(s).');
    }

    public function rolesPermissions(): View
    {
        $tablesReady = Schema::hasTable('pm_roles') && Schema::hasTable('pm_permissions');
        $roles = $tablesReady ? PmRole::query()->withCount('permissions')->orderBy('portal_scope')->orderBy('name')->get() : collect();
        $permissionsByGroup = $tablesReady
            ? PmPermission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')
            : collect();

        return view('superadmin.console.roles_permissions', compact('tablesReady', 'roles', 'permissionsByGroup'));
    }

    public function agentWorkspaces(Request $request)
    {
        $workspace = trim((string) $request->query('workspace', 'all'));
        if (! in_array($workspace, ['all', 'empty', 'active'], true)) {
            $workspace = 'all';
        }
        $q = trim((string) $request->query('q', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $agents = User::query()
            ->where('property_portal_role', 'agent')
            ->when($q !== '', fn ($builder) => $builder->where(function ($b) use ($q) {
                $b->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $propertyCounts = Schema::hasTable('properties')
            ? DB::table('properties')
                ->selectRaw('agent_user_id, COUNT(*) as c')
                ->whereNotNull('agent_user_id')
                ->groupBy('agent_user_id')
                ->pluck('c', 'agent_user_id')
            : collect();

        $unitCounts = Schema::hasTable('properties') && Schema::hasTable('property_units')
            ? DB::table('property_units as u')
                ->join('properties as p', 'p.id', '=', 'u.property_id')
                ->selectRaw('p.agent_user_id, COUNT(*) as c')
                ->whereNotNull('p.agent_user_id')
                ->groupBy('p.agent_user_id')
                ->pluck('c', 'p.agent_user_id')
            : collect();

        $agents = $agents->filter(function (User $agent) use ($workspace, $propertyCounts) {
            $propertyCount = (int) ($propertyCounts[$agent->id] ?? 0);
            if ($workspace === 'empty') {
                return $propertyCount === 0;
            }
            if ($workspace === 'active') {
                return $propertyCount > 0;
            }

            return true;
        })->values();

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return TabularExport::stream(
                'superadmin-agent-workspaces-'.now()->format('Ymd_His'),
                ['Agent', 'Email', 'Properties', 'Units'],
                function () use ($agents, $propertyCounts, $unitCounts) {
                    foreach ($agents as $agent) {
                        yield [
                            (string) $agent->name,
                            (string) $agent->email,
                            (string) ((int) ($propertyCounts[$agent->id] ?? 0)),
                            (string) ((int) ($unitCounts[$agent->id] ?? 0)),
                        ];
                    }
                },
                $export
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $total = $agents->count();
        $pagedAgents = new LengthAwarePaginator(
            $agents->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('superadmin.console.agent_workspaces', [
            'agents' => $pagedAgents,
            'propertyCounts' => $propertyCounts,
            'unitCounts' => $unitCounts,
            'workspace' => $workspace,
            'q' => $q,
            'perPage' => $perPage,
        ]);
    }

    public function auditTrail(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = strtolower(trim((string) $request->query('role', '')));
        if (! in_array($role, ['', 'super_admin', 'agent', 'landlord', 'tenant'], true)) {
            $role = '';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 30)));
        $items = Schema::hasTable('pm_portal_actions')
            ? PmPortalAction::query()
                ->with('user:id,name,email')
                ->when($role !== '', fn ($query) => $query->where('portal_role', $role))
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.$q.'%';
                    $query->where(function ($sub) use ($like) {
                        $sub->where('action_key', 'like', $like)
                            ->orWhere('notes', 'like', $like)
                            ->orWhere('portal_role', 'like', $like);
                    });
                })
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString()
            : collect();

        $export = strtolower((string) $request->query('export', ''));
        if (Schema::hasTable('pm_portal_actions') && in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = PmPortalAction::query()
                ->with('user:id,name,email')
                ->when($role !== '', fn ($query) => $query->where('portal_role', $role))
                ->when($q !== '', function ($query) use ($q) {
                    $like = '%'.$q.'%';
                    $query->where(function ($sub) use ($like) {
                        $sub->where('action_key', 'like', $like)
                            ->orWhere('notes', 'like', $like)
                            ->orWhere('portal_role', 'like', $like);
                    });
                })
                ->latest('id')
                ->limit(5000)
                ->get();

            return TabularExport::stream(
                'superadmin-audit-trail-'.now()->format('Ymd_His'),
                ['When', 'User', 'Email', 'Role', 'Action key', 'Notes'],
                function () use ($rows) {
                    foreach ($rows as $item) {
                        yield [
                            optional($item->created_at)->format('Y-m-d H:i:s') ?? '',
                            (string) ($item->user?->name ?? '—'),
                            (string) ($item->user?->email ?? ''),
                            (string) ($item->portal_role ?? ''),
                            (string) ($item->action_key ?? ''),
                            (string) ($item->notes ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        return view('superadmin.console.audit_trail', [
            'items' => $items,
            'q' => $q,
            'role' => $role,
            'perPage' => $perPage,
            'tablesReady' => Schema::hasTable('pm_portal_actions'),
        ]);
    }
}

