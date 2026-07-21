<?php

namespace App\Console\Commands;

use App\Support\Property\LandlordWorkspaceScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillLandlordAgentScope extends Command
{
    protected $signature = 'property:backfill-landlord-agent-scope
        {--dry : Print what would change without writing}
        {--agent= : Limit backfill to one agent user id}
        {--landlord= : Stamp one landlord to --agent (requires both options)}
        {--assign-orphans-to= : Stamp every unscoped landlord to this agent user id}
        {--release-landlord= : Remove agent visibility for one landlord (super-admin only)}
        {--from-agent= : With --release-landlord, only remove attribution to this agent user id}
        {--keep-property-links : With --release-landlord, do not unlink properties}
        {--inspect-landlord= : Show why a landlord is visible to agents}
        {--list-for-agent= : List landlords visible to one agent user id}
        {--list-agents : List property agent accounts and exit}';

    protected $description = 'Backfill users.agent_user_id for landlords from property links, onboard audit rows, and credential SMS.';

    public function handle(): int
    {
        if ($this->option('list-agents')) {
            return $this->listAgents();
        }

        $listForAgent = $this->option('list-for-agent');
        if (is_numeric($listForAgent)) {
            return $this->listLandlordsForAgent((int) $listForAgent);
        }

        $inspectLandlord = $this->option('inspect-landlord');
        if (is_numeric($inspectLandlord)) {
            return $this->inspectLandlord((int) $inspectLandlord);
        }

        $releaseLandlord = $this->option('release-landlord');
        if (is_numeric($releaseLandlord)) {
            $fromAgent = $this->option('from-agent');
            $fromAgentId = is_numeric($fromAgent) ? (int) $fromAgent : null;

            return $this->releaseSingleLandlord(
                (int) $releaseLandlord,
                $fromAgentId,
                ! (bool) $this->option('keep-property-links'),
                (bool) $this->option('dry'),
            ) ? self::SUCCESS : self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $agentFilter = $this->option('agent');
        $landlordFilter = $this->option('landlord');
        $assignOrphansTo = $this->option('assign-orphans-to');
        $agentId = is_numeric($agentFilter) ? (int) $agentFilter : null;
        $landlordId = is_numeric($landlordFilter) ? (int) $landlordFilter : null;
        $orphanAgentId = is_numeric($assignOrphansTo) ? (int) $assignOrphansTo : null;

        if ($landlordId && ! $agentId) {
            $this->error('Pass --agent when using --landlord.');

            return self::FAILURE;
        }

        if ($landlordId && $agentId) {
            return $this->stampSingleLandlord($landlordId, $agentId, $dry) ? self::SUCCESS : self::FAILURE;
        }

        if ($orphanAgentId) {
            return $this->assignAllOrphans($orphanAgentId, $dry);
        }

        $updatedFromProperties = $this->backfillFromPropertyLinks($dry, $agentId);
        $updatedFromActions = $this->backfillFromPortalActions($dry, $agentId);
        $updatedFromSms = $this->backfillFromCredentialSms($dry, $agentId);
        $orphans = $this->reportOrphanLandlords($agentId);

        $this->newLine();
        $this->info(($dry ? '[DRY RUN] ' : '').'Backfill complete.');
        $this->line('From property links: '.$updatedFromProperties);
        $this->line('From onboard audit rows: '.$updatedFromActions);
        $this->line('From credential SMS logs: '.$updatedFromSms);
        $this->line('Unscoped landlord accounts (super-admin only): '.$orphans);

        if ($orphans > 0) {
            $this->newLine();
            $this->line('Repair all unscoped landlords for one agent (example):');
            $this->line('  php artisan property:backfill-landlord-agent-scope --assign-orphans-to=AGENT_USER_ID');
            $this->line('Repair one landlord:');
            $this->line('  php artisan property:backfill-landlord-agent-scope --landlord=LANDLORD_ID --agent=AGENT_USER_ID');
            $this->line('List agent user ids:');
            $this->line('  php artisan property:backfill-landlord-agent-scope --list-agents');
        }

        if (! Schema::hasColumn('users', 'agent_user_id')) {
            $this->warn('users.agent_user_id is missing. Run migrations, then re-run this command.');
        }

        return self::SUCCESS;
    }

    private function listLandlordsForAgent(int $agentId): int
    {
        $agent = \App\Models\User::query()->find($agentId);
        if (! $agent || (string) ($agent->property_portal_role ?? '') !== 'agent') {
            $this->error('Agent user #'.$agentId.' not found or is not property_portal_role=agent.');

            return self::FAILURE;
        }

        $landlords = LandlordWorkspaceScope::applyToLandlordUsersQuery(
            \App\Models\User::query()->where('property_portal_role', 'landlord'),
            $agent,
        )->orderBy('name')->get(['id', 'name', 'email', 'phone', 'agent_user_id', 'created_at']);

        $this->info('Landlords visible to agent #'.$agentId.' ('.$agent->name.'): '.$landlords->count());

        if ($landlords->isEmpty()) {
            $this->line('None.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($landlords as $landlord) {
            $rows[] = [
                $landlord->id,
                $landlord->name,
                $landlord->email,
                $landlord->phone,
                $this->summarizeLandlordAgentLinks((int) $landlord->id, $agentId),
                $landlord->created_at,
            ];
        }

        $this->table(['ID', 'Name', 'Email', 'Phone', 'Visible because', 'Created'], $rows);

        return self::SUCCESS;
    }

    private function summarizeLandlordAgentLinks(int $landlordId, int $agentId): string
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

    private function listAgents(): int
    {
        if (! Schema::hasTable('users')) {
            $this->error('users table missing.');

            return self::FAILURE;
        }

        $agents = DB::table('users')
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        if ($agents->isEmpty()) {
            $this->warn('No users with property_portal_role=agent found.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Email', 'Phone'], $agents->map(fn ($u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->phone,
        ])->all());

        return self::SUCCESS;
    }

    private function backfillFromPropertyLinks(bool $dry, ?int $agentId): int
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'agent_user_id')
            || ! Schema::hasTable('property_landlord')
            || ! Schema::hasTable('properties')
            || ! Schema::hasColumn('properties', 'agent_user_id')
        ) {
            return 0;
        }

        $pairs = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('u.property_portal_role', 'landlord')
            ->whereNull('u.agent_user_id')
            ->whereNotNull('p.agent_user_id')
            ->when($agentId, fn ($q) => $q->where('p.agent_user_id', $agentId))
            ->groupBy('pl.user_id', 'p.agent_user_id')
            ->selectRaw('pl.user_id as landlord_user_id, p.agent_user_id as agent_user_id')
            ->get();

        $this->line('Property-link landlord/agent pairs to apply: '.$pairs->count());

        if ($dry) {
            return $pairs->count();
        }

        $updated = 0;
        foreach ($pairs as $row) {
            $changed = DB::table('users')
                ->where('id', (int) $row->landlord_user_id)
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => (int) $row->agent_user_id]);
            $updated += (int) $changed;
        }

        return $updated;
    }

    private function backfillFromPortalActions(bool $dry, ?int $agentId): int
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'agent_user_id')
            || ! Schema::hasTable('pm_portal_actions')
        ) {
            return 0;
        }

        $actions = DB::table('pm_portal_actions')
            ->where('action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
            ->where('portal_role', 'agent')
            ->when($agentId, fn ($q) => $q->where('user_id', $agentId))
            ->get(['user_id', 'context']);

        $pairs = [];
        foreach ($actions as $action) {
            $context = is_string($action->context) ? json_decode($action->context, true) : (array) ($action->context ?? []);
            $landlordUserId = (int) ($context['landlord_user_id'] ?? 0);
            $actionAgentId = (int) ($action->user_id ?? 0);
            if ($landlordUserId <= 0 || $actionAgentId <= 0) {
                continue;
            }
            $pairs[$landlordUserId] = $actionAgentId;
        }

        $this->line('Onboard-audit landlord/agent pairs to apply: '.count($pairs));

        if ($dry) {
            return count($pairs);
        }

        $updated = 0;
        foreach ($pairs as $landlordUserId => $actionAgentId) {
            $changed = DB::table('users')
                ->where('id', $landlordUserId)
                ->where('property_portal_role', 'landlord')
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => $actionAgentId]);
            $updated += (int) $changed;
        }

        return $updated;
    }

    private function backfillFromCredentialSms(bool $dry, ?int $agentId): int
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'agent_user_id')
            || ! Schema::hasTable('sms_logs')
            || ! Schema::hasColumn('sms_logs', 'user_id')
        ) {
            return 0;
        }

        $orphans = $this->orphanLandlordQuery()->get(['u.id', 'u.phone', 'u.created_at']);
        $pairs = [];

        foreach ($orphans as $landlord) {
            $phone = trim((string) ($landlord->phone ?? ''));
            if ($phone === '') {
                continue;
            }

            $sms = DB::table('sms_logs')
                ->whereNotNull('user_id')
                ->when($agentId, fn ($q) => $q->where('user_id', $agentId))
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                        ->orWhere('phone', 'like', '%'.substr(preg_replace('/\D+/', '', $phone) ?: $phone, -9));
                })
                ->where('message', 'like', '%landlord portal%')
                ->orderByDesc('id')
                ->first(['user_id']);

            if (! $sms) {
                continue;
            }

            $pairs[(int) $landlord->id] = (int) $sms->user_id;
        }

        $this->line('Credential-SMS landlord/agent pairs to apply: '.count($pairs));

        if ($dry) {
            return count($pairs);
        }

        $updated = 0;
        foreach ($pairs as $landlordUserId => $actionAgentId) {
            $changed = DB::table('users')
                ->where('id', $landlordUserId)
                ->where('property_portal_role', 'landlord')
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => $actionAgentId]);

            if ($changed > 0) {
                app(\App\Services\Property\LandlordPortalOnboardingService::class)
                    ->recordAgentOnboarding(
                        \App\Models\User::query()->findOrFail($landlordUserId),
                        $actionAgentId,
                    );
            }

            $updated += (int) $changed;
        }

        return $updated;
    }

    private function assignAllOrphans(int $agentId, bool $dry): int
    {
        $agent = DB::table('users')
            ->where('id', $agentId)
            ->where('property_portal_role', 'agent')
            ->first();

        if (! $agent) {
            $this->error('Agent user #'.$agentId.' not found or is not property_portal_role=agent.');

            return self::FAILURE;
        }

        $orphans = $this->orphanLandlordQuery()->orderBy('u.name')->get([
            'u.id', 'u.name', 'u.email', 'u.phone', 'u.created_at',
        ]);

        if ($orphans->isEmpty()) {
            $this->info('No unscoped landlord accounts remain.');

            return self::SUCCESS;
        }

        $this->warn(($dry ? '[DRY RUN] Would assign ' : 'Assigning ').$orphans->count().' landlord(s) to agent #'.$agentId.' ('.$agent->name.').');
        $this->table(
            ['ID', 'Name', 'Email', 'Phone', 'Created'],
            $orphans->map(fn ($u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->phone,
                $u->created_at,
            ])->all(),
        );

        if ($dry) {
            return self::SUCCESS;
        }

        $service = app(\App\Services\Property\LandlordPortalOnboardingService::class);
        foreach ($orphans as $orphan) {
            $service->stampAgentOwnership(
                \App\Models\User::query()->findOrFail((int) $orphan->id),
                $agentId,
            );
        }

        $this->info('Assigned '.$orphans->count().' landlord(s) to '.$agent->name.'.');

        return self::SUCCESS;
    }

    private function stampSingleLandlord(int $landlordId, int $agentId, bool $dry): bool
    {
        $landlord = DB::table('users')
            ->where('id', $landlordId)
            ->where('property_portal_role', 'landlord')
            ->first();

        if (! $landlord) {
            $this->error('Landlord user #'.$landlordId.' not found.');

            return false;
        }

        $agent = DB::table('users')
            ->where('id', $agentId)
            ->where('property_portal_role', 'agent')
            ->first();

        if (! $agent) {
            $this->error('Agent user #'.$agentId.' not found.');

            return false;
        }

        if ($dry) {
            $this->info('[DRY RUN] Would stamp landlord #'.$landlordId.' ('.$landlord->name.') to agent #'.$agentId.' ('.$agent->name.').');

            return true;
        }

        app(\App\Services\Property\LandlordPortalOnboardingService::class)
            ->stampAgentOwnership(\App\Models\User::query()->findOrFail($landlordId), $agentId);

        $this->info('Stamped landlord #'.$landlordId.' ('.$landlord->name.') to agent #'.$agentId.' ('.$agent->name.').');

        return true;
    }

    private function releaseSingleLandlord(int $landlordId, ?int $fromAgentId, bool $detachProperties, bool $dry): bool
    {
        $landlord = DB::table('users')
            ->where('id', $landlordId)
            ->where('property_portal_role', 'landlord')
            ->first();

        if (! $landlord) {
            $this->error('Landlord user #'.$landlordId.' not found.');

            return false;
        }

        $reasons = $this->landlordVisibilityReasons($landlordId);
        if ($reasons === []) {
            $this->info('Landlord #'.$landlordId.' ('.$landlord->name.') is already super-admin only.');

            return true;
        }

        $this->warn(($dry ? '[DRY RUN] Would remove agent visibility for ' : 'Removing agent visibility for ')
            .'landlord #'.$landlordId.' ('.$landlord->name.').');
        $this->table(['Reason', 'Detail'], $reasons);

        if ($dry) {
            return true;
        }

        app(\App\Services\Property\LandlordPortalOnboardingService::class)
            ->releaseAgentOwnership(
                \App\Models\User::query()->findOrFail($landlordId),
                $fromAgentId,
                $detachProperties,
            );

        $this->info('Landlord #'.$landlordId.' is now super-admin only.');

        return true;
    }

    private function inspectLandlord(int $landlordId): int
    {
        $landlord = DB::table('users')
            ->where('id', $landlordId)
            ->where('property_portal_role', 'landlord')
            ->first();

        if (! $landlord) {
            $this->error('Landlord user #'.$landlordId.' not found.');

            return self::FAILURE;
        }

        $this->info('Landlord #'.$landlordId.': '.$landlord->name);
        $this->line('agent_user_id: '.(Schema::hasColumn('users', 'agent_user_id') ? ($landlord->agent_user_id ?? 'null') : 'column missing'));

        $reasons = $this->landlordVisibilityReasons($landlordId);
        if ($reasons === []) {
            $this->info('Visible to super admin only.');

            return self::SUCCESS;
        }

        $this->table(['Reason', 'Detail'], $reasons);

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function landlordVisibilityReasons(int $landlordId): array
    {
        $reasons = [];

        if (Schema::hasColumn('users', 'agent_user_id')) {
            $agentUserId = DB::table('users')->where('id', $landlordId)->value('agent_user_id');
            if ($agentUserId) {
                $agent = DB::table('users')->where('id', $agentUserId)->first();
                $reasons[] = [
                    'users.agent_user_id',
                    '#'.$agentUserId.' '.($agent->name ?? 'unknown'),
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
                    'pm_portal_actions',
                    'row #'.$action->id.' agent #'.$action->user_id.' '.$action->agent_name.' @ '.$action->created_at,
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
                    'property_landlord',
                    'property #'.$link->property_id.' '.$link->property_name
                        .' (agent #'.($link->agent_user_id ?? '?').' '.($link->agent_name ?? 'unassigned')
                        .', '.$link->ownership_percent.'%)',
                ];
            }
        }

        return $reasons;
    }

    private function reportOrphanLandlords(?int $agentId): int
    {
        if ($agentId || ! Schema::hasTable('users')) {
            return 0;
        }

        $orphans = $this->orphanLandlordQuery()
            ->orderBy('u.created_at')
            ->get(['u.id', 'u.name', 'u.email', 'u.phone', 'u.created_at']);

        $count = $orphans->count();
        if ($count > 0) {
            $this->newLine();
            $this->warn('Unscoped landlord accounts (visible to super admin only):');
            $this->table(
                ['ID', 'Name', 'Email', 'Phone', 'Created'],
                $orphans->map(fn ($u) => [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->phone,
                    $u->created_at,
                ])->all(),
            );
        }

        return $count;
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
