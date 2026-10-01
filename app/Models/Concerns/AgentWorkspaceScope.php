<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    /** @var array<int, int> */
    private static array $resolvedAgentIds = [];

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

        $userId = self::scopedAgentId();
        if ($userId === null) {
            return;
        }
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

        $userId = self::scopedAgentId();
        if ($userId === null) {
            return;
        }
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

        $userId = self::scopedAgentId();
        if ($userId === null) {
            return;
        }
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

        $userId = $creatorColumn === 'agent_user_id'
            ? (self::scopedAgentId() ?? (int) Auth::id())
            : (int) Auth::id();
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

        $staffId = (int) Auth::id();
        $agentId = self::scopedAgentId() ?? $staffId;
        $toColumn = $tableName.'.to_address';

        $query->where(function (Builder $scope) use ($tableName, $staffId, $agentId, $toColumn) {
            $scope->where($tableName.'.user_id', $staffId)
                ->orWhere(function (Builder $system) use ($tableName, $agentId, $toColumn) {
                    $system->whereNull($tableName.'.user_id')
                        ->whereExists(function ($sub) use ($agentId, $toColumn) {
                            $sub->selectRaw('1')
                                ->from('pm_tenants as t')
                                ->where(function ($tenantScope) use ($agentId) {
                                    self::constrainAgentTenantAlias($tenantScope, 't', $agentId);
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

        $staffId = (int) Auth::id();
        $agentId = self::scopedAgentId() ?? $staffId;
        $qualifiedColumn = $tableName.'.'.$conversationIdColumn;
        $hasTenantAgent = Schema::hasColumn('pm_tenants', 'agent_user_id');

        $query->whereIn($qualifiedColumn, function ($sub) use ($staffId, $agentId, $hasTenantAgent) {
            $sub->select('id')->from('pm_conversations')
                ->where(function ($scope) use ($staffId, $agentId, $hasTenantAgent) {
                    $scope->where('assigned_to_user_id', $staffId);
                    if ($hasTenantAgent) {
                        $scope->orWhereExists(function ($t) use ($agentId) {
                            $t->selectRaw('1')
                                ->from('pm_tenants as ct')
                                ->whereColumn('ct.id', 'pm_conversations.pm_tenant_id')
                                ->where('ct.agent_user_id', $agentId);
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

    /**
     * Company workspace the current user should see.
     * Agency owners get their own id; HR staff get the company (`employees.agent_user_id`) they belong to.
     * Super admins and non-agent portal users return null (no agent workspace filter).
     */
    public static function currentAgentUserId(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }
        if (($user->is_super_admin ?? false) === true) {
            return null;
        }
        if ((string) ($user->property_portal_role ?? '') !== 'agent') {
            return null;
        }

        $uid = (int) $user->id;
        if (array_key_exists($uid, self::$resolvedAgentIds)) {
            return self::$resolvedAgentIds[$uid];
        }

        $companyId = self::companyAgentIdForStaff($user);
        $resolved = $companyId > 0 ? $companyId : $uid;
        self::$resolvedAgentIds[$uid] = $resolved;

        return $resolved;
    }

    /**
     * Passion Homes (or any agency) employee logins keep property_portal_role=agent,
     * but their portfolio belongs to employees.agent_user_id, not their own user id.
     */
    public static function companyAgentIdForStaff(User $user): int
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'agent_user_id')) {
            return 0;
        }

        $uid = (int) $user->id;
        $email = strtolower(trim((string) $user->email));
        $query = DB::table('employees')
            ->where('agent_user_id', '>', 0)
            ->where('agent_user_id', '!=', $uid)
            ->where(function ($inner) use ($uid, $email): void {
                $inner->where('user_id', $uid);
                if ($email !== '' && Schema::hasColumn('employees', 'email')) {
                    $inner->orWhere(function ($byEmail) use ($email, $uid): void {
                        $byEmail->whereRaw('LOWER(email) = ?', [$email])
                            ->where(function ($unlinked) use ($uid): void {
                                $unlinked->whereNull('user_id')->orWhere('user_id', $uid);
                            });
                    });
                }
            })
            ->orderByDesc('id');

        $row = $query->first(['id', 'user_id', 'agent_user_id']);
        if (! $row) {
            return 0;
        }

        if (empty($row->user_id) && Schema::hasColumn('employees', 'user_id')) {
            DB::table('employees')->where('id', $row->id)->whereNull('user_id')->update(['user_id' => $uid]);
        }

        return (int) $row->agent_user_id;
    }

    public static function staffUserIds(): array
    {
        if (! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'user_id')) {
            return [];
        }

        $linked = DB::table('employees')
            ->whereNotNull('user_id')
            ->where('agent_user_id', '>', 0)
            ->whereColumn('agent_user_id', '!=', 'user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! Schema::hasColumn('employees', 'email')) {
            return $linked;
        }

        $emails = DB::table('employees')
            ->where('agent_user_id', '>', 0)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($emails === []) {
            return $linked;
        }

        $byEmail = DB::table('users')
            ->where('property_portal_role', 'agent')
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $companyIds = DB::table('employees')->where('agent_user_id', '>', 0)->pluck('agent_user_id')->map(fn ($id) => (int) $id)->all();

        return array_values(array_unique(array_merge(
            $linked,
            array_values(array_diff($byEmail, $companyIds)),
        )));
    }

    private static function scopedAgentId(): ?int
    {
        return self::currentAgentUserId();
    }
}
