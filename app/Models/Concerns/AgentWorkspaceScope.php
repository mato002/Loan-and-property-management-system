<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable agent-workspace scoping helpers.
 *
 * Behaviour matches the existing scope on PmInvoice/PmPayment/PmTenant/etc.:
 *  - super admins (is_super_admin) see everything (intentional);
 *  - non-agent property portal users (e.g. guest, landlord, tenant) are
 *    not subjected to this scope (their own scopes apply elsewhere);
 *  - only an authenticated user with property_portal_role='agent' is
 *    restricted to rows that belong to a property they own
 *    (`properties.agent_user_id = auth()->id()`).
 *
 * Each public helper returns silently when no agent restriction applies,
 * so callers can drop the helper into a model `booted()` block without
 * extra branching.
 */
final class AgentWorkspaceScope
{
    /**
     * Restrict a query to rows whose `property_unit_id` belongs to a property
     * owned by the current agent. Used by water readings, utility charges,
     * unit movements, and similar unit-anchored data.
     */
    public static function applyByPropertyUnit(Builder $query, string $tableName, string $unitColumn = 'property_unit_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }
        if (! Schema::hasColumn('properties', 'agent_user_id')) {
            return;
        }

        $userId = (int) Auth::id();
        $qualifiedColumn = $tableName.'.'.$unitColumn;

        $query->whereIn($qualifiedColumn, function ($sub) use ($userId) {
            $sub->select('pu.id')
                ->from('property_units as pu')
                ->join('properties as p', 'p.id', '=', 'pu.property_id')
                ->where('p.agent_user_id', $userId);
        });
    }

    /**
     * Restrict a query to rows whose `property_id` belongs to a property
     * in the current agent's workspace.
     */
    public static function applyByProperty(Builder $query, string $tableName, string $propertyColumn = 'property_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }
        if (! Schema::hasColumn('properties', 'agent_user_id')) {
            return;
        }

        $userId = (int) Auth::id();
        $qualifiedColumn = $tableName.'.'.$propertyColumn;

        $query->whereIn($qualifiedColumn, function ($sub) use ($userId) {
            $sub->select('id')->from('properties')->where('agent_user_id', $userId);
        });
    }

    /**
     * Restrict a query to rows whose `pm_tenant_id` belongs to the current
     * agent's tenant workspace.
     */
    public static function applyByTenant(Builder $query, string $tableName, string $tenantColumn = 'pm_tenant_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }
        if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return;
        }

        $userId = (int) Auth::id();
        $qualifiedColumn = $tableName.'.'.$tenantColumn;

        $query->whereIn($qualifiedColumn, function ($sub) use ($userId) {
            $sub->select('id')->from('pm_tenants')->where('agent_user_id', $userId);
        });
    }

    /**
     * Restrict a query to rows the current agent created themselves.
     * Used for message batches, message envelopes, communication exports,
     * etc. where ownership is recorded directly on the row.
     */
    public static function applyByCreator(Builder $query, string $tableName, string $creatorColumn = 'created_by_user_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }

        $userId = (int) Auth::id();
        $query->where($tableName.'.'.$creatorColumn, $userId);
    }

    /**
     * Communications log visibility for agents: own sends plus system/cron
     * SMS/email to tenants in the agent workspace (matched by phone or email).
     */
    public static function applyByMessageLog(Builder $query, string $tableName = 'pm_message_logs'): void
    {
        if (! self::shouldApply()) {
            return;
        }

        $userId = (int) Auth::id();
        $toColumn = $tableName.'.to_address';

        $query->where(function (Builder $scope) use ($tableName, $userId, $toColumn) {
            $scope->where($tableName.'.user_id', $userId)
                ->orWhere(function (Builder $system) use ($tableName, $userId, $toColumn) {
                    $system->whereNull($tableName.'.user_id')
                        ->whereExists(function ($sub) use ($userId, $toColumn) {
                            $sub->selectRaw('1')
                                ->from('pm_tenants as t')
                                ->where(function ($tenantScope) use ($userId) {
                                    self::constrainAgentTenantAlias($tenantScope, 't', $userId);
                                })
                                ->where(function ($contact) use ($toColumn) {
                                    $contact->where(function ($email) use ($toColumn) {
                                        $email->whereNotNull('t.email')
                                            ->where('t.email', '!=', '')
                                            ->whereColumn('t.email', $toColumn);
                                    });

                                    if (Schema::hasColumn('pm_tenants', 'phone')) {
                                        $contact->orWhere(function ($phone) use ($toColumn) {
                                            $phone->whereNotNull('t.phone')
                                                ->where('t.phone', '!=', '')
                                                ->whereRaw(self::phoneDigitsMatchSql('t.phone', $toColumn));
                                        });
                                    }
                                });
                        });
                });
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function constrainAgentTenantAlias($query, string $alias, int $agentUserId): void
    {
        if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where($alias.'.agent_user_id', $agentUserId);

            return;
        }

        $query->where(function ($tenantQuery) use ($alias, $agentUserId) {
            $tenantQuery->whereExists(function ($sub) use ($alias, $agentUserId) {
                $sub->selectRaw('1')
                    ->from('pm_invoices as i')
                    ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereColumn('i.pm_tenant_id', $alias.'.id')
                    ->where('p.agent_user_id', $agentUserId);
            })->orWhereExists(function ($sub) use ($alias, $agentUserId) {
                $sub->selectRaw('1')
                    ->from('pm_leases as l')
                    ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
                    ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereColumn('l.pm_tenant_id', $alias.'.id')
                    ->where('p.agent_user_id', $agentUserId);
            });
        });
    }

    private static function phoneDigitsMatchSql(string $leftColumn, string $rightColumn): string
    {
        $normalize = static fn (string $column): string => "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', ''), '/', ''), 9)";

        $left = $normalize($leftColumn);
        $right = $normalize($rightColumn);

        return "{$left} <> '' AND {$left} = {$right}";
    }

    /**
     * Restrict a query to rows whose parent `pm_messages` row was created
     * by the current agent. Used for message recipients, deliveries, and
     * attachments, which inherit ownership from their parent envelope.
     */
    public static function applyByMessageParent(Builder $query, string $tableName, string $messageIdColumn = 'message_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }
        if (! Schema::hasColumn('pm_messages', 'created_by_user_id')) {
            return;
        }

        $userId = (int) Auth::id();
        $qualifiedColumn = $tableName.'.'.$messageIdColumn;

        $query->whereIn($qualifiedColumn, function ($sub) use ($userId) {
            $sub->select('id')->from('pm_messages')->where('created_by_user_id', $userId);
        });
    }

    /**
     * Restrict a query to rows whose parent `pm_conversations` row is
     * either about a tenant the agent owns or is assigned to that agent.
     */
    public static function applyByConversationParent(Builder $query, string $tableName, string $conversationIdColumn = 'conversation_id'): void
    {
        if (! self::shouldApply()) {
            return;
        }

        $userId = (int) Auth::id();
        $qualifiedColumn = $tableName.'.'.$conversationIdColumn;
        $hasTenantAgent = Schema::hasColumn('pm_tenants', 'agent_user_id');

        $query->whereIn($qualifiedColumn, function ($sub) use ($userId, $hasTenantAgent) {
            $sub->select('id')->from('pm_conversations')
                ->where(function ($scope) use ($userId, $hasTenantAgent) {
                    $scope->where('assigned_to_user_id', $userId);
                    if ($hasTenantAgent) {
                        $scope->orWhereExists(function ($t) use ($userId) {
                            $t->selectRaw('1')
                                ->from('pm_tenants as ct')
                                ->whereColumn('ct.id', 'pm_conversations.pm_tenant_id')
                                ->where('ct.agent_user_id', $userId);
                        });
                    }
                });
        });
    }

    /**
     * True only when the active user is a property portal "agent" and not
     * a super admin. This is the single source of truth used by every
     * applyByXxx helper above.
     */
    public static function shouldApply(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if (($user->is_super_admin ?? false) === true) {
            return false;
        }

        return (string) ($user->property_portal_role ?? '') === 'agent';
    }
}
