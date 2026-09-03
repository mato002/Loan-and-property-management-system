<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\SuperAdmin\AgentWorkspaceAdminService;
use App\Support\Auth\StaffModuleRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminAgentWorkspaceController extends Controller
{
    private const IMPERSONATOR_SESSION_KEY = 'pm_impersonator_id';

    public function __construct(
        private readonly AgentWorkspaceAdminService $workspaces,
    ) {}

    public function index(Request $request): View|StreamedResponse
    {
        $workspace = trim((string) $request->query('workspace', 'all'));
        if (! in_array($workspace, ['all', 'empty', 'active'], true)) {
            $workspace = 'all';
        }
        $statusFilter = trim((string) $request->query('status', ''));
        if (! in_array($statusFilter, ['', 'active', 'suspended', 'pending'], true)) {
            $statusFilter = '';
        }
        $q = trim((string) $request->query('q', ''));
        $perPage = min(200, max(10, (int) $request->query('per_page', 25)));

        $agents = User::query()
            ->with(['moduleAccesses' => fn ($q) => $q->where('module', 'property')])
            ->where('property_portal_role', 'agent')
            ->when($q !== '', fn ($builder) => $builder->where(function ($b) use ($q) {
                $b->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
            }))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        [$propertyCounts, $unitCounts] = $this->countFootprints($agents->pluck('id')->all());

        $summaries = $this->workspaces->summarizeAgents($agents);

        $agents = $agents->filter(function (User $agent) use ($workspace, $propertyCounts, $statusFilter, $summaries) {
            $propertyCount = (int) ($propertyCounts[$agent->id] ?? 0);
            if ($workspace === 'empty' && $propertyCount !== 0) {
                return false;
            }
            if ($workspace === 'active' && $propertyCount === 0) {
                return false;
            }
            if ($statusFilter !== '') {
                $key = $summaries[(int) $agent->id]['status']['key'] ?? '';

                return $key === $statusFilter;
            }

            return true;
        })->values();

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf', 'word'], true)) {
            return $this->workspaces->exportAgents($agents, $propertyCounts, $unitCounts, $export);
        }

        $page = max(1, (int) $request->query('page', 1));
        $pagedAgents = new LengthAwarePaginator(
            $agents->forPage($page, $perPage)->values(),
            $agents->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $packages = Schema::hasTable('subscription_packages')
            ? SubscriptionPackage::query()->ordered()->pluck('name', 'id')
            : collect();

        $otherAgents = User::query()
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('superadmin.console.agent_workspaces', [
            'agents' => $pagedAgents,
            'propertyCounts' => $propertyCounts,
            'unitCounts' => $unitCounts,
            'summaries' => $summaries,
            'workspace' => $workspace,
            'statusFilter' => $statusFilter,
            'q' => $q,
            'perPage' => $perPage,
            'packages' => $packages,
            'otherAgents' => $otherAgents,
            'hasSubscriptions' => Schema::hasTable('agent_subscriptions'),
        ]);
    }

    public function show(User $agent): View
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        [$propertyCounts, $unitCounts] = $this->countFootprints([(int) $agent->id]);
        $properties = Schema::hasTable('properties')
            ? Property::query()
                ->where('agent_user_id', $agent->id)
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'code'])
            : collect();

        $summary = $this->workspaces->summarizeAgents(collect([$agent]))[(int) $agent->id] ?? [];
        $subscription = $this->workspaces->latestSubscription($agent);

        $otherAgents = User::query()
            ->where('property_portal_role', 'agent')
            ->where('id', '!=', $agent->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $packages = Schema::hasTable('subscription_packages')
            ? SubscriptionPackage::query()->ordered()->pluck('name', 'id')
            : collect();

        return view('superadmin.console.agent_workspaces_show', [
            'agent' => $agent,
            'properties' => $properties,
            'propertyCount' => (int) ($propertyCounts[$agent->id] ?? 0),
            'unitCount' => (int) ($unitCounts[$agent->id] ?? 0),
            'summary' => $summary,
            'subscription' => $subscription,
            'otherAgents' => $otherAgents,
            'packages' => $packages,
        ]);
    }

    public function impersonate(Request $request, User $agent): RedirectResponse
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $actor = $request->user();
        if (! $actor || ! ($actor->is_super_admin ?? false)) {
            abort(403);
        }

        if (! $request->session()->has(self::IMPERSONATOR_SESSION_KEY)) {
            $request->session()->put(self::IMPERSONATOR_SESSION_KEY, (int) $actor->id);
        }

        Auth::login($agent);
        StaffModuleRedirect::rememberModule($request, 'property');

        Log::info('superadmin_agent_workspace_impersonation_started', [
            'impersonator_id' => (int) $actor->id,
            'agent_user_id' => (int) $agent->id,
        ]);

        return redirect()->route('property.dashboard')
            ->with('success', 'Viewing '.$agent->name.'\'s property workspace. Use “Stop impersonating” to return.');
    }

    public function transfer(Request $request, User $agent): RedirectResponse
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $data = $request->validate([
            'target_agent_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('property_portal_role', 'agent'),
                Rule::notIn([(int) $agent->id]),
            ],
        ]);

        $moved = $this->workspaces->transferOwnership((int) $agent->id, (int) $data['target_agent_id']);

        return redirect()
            ->route('superadmin.agent_workspaces.show', $agent)
            ->with('success', 'Transferred workspace footprint ('.$moved.' scoped row updates).');
    }

    public function toggleStatus(Request $request, User $agent): RedirectResponse
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $data = $request->validate([
            'intent' => ['required', Rule::in(['suspend', 'activate'])],
        ]);

        if ($data['intent'] === 'suspend') {
            $this->workspaces->suspendWorkspace($agent, (int) $request->user()?->id);
            $message = 'Workspace suspended for '.$agent->name.'.';
        } else {
            $this->workspaces->activateWorkspace($agent, (int) $request->user()?->id);
            $message = 'Workspace activated for '.$agent->name.'.';
        }

        return back()->with('success', $message);
    }

    public function updateSubscription(Request $request, User $agent): RedirectResponse
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $data = $request->validate([
            'subscription_package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
        ]);

        $this->workspaces->changePackage(
            $agent,
            (int) $data['subscription_package_id'],
            (string) ($data['status'] ?? 'active'),
        );

        return back()->with('success', 'Subscription updated for '.$agent->name.'.');
    }

    public function bulk(Request $request): RedirectResponse|StreamedResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', Rule::in([
                'export',
                'change_package',
                'suspend',
                'activate',
            ])],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer', 'exists:users,id'],
            'format' => ['nullable', Rule::in(['csv', 'xls', 'pdf'])],
            'subscription_package_id' => ['required_if:bulk_action,change_package', 'nullable', 'integer', 'exists:subscription_packages,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $agents = User::query()
            ->whereIn('id', $ids)
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        if ($agents->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one agent workspace.']);
        }

        $actorId = (int) $request->user()?->id;

        return match ($data['bulk_action']) {
            'export' => $this->exportSelected($agents, (string) ($data['format'] ?? 'csv')),
            'change_package' => back()->with(
                'success',
                'Updated subscription package for '.$this->workspaces->bulkChangePackage(
                    $agents->pluck('id')->all(),
                    (int) $data['subscription_package_id'],
                ).' agent workspace(s).'
            ),
            'suspend' => back()->with(
                'success',
                'Suspended '.$this->workspaces->bulkSuspend($agents->pluck('id')->all(), $actorId).' agent workspace(s).'
            ),
            'activate' => back()->with(
                'success',
                'Activated '.$this->workspaces->bulkActivate($agents->pluck('id')->all(), $actorId).' agent workspace(s).'
            ),
        };
    }

    /**
     * @param  list<int>  $agentIds
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function countFootprints(array $agentIds): array
    {
        if ($agentIds === []) {
            return [collect(), collect()];
        }

        $propertyCounts = Schema::hasTable('properties')
            ? DB::table('properties')
                ->selectRaw('agent_user_id, COUNT(*) as c')
                ->whereNotNull('agent_user_id')
                ->whereIn('agent_user_id', $agentIds)
                ->groupBy('agent_user_id')
                ->pluck('c', 'agent_user_id')
            : collect();

        $unitCounts = Schema::hasTable('properties') && Schema::hasTable('property_units')
            ? DB::table('property_units as u')
                ->join('properties as p', 'p.id', '=', 'u.property_id')
                ->selectRaw('p.agent_user_id, COUNT(*) as c')
                ->whereNotNull('p.agent_user_id')
                ->whereIn('p.agent_user_id', $agentIds)
                ->groupBy('p.agent_user_id')
                ->pluck('c', 'p.agent_user_id')
            : collect();

        return [$propertyCounts, $unitCounts];
    }

    private function exportSelected($agents, string $format): StreamedResponse
    {
        [$propertyCounts, $unitCounts] = $this->countFootprints($agents->pluck('id')->all());

        return $this->workspaces->exportAgents($agents, $propertyCounts, $unitCounts, $format);
    }
}
