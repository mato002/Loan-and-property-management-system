<?php

namespace App\Services\SuperAdmin;

use App\Models\AgentSubscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\UserModuleAccess;
use App\Support\TabularExport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgentWorkspaceAdminService
{
    /** @var list<string> */
    private const AGENT_SCOPED_TABLES = [
        'properties',
        'pm_tenants',
        'unassigned_payments',
        'pm_landlord_ledger_entries',
        'pm_messages',
        'pm_communication_exports',
        'utility_billing_periods',
        'accounting_chart_accounts',
        'accounting_periods',
        'accounting_journal_batches',
        'accounting_journal_lines',
        'accounting_payroll_periods',
        'property_portal_settings',
    ];

    /**
     * @return array{label: string, tone: string, key: string}
     */
    public function workspaceStatus(User $agent): array
    {
        if ($this->isPropertyModuleRevoked($agent)) {
            return ['key' => 'suspended', 'label' => 'Suspended', 'tone' => 'red'];
        }

        $latest = $this->latestSubscription($agent);

        if (! $latest) {
            return ['key' => 'pending', 'label' => 'Pending', 'tone' => 'orange'];
        }

        if ($latest->status === AgentSubscription::STATUS_SUSPENDED) {
            return ['key' => 'suspended', 'label' => 'Suspended', 'tone' => 'red'];
        }

        if ($latest->status === AgentSubscription::STATUS_ACTIVE && $latest->isActive()) {
            return ['key' => 'active', 'label' => 'Active', 'tone' => 'green'];
        }

        return ['key' => 'pending', 'label' => 'Pending', 'tone' => 'orange'];
    }

    public function subscriptionLabel(User $agent): string
    {
        $latest = $this->latestSubscription($agent);
        if (! $latest) {
            return 'No plan';
        }

        $packageName = $latest->subscriptionPackage?->name;

        return $packageName ? (string) $packageName : 'Unassigned package';
    }

    public function latestSubscription(User $agent): ?AgentSubscription
    {
        if (! Schema::hasTable('agent_subscriptions')) {
            return null;
        }

        return AgentSubscription::query()
            ->with('subscriptionPackage:id,name')
            ->where('user_id', $agent->id)
            ->latest('id')
            ->first();
    }

    /**
     * @param  Collection<int, User>  $agents
     * @return array<int, array{status: array{label: string, tone: string, key: string}, subscription: string, subscription_id: ?int, package_id: ?int}>
     */
    public function summarizeAgents(Collection $agents): array
    {
        $map = [];
        foreach ($agents as $agent) {
            $latest = $this->latestSubscription($agent);
            $map[(int) $agent->id] = [
                'status' => $this->workspaceStatus($agent),
                'subscription' => $this->subscriptionLabel($agent),
                'subscription_id' => $latest?->id,
                'package_id' => $latest?->subscription_package_id,
            ];
        }

        return $map;
    }

    public function suspendWorkspace(User $agent, ?int $actorId = null): void
    {
        $this->setPropertyModuleAccess($agent, UserModuleAccess::STATUS_REVOKED, $actorId);
        $this->upsertSubscriptionStatus($agent, AgentSubscription::STATUS_SUSPENDED);
    }

    public function activateWorkspace(User $agent, ?int $actorId = null): void
    {
        $this->setPropertyModuleAccess($agent, UserModuleAccess::STATUS_APPROVED, $actorId);
        $this->upsertSubscriptionStatus($agent, AgentSubscription::STATUS_ACTIVE);
    }

    public function transferOwnership(int $fromAgentId, int $toAgentId): int
    {
        if ($fromAgentId === $toAgentId) {
            return 0;
        }

        $moved = 0;

        DB::transaction(function () use ($fromAgentId, $toAgentId, &$moved) {
            foreach (self::AGENT_SCOPED_TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'agent_user_id')) {
                    continue;
                }

                $moved += (int) DB::table($table)
                    ->where('agent_user_id', $fromAgentId)
                    ->update(['agent_user_id' => $toAgentId]);
            }

            if (Schema::hasColumn('users', 'agent_user_id')) {
                DB::table('users')
                    ->where('agent_user_id', $fromAgentId)
                    ->where('property_portal_role', 'landlord')
                    ->update(['agent_user_id' => $toAgentId]);
            }
        });

        return $moved;
    }

    public function changePackage(User $agent, int $packageId, string $status = AgentSubscription::STATUS_ACTIVE): void
    {
        if (! Schema::hasTable('agent_subscriptions') || ! Schema::hasTable('subscription_packages')) {
            return;
        }

        SubscriptionPackage::query()->findOrFail($packageId);

        $latest = $this->latestSubscription($agent);

        if ($latest) {
            $latest->update([
                'subscription_package_id' => $packageId,
                'status' => $status,
                'starts_at' => $latest->starts_at ?? now()->toDateString(),
            ]);

            return;
        }

        AgentSubscription::query()->create([
            'user_id' => $agent->id,
            'subscription_package_id' => $packageId,
            'status' => $status,
            'starts_at' => now()->toDateString(),
        ]);
    }

    /**
     * @param  list<int>  $agentIds
     */
    public function bulkChangePackage(array $agentIds, int $packageId): int
    {
        $count = 0;
        $agents = User::query()
            ->whereIn('id', $agentIds)
            ->where('property_portal_role', 'agent')
            ->get();

        foreach ($agents as $agent) {
            $this->changePackage($agent, $packageId);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<int>  $agentIds
     */
    public function bulkSuspend(array $agentIds, ?int $actorId = null): int
    {
        $count = 0;
        $agents = User::query()
            ->whereIn('id', $agentIds)
            ->where('property_portal_role', 'agent')
            ->get();

        foreach ($agents as $agent) {
            $this->suspendWorkspace($agent, $actorId);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<int>  $agentIds
     */
    public function bulkActivate(array $agentIds, ?int $actorId = null): int
    {
        $count = 0;
        $agents = User::query()
            ->whereIn('id', $agentIds)
            ->where('property_portal_role', 'agent')
            ->get();

        foreach ($agents as $agent) {
            $this->activateWorkspace($agent, $actorId);
            $count++;
        }

        return $count;
    }

    /**
     * @param  Collection<int, User>  $agents
     */
    public function exportAgents(Collection $agents, Collection $propertyCounts, Collection $unitCounts, string $format): StreamedResponse
    {
        $meta = $this->summarizeAgents($agents);

        return TabularExport::stream(
            'superadmin-agent-workspaces-'.now()->format('Ymd_His'),
            ['Agent', 'Email', 'Status', 'Subscription', 'Properties', 'Units'],
            function () use ($agents, $propertyCounts, $unitCounts, $meta) {
                foreach ($agents as $agent) {
                    $row = $meta[(int) $agent->id] ?? [];
                    yield [
                        (string) $agent->name,
                        (string) $agent->email,
                        (string) ($row['status']['label'] ?? ''),
                        (string) ($row['subscription'] ?? ''),
                        (string) ((int) ($propertyCounts[$agent->id] ?? 0)),
                        (string) ((int) ($unitCounts[$agent->id] ?? 0)),
                    ];
                }
            },
            $format
        );
    }

    private function isPropertyModuleRevoked(User $agent): bool
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return false;
        }

        $access = $agent->moduleAccesses()->where('module', 'property')->first();

        return $access !== null && $access->normalized_status === UserModuleAccess::STATUS_REVOKED;
    }

    private function setPropertyModuleAccess(User $agent, string $status, ?int $actorId): void
    {
        if (! Schema::hasTable('user_module_accesses')) {
            return;
        }

        UserModuleAccess::query()->updateOrCreate(
            [
                'user_id' => $agent->id,
                'module' => 'property',
            ],
            [
                'status' => $status,
                'approved_by' => $actorId,
                'approved_at' => $status === UserModuleAccess::STATUS_APPROVED ? now() : null,
            ]
        );
    }

    private function upsertSubscriptionStatus(User $agent, string $status): void
    {
        if (! Schema::hasTable('agent_subscriptions')) {
            return;
        }

        $latest = $this->latestSubscription($agent);

        if ($latest) {
            $latest->update(['status' => $status]);

            return;
        }

        $packageId = SubscriptionPackage::query()->active()->ordered()->value('id');
        if (! $packageId) {
            return;
        }

        AgentSubscription::query()->create([
            'user_id' => $agent->id,
            'subscription_package_id' => $packageId,
            'status' => $status,
            'starts_at' => now()->toDateString(),
        ]);
    }
}
