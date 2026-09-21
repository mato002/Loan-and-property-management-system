<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\SuperAdmin\AgentWorkspaceAdminService;
use App\Support\Auth\StaffModuleRedirect;
use App\Support\Property\PropertyBrandPalette;
use App\Support\Property\PropertyPortalTheme;
use App\Support\Property\PropertyWorkspaceBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    public function brandingIndex(Request $request): View|RedirectResponse
    {
        $agentId = (int) $request->query('agent', 0);
        if ($agentId > 0) {
            $agent = User::query()
                ->whereKey($agentId)
                ->where('property_portal_role', 'agent')
                ->first();

            if ($agent) {
                return redirect()->route('superadmin.agent_workspaces.branding', $agent);
            }
        }

        $agents = User::query()
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $companyNames = [];
        if (Schema::hasColumn('property_portal_settings', 'agent_user_id') && $agents->isNotEmpty()) {
            $companyNames = DB::table('property_portal_settings')
                ->where('key', 'company_name')
                ->whereIn('agent_user_id', $agents->pluck('id')->all())
                ->whereNotNull('agent_user_id')
                ->where('value', '!=', '')
                ->pluck('value', 'agent_user_id')
                ->all();
        }

        return view('superadmin.console.agent_branding_index', [
            'agents' => $agents,
            'companyNames' => $companyNames,
        ]);
    }

    public function branding(User $agent): View
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $agents = User::query()
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $agentId = (int) $agent->id;

        return view('superadmin.console.agent_branding', [
            'agent' => $agent,
            'agents' => $agents,
            'companyName' => PropertyWorkspaceBranding::getForAgent('company_name', $agentId, ''),
            'companyLogoUrl' => PropertyWorkspaceBranding::getForAgent('company_logo_url', $agentId, ''),
            'siteFaviconUrl' => PropertyWorkspaceBranding::getForAgent('site_favicon_url', $agentId, ''),
            'contactEmailPrimary' => PropertyWorkspaceBranding::getForAgent('contact_email_primary', $agentId, ''),
            'contactEmailSupport' => PropertyWorkspaceBranding::getForAgent('contact_email_support', $agentId, ''),
            'contactPhone' => PropertyWorkspaceBranding::getForAgent('contact_phone', $agentId, ''),
            'contactWhatsapp' => PropertyWorkspaceBranding::getForAgent('contact_whatsapp', $agentId, ''),
            'contactAddress' => PropertyWorkspaceBranding::getForAgent('contact_address', $agentId, ''),
            'contactRegNo' => PropertyWorkspaceBranding::getForAgent('contact_reg_no', $agentId, ''),
            'contactMapEmbedUrl' => PropertyWorkspaceBranding::getForAgent('contact_map_embed_url', $agentId, ''),
            'publicWebsiteDomain' => PropertyWorkspaceBranding::getForAgent('public_website_domain', $agentId, ''),
            'portalColorTheme' => PropertyPortalTheme::normalize(
                PropertyWorkspaceBranding::getForAgent('portal_color_theme', $agentId, PropertyPortalTheme::LIGHT)
            ),
            'brandPalette' => PropertyBrandPalette::normalize(
                PropertyWorkspaceBranding::getForAgent('brand_palette', $agentId, PropertyBrandPalette::PLATFORM)
            ),
        ]);
    }

    public function storeBranding(Request $request, User $agent): RedirectResponse
    {
        abort_unless((string) ($agent->property_portal_role ?? '') === 'agent', 404);

        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_logo_url' => ['nullable', 'string', 'max:2048'],
            'company_logo' => ['nullable', 'image', 'max:102400'],
            'site_favicon_url' => ['nullable', 'string', 'max:2048'],
            'site_favicon' => ['nullable', 'image', 'max:102400'],
            'contact_email_primary' => ['nullable', 'email', 'max:255'],
            'contact_email_support' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'contact_whatsapp' => ['nullable', 'string', 'max:64'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_reg_no' => ['nullable', 'string', 'max:128'],
            'contact_map_embed_url' => ['nullable', 'url', 'max:2048'],
            'public_website_domain' => ['nullable', 'string', 'max:255'],
            'portal_color_theme' => ['nullable', Rule::in(PropertyPortalTheme::OPTIONS)],
            'brand_palette' => ['nullable', Rule::in(PropertyBrandPalette::OPTIONS)],
            'remove_logo' => ['nullable', 'in:0,1'],
            'remove_favicon' => ['nullable', 'in:0,1'],
        ]);

        $agentId = (int) $agent->id;

        PropertyWorkspaceBranding::set('company_name', $data['company_name'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_email_primary', $data['contact_email_primary'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_email_support', $data['contact_email_support'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_phone', $data['contact_phone'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_whatsapp', $data['contact_whatsapp'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_address', $data['contact_address'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_reg_no', $data['contact_reg_no'] ?? '', $agentId);
        PropertyWorkspaceBranding::set('contact_map_embed_url', $data['contact_map_embed_url'] ?? '', $agentId);
        PropertyWorkspaceBranding::set(
            'public_website_domain',
            PropertyWorkspaceBranding::normalizePublicHost((string) ($data['public_website_domain'] ?? '')),
            $agentId
        );
        PropertyWorkspaceBranding::set(
            'portal_color_theme',
            PropertyPortalTheme::normalize($data['portal_color_theme'] ?? PropertyPortalTheme::LIGHT),
            $agentId
        );
        PropertyBrandPalette::persist($data['brand_palette'] ?? PropertyBrandPalette::PLATFORM, $agentId);

        if (($data['remove_logo'] ?? '0') === '1') {
            PropertyWorkspaceBranding::set('company_logo_url', '', $agentId);
        } elseif ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('property/branding/'.$agentId, 'public');
            PropertyWorkspaceBranding::set('company_logo_url', Storage::url($path), $agentId);
        } elseif (array_key_exists('company_logo_url', $data)) {
            PropertyWorkspaceBranding::set('company_logo_url', $data['company_logo_url'] ?? '', $agentId);
        }

        if (($data['remove_favicon'] ?? '0') === '1') {
            PropertyWorkspaceBranding::set('site_favicon_url', '', $agentId);
        } elseif ($request->hasFile('site_favicon')) {
            $path = $request->file('site_favicon')->store('property/branding/'.$agentId, 'public');
            PropertyWorkspaceBranding::set('site_favicon_url', Storage::url($path), $agentId);
        } elseif (array_key_exists('site_favicon_url', $data)) {
            PropertyWorkspaceBranding::set('site_favicon_url', $data['site_favicon_url'] ?? '', $agentId);
        }

        Log::info('superadmin_agent_branding_updated', [
            'actor_id' => (int) $request->user()?->id,
            'agent_user_id' => $agentId,
            'company_name' => $data['company_name'] ?? '',
        ]);

        return redirect()
            ->route('superadmin.agent_workspaces.branding', $agent)
            ->with('success', 'Branding saved for '.$agent->name.'. It will appear on their invoices, receipts, and prints.');
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
