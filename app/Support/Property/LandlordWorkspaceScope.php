<?php

namespace App\Support\Property;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landlord list visibility for property agents.
 *
 * Agents see landlords they onboarded (users.agent_user_id and/or pm_portal_actions)
 * and landlords linked to properties in their workspace.
 */
final class LandlordWorkspaceScope
{
    public const ONBOARD_ACTION_KEY = 'landlord_onboarded';

    public static function isAgentActor(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }
        if (($actor->is_super_admin ?? false) === true) {
            return false;
        }

        $role = trim((string) ($actor->property_portal_role ?? ''));
        if ($role === 'agent') {
            return true;
        }
        if (in_array($role, ['landlord', 'tenant'], true)) {
            return false;
        }

        // Staff users without an explicit portal role still operate in the agent UI.
        return $role === '';
    }

    public static function shouldRestrict(?User $actor): bool
    {
        return self::isAgentActor($actor);
    }

    public static function creatingAgentUserId(?User $actor): ?int
    {
        if (! self::shouldRestrict($actor)) {
            return null;
        }

        return (int) $actor->id;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function applyToLandlordUsersQuery(Builder $query, ?User $actor): Builder
    {
        if (! self::shouldRestrict($actor)) {
            return $query;
        }

        $agentId = (int) $actor->id;

        $hasUserAgentColumn = Schema::hasColumn('users', 'agent_user_id');
        $hasOnboardAudit = Schema::hasTable('pm_portal_actions');
        $hasPropertyLinks = Schema::hasTable('property_landlord')
            && Schema::hasTable('properties')
            && Schema::hasColumn('properties', 'agent_user_id');

        if (! $hasUserAgentColumn && ! $hasOnboardAudit && ! $hasPropertyLinks) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($agentId, $hasUserAgentColumn, $hasOnboardAudit, $hasPropertyLinks) {
            if ($hasUserAgentColumn) {
                $scoped->where('users.agent_user_id', $agentId);
            }

            if ($hasOnboardAudit) {
                $method = $hasUserAgentColumn ? 'orWhereExists' : 'whereExists';
                $scoped->{$method}(function ($sub) use ($agentId) {
                    $sub->selectRaw('1')
                        ->from('pm_portal_actions as pa')
                        ->where('pa.user_id', $agentId)
                        ->where('pa.action_key', self::ONBOARD_ACTION_KEY)
                        ->where('pa.portal_role', 'agent')
                        ->whereRaw(self::portalActionLandlordMatchSql('pa', 'users.id'));
                });
            }

            if ($hasPropertyLinks) {
                $method = ($hasUserAgentColumn || $hasOnboardAudit) ? 'orWhereExists' : 'whereExists';
                $scoped->{$method}(function ($sub) use ($agentId) {
                    $sub->selectRaw('1')
                        ->from('property_landlord as pl')
                        ->join('properties as p', 'p.id', '=', 'pl.property_id')
                        ->whereColumn('pl.user_id', 'users.id')
                        ->where('p.agent_user_id', $agentId);
                });
            }
        });
    }

    public static function landlordVisibleToActor(User $landlord, ?User $actor): bool
    {
        if ((string) $landlord->property_portal_role !== 'landlord') {
            return false;
        }

        if (! self::shouldRestrict($actor)) {
            return true;
        }

        $agentId = (int) $actor->id;

        if (
            Schema::hasColumn('users', 'agent_user_id')
            && (int) ($landlord->agent_user_id ?? 0) === $agentId
        ) {
            return true;
        }

        if (Schema::hasTable('pm_portal_actions')) {
            $onboarded = DB::table('pm_portal_actions as pa')
                ->where('pa.user_id', $agentId)
                ->where('pa.action_key', self::ONBOARD_ACTION_KEY)
                ->where('pa.portal_role', 'agent')
                ->whereRaw(self::portalActionLandlordMatchSql('pa', (string) (int) $landlord->id))
                ->exists();
            if ($onboarded) {
                return true;
            }
        }

        if (! Schema::hasTable('property_landlord') || ! Schema::hasTable('properties')) {
            return false;
        }

        return DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->where('pl.user_id', $landlord->id)
            ->where('p.agent_user_id', $agentId)
            ->exists();
    }

    /**
     * SQL fragment matching pm_portal_actions.context.landlord_user_id to a landlord user id.
     */
    private static function portalActionLandlordMatchSql(string $alias, string $landlordIdExpression): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "CAST(json_extract({$alias}.context, '$.landlord_user_id') AS INTEGER) = {$landlordIdExpression}";
        }

        return "CAST(JSON_UNQUOTE(JSON_EXTRACT({$alias}.context, '$.landlord_user_id')) AS UNSIGNED) = {$landlordIdExpression}";
    }
}
