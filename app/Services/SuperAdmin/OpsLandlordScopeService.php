<?php

namespace App\Services\SuperAdmin;

use App\Models\User;
use App\Services\Property\LandlordPortalOnboardingService;
use App\Support\Property\LandlordWorkspaceScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OpsLandlordScopeService
{
    public function __construct(
        private LandlordPortalOnboardingService $onboarding,
    ) {}

    /**
     * @return Collection<int, object{id: int, name: string, email: ?string, phone: ?string}>
     */
    public function listAgents(): Collection
    {
        return DB::table('users')
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);
    }

    /**
     * @return Collection<int, User>
     */
    public function listLandlordsForAgent(int $agentId): Collection
    {
        $agent = User::query()->find($agentId);
        if (! $agent) {
            return collect();
        }

        return LandlordWorkspaceScope::applyToLandlordUsersQuery(
            User::query()->where('property_portal_role', 'landlord'),
            $agent,
        )->orderBy('name')->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function listOrphanLandlords(): Collection
    {
        return $this->orphanLandlordQuery()
            ->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.email', 'u.phone', 'u.created_at']);
    }

    /**
     * @return list<array{reason: string, detail: string}>
     */
    public function inspectLandlord(int $landlordId): array
    {
        $reasons = [];

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $agentUserId = (int) (DB::table('users')->where('id', $landlordId)->value('agent_user_id') ?? 0);
            if ($agentUserId > 0) {
                $agent = DB::table('users')->where('id', $agentUserId)->first();
                $reasons[] = [
                    'reason' => 'users.agent_user_id',
                    'detail' => '#'.$agentUserId.' '.($agent->name ?? 'unknown'),
                ];
            }
        }

        if (Schema::hasTable('pm_portal_actions')) {
            $actions = DB::table('pm_portal_actions as pa')
                ->join('users as a', 'a.id', '=', 'pa.user_id')
                ->where('pa.action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
                ->where('pa.portal_role', 'agent')
                ->where('pa.context->landlord_user_id', $landlordId)
                ->get(['pa.id', 'pa.user_id', 'a.name as agent_name', 'pa.created_at']);

            foreach ($actions as $action) {
                $reasons[] = [
                    'reason' => 'pm_portal_actions',
                    'detail' => 'row #'.$action->id.' agent #'.$action->user_id.' '.$action->agent_name.' @ '.$action->created_at,
                ];
            }
        }

        if (Schema::hasTable('property_landlord') && Schema::hasTable('properties')) {
            $links = DB::table('property_landlord as pl')
                ->join('properties as p', 'p.id', '=', 'pl.property_id')
                ->leftJoin('users as a', 'a.id', '=', 'p.agent_user_id')
                ->where('pl.user_id', $landlordId)
                ->get(['p.id as property_id', 'p.name as property_name', 'p.agent_user_id', 'a.name as agent_name', 'pl.ownership_percent']);

            foreach ($links as $link) {
                $reasons[] = [
                    'reason' => 'property_landlord',
                    'detail' => 'property #'.$link->property_id.' '.$link->property_name
                        .' (agent #'.($link->agent_user_id ?? '?').' '.($link->agent_name ?? 'unassigned')
                        .', '.$link->ownership_percent.'%)',
                ];
            }
        }

        return $reasons;
    }

    public function summarizeLandlordAgentLinks(int $landlordId, int $agentId): string
    {
        $parts = [];

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $ownerId = (int) (DB::table('users')->where('id', $landlordId)->value('agent_user_id') ?? 0);
            if ($ownerId === $agentId) {
                $parts[] = 'stamped owner';
            }
        }

        if (Schema::hasTable('pm_portal_actions')) {
            $audited = DB::table('pm_portal_actions')
                ->where('user_id', $agentId)
                ->where('action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
                ->where('portal_role', 'agent')
                ->where('context->landlord_user_id', $landlordId)
                ->exists();
            if ($audited) {
                $parts[] = 'onboard audit';
            }
        }

        if (Schema::hasTable('property_landlord') && Schema::hasTable('properties')) {
            $properties = DB::table('property_landlord as pl')
                ->join('properties as p', 'p.id', '=', 'pl.property_id')
                ->where('pl.user_id', $landlordId)
                ->where('p.agent_user_id', $agentId)
                ->pluck('p.name')
                ->all();
            if ($properties !== []) {
                $parts[] = 'property: '.implode(', ', $properties);
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : 'matched by scope';
    }

    public function assignLandlordToAgent(int $landlordId, int $agentId): User
    {
        $landlord = User::query()
            ->where('id', $landlordId)
            ->where('property_portal_role', 'landlord')
            ->firstOrFail();

        $agent = User::query()
            ->where('id', $agentId)
            ->where('property_portal_role', 'agent')
            ->firstOrFail();

        $this->onboarding->stampAgentOwnership($landlord, (int) $agent->id);

        return $landlord->fresh();
    }

    public function releaseLandlord(int $landlordId, ?int $fromAgentId = null, bool $detachProperties = true): User
    {
        $landlord = User::query()
            ->where('id', $landlordId)
            ->where('property_portal_role', 'landlord')
            ->firstOrFail();

        $this->onboarding->releaseAgentOwnership($landlord, $fromAgentId, $detachProperties);

        return $landlord->fresh();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function orphanLandlordQuery()
    {
        $query = DB::table('users as u')
            ->where('u.property_portal_role', 'landlord');

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $query->whereNull('u.agent_user_id');
        }

        if (Schema::hasTable('property_landlord') && Schema::hasTable('properties') && Schema::hasColumn('properties', 'agent_user_id')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('property_landlord as pl')
                    ->join('properties as p', 'p.id', '=', 'pl.property_id')
                    ->whereColumn('pl.user_id', 'u.id')
                    ->whereNotNull('p.agent_user_id');
            });
        }

        if (Schema::hasTable('pm_portal_actions')) {
            $driver = DB::connection()->getDriverName();
            $landlordIdSql = $driver === 'sqlite'
                ? "CAST(json_extract(pa.context, '$.landlord_user_id') AS INTEGER)"
                : "CAST(JSON_UNQUOTE(JSON_EXTRACT(pa.context, '$.landlord_user_id')) AS UNSIGNED)";

            $query->whereNotExists(function ($sub) use ($landlordIdSql) {
                $sub->selectRaw('1')
                    ->from('pm_portal_actions as pa')
                    ->where('pa.action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
                    ->where('pa.portal_role', 'agent')
                    ->whereRaw("{$landlordIdSql} = u.id");
            });
        }

        return $query;
    }
}
