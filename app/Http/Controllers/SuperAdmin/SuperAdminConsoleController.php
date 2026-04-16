<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AgentSubscription;
use App\Models\LoanBookApplication;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\PmPermission;
use App\Models\PmPortalAction;
use App\Models\PmRole;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\SubscriptionPackage;
use App\Models\UnassignedPayment;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Support\TabularExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminConsoleController extends Controller
{
    /**
     * System-wide loan book snapshot (super admin, not portfolio-scoped).
     *
     * @return array<string, mixed>
     */
    private function buildGlobalLoanStats(): array
    {
        $bookReady = Schema::hasTable('loan_book_loans');
        $paymentsReady = Schema::hasTable('loan_book_payments');
        $clientsReady = Schema::hasTable('loan_clients');
        $applicationsReady = Schema::hasTable('loan_book_applications');

        $loanStats = [
            'tables_ready' => $bookReady || $paymentsReady || $clientsReady || $applicationsReady,
            'book_ready' => $bookReady,
            'active_loans' => 0,
            'total_loans' => 0,
            'outstanding' => 0.0,
            'npl_count' => 0,
            'clients' => 0,
            'leads' => 0,
            'pipeline' => 0,
            'credit_review' => 0,
            'mtd_collections' => 0.0,
            'unposted_payments' => 0,
            'pending_loan_access' => 0,
        ];

        if ($bookReady) {
            $portfolioStatuses = [
                LoanBookLoan::STATUS_ACTIVE,
                LoanBookLoan::STATUS_RESTRUCTURED,
                LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
            ];
            $loanStats['active_loans'] = (int) LoanBookLoan::query()->where('status', LoanBookLoan::STATUS_ACTIVE)->count();
            $loanStats['total_loans'] = (int) LoanBookLoan::query()->count();
            $loanStats['outstanding'] = (float) LoanBookLoan::query()->whereIn('status', $portfolioStatuses)->sum('balance');
            $loanStats['npl_count'] = (int) LoanBookLoan::query()
                ->where('status', LoanBookLoan::STATUS_ACTIVE)
                ->where('dpd', '>', 30)
                ->count();
        }

        if ($clientsReady) {
            $loanStats['clients'] = (int) LoanClient::query()->where('kind', LoanClient::KIND_CLIENT)->count();
            $loanStats['leads'] = (int) LoanClient::query()->where('kind', LoanClient::KIND_LEAD)->count();
        }

        if ($applicationsReady) {
            $loanStats['pipeline'] = (int) LoanBookApplication::query()
                ->whereNotIn('stage', [LoanBookApplication::STAGE_DISBURSED, LoanBookApplication::STAGE_DECLINED])
                ->count();
            $loanStats['credit_review'] = (int) LoanBookApplication::query()
                ->where('stage', LoanBookApplication::STAGE_CREDIT_REVIEW)
                ->count();
        }

        if ($paymentsReady) {
            $loanStats['mtd_collections'] = (float) LoanBookPayment::query()
                ->where('status', LoanBookPayment::STATUS_PROCESSED)
                ->whereNull('merged_into_payment_id')
                ->whereBetween('transaction_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
            $loanStats['unposted_payments'] = (int) LoanBookPayment::query()->unpostedQueue()->count();
        }

        if (Schema::hasTable('user_module_accesses')) {
            $loanStats['pending_loan_access'] = (int) UserModuleAccess::query()
                ->where('module', 'loan')
                ->where('status', UserModuleAccess::STATUS_PENDING)
                ->count();
        }

        return $loanStats;
    }

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
        $recentActivities = Schema::hasTable('pm_portal_actions') 
            ? PmPortalAction::query()->with('user:id,name,email')->latest('id')->limit(5)->get() 
            : collect();

        $loanStats = $this->buildGlobalLoanStats();
        $showTenantFinancialAggregates = (bool) config('superadmin.show_tenant_financial_aggregates', false);

        return view('superadmin.console.dashboard', compact(
            'stats',
            'recentActivities',
            'loanStats',
            'showTenantFinancialAggregates',
        ));
    }

    public function accessApprovals(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $module = strtolower(trim((string) $request->query('module', '')));
        if (! in_array($module, ['', 'property', 'loan'], true)) {
            $module = '';
        }
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));
        $status = strtolower(trim((string) $request->query('status', '')));
        if (! in_array($status, ['', 'pending', 'approved', 'revoked'], true)) {
            $status = '';
        }
        $query = Schema::hasTable('user_module_accesses')
            ? UserModuleAccess::query()
                ->with('user:id,name,email')
                ->when($status !== '', fn ($builder) => $builder->where('status', $status))
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
                ['User', 'Email', 'Module', 'Status', 'Requested at', 'Approved at'],
                function () use ($rows) {
                    foreach ($rows as $item) {
                        yield [
                            (string) ($item->user?->name ?? '—'),
                            (string) ($item->user?->email ?? '—'),
                            strtoupper((string) $item->module),
                            ucfirst((string) $item->status),
                            optional($item->created_at)->format('Y-m-d H:i:s') ?? '',
                            optional($item->approved_at)->format('Y-m-d H:i:s') ?? '',
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
            'status' => $status,
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
            'bulk_mode' => ['required', 'in:filter,selected'],
            'action' => ['required', 'in:approve,revoke,pending'],
            'q' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:32'],
            'ids' => ['required_if:bulk_mode,selected', 'array', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        if ($data['bulk_mode'] === 'selected') {
            $selectedIds = array_values(array_unique(array_filter(
                array_map('intval', $data['ids'] ?? []),
                fn (int $id) => $id > 0
            )));

            return $this->bulkAccessApprovalsForSelectedIds($request, $data['action'], $selectedIds);
        }

        if ($data['action'] === 'pending') {
            return back()->withErrors(['access' => 'Use row actions or “selected rows” bulk actions to set records back to pending.']);
        }

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

    /**
     * Bulk update for explicitly selected rows (same transition rules as single-row actions).
     *
     * @param  list<int>  $ids
     */
    private function bulkAccessApprovalsForSelectedIds(Request $request, string $action, array $ids): RedirectResponse
    {
        $userId = $request->user()?->id;

        if ($action === 'approve') {
            $affected = UserModuleAccess::query()
                ->whereIn('id', $ids)
                ->whereIn('status', [UserModuleAccess::STATUS_PENDING, UserModuleAccess::STATUS_REVOKED])
                ->update([
                    'status' => UserModuleAccess::STATUS_APPROVED,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);
        } elseif ($action === 'revoke') {
            $affected = UserModuleAccess::query()
                ->whereIn('id', $ids)
                ->whereIn('status', [UserModuleAccess::STATUS_PENDING, UserModuleAccess::STATUS_APPROVED])
                ->update([
                    'status' => UserModuleAccess::STATUS_REVOKED,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
        } else {
            $affected = UserModuleAccess::query()
                ->whereIn('id', $ids)
                ->whereIn('status', [UserModuleAccess::STATUS_APPROVED, UserModuleAccess::STATUS_REVOKED])
                ->update([
                    'status' => UserModuleAccess::STATUS_PENDING,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
        }

        if ($affected === 0) {
            return back()->with('success', 'No selected rows were eligible for that action (status may not allow it).');
        }

        return back()->with('success', 'Updated '.$affected.' access record(s).');
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

    public function subscriptionPackages(Request $request)
    {
        $packages = Schema::hasTable('subscription_packages')
            ? SubscriptionPackage::query()->ordered()->get()
            : collect();

        return view('superadmin.console.subscription_packages', [
            'packages' => $packages,
            'tablesReady' => Schema::hasTable('subscription_packages'),
        ]);
    }

    public function storeSubscriptionPackage(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('subscription_packages')) {
            return back()->withErrors(['error' => 'Subscription packages table is not ready.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_units' => ['required', 'integer', 'min:1'],
            'max_units' => ['nullable', 'integer', 'min:1'],
            'monthly_price_ksh' => ['required', 'numeric', 'min:0'],
            'annual_price_ksh' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        if (isset($data['max_units']) && $data['max_units'] < $data['min_units']) {
            return back()->withErrors(['max_units' => 'Maximum units must be greater than or equal to minimum units.']);
        }

        SubscriptionPackage::create($data);

        return back()->with('success', 'Subscription package created successfully.');
    }

    public function updateSubscriptionPackage(Request $request, SubscriptionPackage $package): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_units' => ['required', 'integer', 'min:1'],
            'max_units' => ['nullable', 'integer', 'min:1'],
            'monthly_price_ksh' => ['required', 'numeric', 'min:0'],
            'annual_price_ksh' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ]);

        if (isset($data['max_units']) && $data['max_units'] < $data['min_units']) {
            return back()->withErrors(['max_units' => 'Maximum units must be greater than or equal to minimum units.']);
        }

        $package->update($data);

        return back()->with('success', 'Subscription package updated successfully.');
    }

    public function deleteSubscriptionPackage(SubscriptionPackage $package): RedirectResponse
    {
        if ($package->agentSubscriptions()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete package with existing subscriptions.']);
        }

        $package->delete();

        return back()->with('success', 'Subscription package deleted successfully.');
    }

    public function agentSubscriptions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', '');
        $package = $request->query('package', '');
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $query = Schema::hasTable('agent_subscriptions')
            ? AgentSubscription::query()
                ->with(['user:id,name,email', 'subscriptionPackage:id,name,monthly_price_ksh'])
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->whereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', '%'.$q.'%')
                            ->orWhere('email', 'like', '%'.$q.'%');
                    });
                })
                ->when($status !== '', fn ($builder) => $builder->where('status', $status))
                ->when($package !== '', fn ($builder) => $builder->where('subscription_package_id', $package))
                ->latest('id')
            : null;

        $export = strtolower((string) $request->query('export', ''));
        if ($query && in_array($export, ['csv', 'xls', 'pdf'], true)) {
            $rows = (clone $query)->limit(5000)->get();

            return TabularExport::stream(
                'superadmin-agent-subscriptions-'.now()->format('Ymd_His'),
                ['Agent', 'Email', 'Package', 'Status', 'Start Date', 'End Date', 'Price Paid', 'Payment Method'],
                function () use ($rows) {
                    foreach ($rows as $item) {
                        yield [
                            (string) ($item->user?->name ?? '—'),
                            (string) ($item->user?->email ?? '—'),
                            (string) ($item->subscriptionPackage?->name ?? '—'),
                            ucfirst((string) $item->status),
                            $item->starts_at?->format('Y-m-d') ?? '',
                            $item->ends_at?->format('Y-m-d') ?? '',
                            (string) ($item->price_paid ?? ''),
                            (string) ($item->payment_method ?? ''),
                        ];
                    }
                },
                $export
            );
        }

        $items = $query ? $query->paginate($perPage)->withQueryString() : collect();

        $packages = Schema::hasTable('subscription_packages')
            ? SubscriptionPackage::active()->ordered()->pluck('name', 'id')
            : collect();
        
        $agents = Schema::hasTable('users')
            ? User::where('property_portal_role', 'agent')->orderBy('name')->get(['id', 'name', 'email'])
            : collect();

        return view('superadmin.console.agent_subscriptions', [
            'items' => $items,
            'packages' => $packages,
            'agents' => $agents,
            'q' => $q,
            'status' => $status,
            'package' => $package,
            'perPage' => $perPage,
            'tablesReady' => Schema::hasTable('agent_subscriptions'),
        ]);
    }

    public function storeAgentSubscription(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('agent_subscriptions')) {
            return back()->withErrors(['error' => 'Agent subscriptions table is not ready.']);
        }

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'status' => ['required', 'in:active,inactive,suspended,cancelled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        AgentSubscription::create($data);

        return back()->with('success', 'Agent subscription created successfully.');
    }

    public function updateAgentSubscription(Request $request, AgentSubscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended,cancelled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $subscription->update($data);

        return back()->with('success', 'Agent subscription updated successfully.');
    }

    public function deleteAgentSubscription(AgentSubscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Agent subscription deleted successfully.');
    }

    public function bulkAgentSubscriptions(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('agent_subscriptions')) {
            return back()->withErrors(['error' => 'Agent subscriptions table is not ready.']);
        }

        $data = $request->validate([
            'bulk_action' => ['required', Rule::in(['delete', 'set_status'])],
            'status' => ['required_if:bulk_action,set_status', 'nullable', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
            'ids' => ['required', 'array', 'max:300'],
            'ids.*' => ['integer', 'exists:agent_subscriptions,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        if ($data['bulk_action'] === 'delete') {
            $deleted = AgentSubscription::query()->whereIn('id', $ids)->delete();

            return back()->with('success', 'Deleted '.(int) $deleted.' subscription(s).');
        }

        $status = (string) $data['status'];
        $updated = AgentSubscription::query()->whereIn('id', $ids)->update(['status' => $status]);

        return back()->with('success', 'Updated '.(int) $updated.' subscription(s) to '.ucfirst($status).'.');
    }

    public function bulkSubscriptionPackages(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('subscription_packages')) {
            return back()->withErrors(['error' => 'Subscription packages table is not ready.']);
        }

        $data = $request->validate([
            'bulk_action' => ['required', Rule::in(['delete', 'set_active'])],
            'is_active' => ['required_if:bulk_action,set_active', 'nullable', 'boolean'],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer', 'exists:subscription_packages,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        if ($data['bulk_action'] === 'set_active') {
            $flag = (bool) $data['is_active'];
            $updated = SubscriptionPackage::query()->whereIn('id', $ids)->update(['is_active' => $flag]);

            return back()->with('success', 'Updated '.(int) $updated.' package(s) to '.($flag ? 'active' : 'inactive').'.');
        }

        $deleted = 0;
        $skipped = 0;
        $packages = SubscriptionPackage::query()->whereIn('id', $ids)->get();
        foreach ($packages as $package) {
            if ($package->agentSubscriptions()->exists()) {
                $skipped++;

                continue;
            }
            $package->delete();
            $deleted++;
        }

        $msg = 'Deleted '.$deleted.' package(s).';
        if ($skipped > 0) {
            $msg .= ' Skipped '.$skipped.' in-use package(s).';
        }

        return back()->with('success', $msg);
    }

    public function auditTrailExportSelected(Request $request): RedirectResponse|StreamedResponse
    {
        if (! Schema::hasTable('pm_portal_actions')) {
            return back()->withErrors(['error' => 'Audit table is not ready.']);
        }

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:2000'],
            'ids.*' => ['integer', 'min:1'],
            'format' => ['nullable', Rule::in(['csv', 'xls', 'pdf'])],
        ]);

        $format = $data['format'] ?? 'csv';
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        $rows = PmPortalAction::query()
            ->whereIn('id', $ids)
            ->with('user:id,name,email')
            ->latest('id')
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors(['export' => 'No matching audit rows found for the selection.']);
        }

        return TabularExport::stream(
            'superadmin-audit-selected-'.now()->format('Ymd_His'),
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
            $format
        );
    }

    public function agentWorkspacesExportSelected(Request $request): RedirectResponse|StreamedResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer', 'exists:users,id'],
            'format' => ['nullable', Rule::in(['csv', 'xls', 'pdf'])],
        ]);

        $format = $data['format'] ?? 'csv';
        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        $agents = User::query()
            ->whereIn('id', $ids)
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        if ($agents->isEmpty()) {
            return back()->withErrors(['export' => 'Select at least one agent row.']);
        }

        $propertyCounts = Schema::hasTable('properties')
            ? DB::table('properties')
                ->selectRaw('agent_user_id, COUNT(*) as c')
                ->whereNotNull('agent_user_id')
                ->whereIn('agent_user_id', $agents->pluck('id')->all())
                ->groupBy('agent_user_id')
                ->pluck('c', 'agent_user_id')
            : collect();

        $unitCounts = Schema::hasTable('properties') && Schema::hasTable('property_units')
            ? DB::table('property_units as u')
                ->join('properties as p', 'p.id', '=', 'u.property_id')
                ->selectRaw('p.agent_user_id, COUNT(*) as c')
                ->whereNotNull('p.agent_user_id')
                ->whereIn('p.agent_user_id', $agents->pluck('id')->all())
                ->groupBy('p.agent_user_id')
                ->pluck('c', 'p.agent_user_id')
            : collect();

        return TabularExport::stream(
            'superadmin-agents-selected-'.now()->format('Ymd_His'),
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
            $format
        );
    }
}

