<?php

namespace App\Support\SuperAdmin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Places each login in the platform structure: operator, agent company, or a person under a company.
 */
class PlatformPeopleDirectory
{
    /** @var array<string, string> */
    public const CATEGORIES = [
        'platform' => 'Platform operators',
        'agent' => 'Agent companies',
        'employee' => 'Employees',
        'landlord' => 'Landlords',
        'tenant' => 'Tenants',
        'loan' => 'Loan staff',
        'unplaced' => 'Not placed',
    ];

    public function applyFilters(Builder $query, string $category, int $companyId): void
    {
        if (isset(self::CATEGORIES[$category])) {
            $this->constrainCategory($query, $category);
        }

        if ($companyId > 0) {
            $this->constrainCompany($query, $companyId);
        }
    }

    public function order(Builder $query): void
    {
        $query->orderByRaw(
            "CASE
                WHEN is_super_admin = 1 THEN 0
                WHEN property_portal_role = 'agent' THEN 1
                WHEN property_portal_role = 'landlord' THEN 3
                WHEN property_portal_role = 'tenant' THEN 4
                WHEN loan_role IS NOT NULL AND loan_role != '' THEN 5
                ELSE 2
            END"
        )->orderBy('name');
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function decorate(Collection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $ids = $users->map(fn (User $user) => (int) $user->id)->all();
        $employees = $this->employeesByUserId($ids);
        $landlords = $this->agentIdsForLandlords($ids);
        $tenants = $this->agentIdsForTenants($ids);

        $agentIds = collect($employees)->pluck('agent_user_id')
            ->merge(collect($landlords)->flatten())
            ->merge(collect($tenants)->flatten())
            ->merge($users->filter(fn (User $user) => $user->property_portal_role === 'agent')->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $companyNames = $this->companyNames($agentIds);

        foreach ($users as $user) {
            $placement = $this->placementFor($user, $employees, $landlords, $tenants, $companyNames);
            $user->setAttribute('directory_category', $placement['category']);
            $user->setAttribute('directory_label', $placement['label']);
            $user->setAttribute('directory_company', $placement['company']);
            $user->setAttribute('directory_company_id', $placement['company_id']);
            $user->setAttribute('directory_note', $placement['note']);
        }
    }

    /**
     * @return Collection<int, array{id:int,label:string}>
     */
    public function companyOptions(): Collection
    {
        $agents = User::query()
            ->where('property_portal_role', 'agent')
            ->orderBy('name')
            ->get(['id', 'name']);

        $names = $this->companyNames($agents->pluck('id'));

        return $agents->map(function (User $agent) use ($names) {
            $company = trim((string) ($names[(int) $agent->id] ?? ''));
            $label = $company !== '' && strcasecmp($company, (string) $agent->name) !== 0
                ? $company.' · '.$agent->name
                : ($company !== '' ? $company : (string) $agent->name);

            return ['id' => (int) $agent->id, 'label' => $label];
        })->values();
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $base = User::query();

        return [
            'platform' => (clone $base)->where('is_super_admin', true)->count(),
            'agent' => (clone $base)->where('is_super_admin', false)->where('property_portal_role', 'agent')->count(),
            'employee' => $this->countWhereCategory('employee'),
            'landlord' => (clone $base)->where('is_super_admin', false)->where('property_portal_role', 'landlord')->count(),
            'tenant' => (clone $base)->where('is_super_admin', false)->where('property_portal_role', 'tenant')->count(),
        ];
    }

    private function constrainCategory(Builder $query, string $category): void
    {
        match ($category) {
            'platform' => $query->where('is_super_admin', true),
            'agent' => $query->where('is_super_admin', false)->where('property_portal_role', 'agent'),
            'employee' => Schema::hasTable('employees')
                ? $query->where('is_super_admin', false)
                    ->where(function (Builder $inner): void {
                        $inner->whereNull('property_portal_role')->orWhere('property_portal_role', '!=', 'agent');
                    })
                    ->whereIn('id', function ($sub): void {
                        $sub->select('user_id')->from('employees')->whereNotNull('user_id');
                    })
                : $query->whereRaw('1 = 0'),
            'landlord' => $query->where('is_super_admin', false)->where('property_portal_role', 'landlord'),
            'tenant' => $query->where('is_super_admin', false)->where('property_portal_role', 'tenant'),
            'loan' => $query->where('is_super_admin', false)
                ->whereNull('property_portal_role')
                ->whereNotNull('loan_role')
                ->where('loan_role', '!=', ''),
            'unplaced' => $query->where('is_super_admin', false)
                ->whereNull('property_portal_role')
                ->where(function (Builder $inner): void {
                    $inner->whereNull('loan_role')->orWhere('loan_role', '');
                })
                ->when(Schema::hasTable('employees'), function (Builder $inner): void {
                    $inner->whereNotIn('id', function ($sub): void {
                        $sub->select('user_id')->from('employees')->whereNotNull('user_id');
                    });
                }),
            default => null,
        };
    }

    private function constrainCompany(Builder $query, int $companyId): void
    {
        $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('id', $companyId);

            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'user_id')) {
                $inner->orWhereIn('id', function ($sub) use ($companyId): void {
                    $sub->select('user_id')->from('employees')->where('agent_user_id', $companyId)->whereNotNull('user_id');
                });
            }

            if (Schema::hasTable('property_landlord') && Schema::hasTable('properties') && Schema::hasColumn('properties', 'agent_user_id')) {
                $inner->orWhereIn('id', function ($sub) use ($companyId): void {
                    $sub->select('property_landlord.user_id')
                        ->from('property_landlord')
                        ->join('properties', 'properties.id', '=', 'property_landlord.property_id')
                        ->where('properties.agent_user_id', $companyId);
                });
            }

            if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'agent_user_id') && Schema::hasColumn('pm_tenants', 'user_id')) {
                $inner->orWhereIn('id', function ($sub) use ($companyId): void {
                    $sub->select('user_id')->from('pm_tenants')->where('agent_user_id', $companyId)->whereNotNull('user_id');
                });
            }
        });
    }

    private function countWhereCategory(string $category): int
    {
        if ($category === 'employee' && ! Schema::hasTable('employees')) {
            return 0;
        }

        $query = User::query();
        $this->constrainCategory($query, $category);

        return $query->count();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{agent_user_id:int, job_title:string}>
     */
    private function employeesByUserId(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('employees') || ! Schema::hasColumn('employees', 'user_id')) {
            return [];
        }

        $rows = DB::table('employees')
            ->whereIn('user_id', $ids)
            ->orderByDesc('id')
            ->get(['user_id', 'agent_user_id', 'job_title']);

        $mapped = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            if (isset($mapped[$userId])) {
                continue;
            }
            $mapped[$userId] = [
                'agent_user_id' => (int) ($row->agent_user_id ?? 0),
                'job_title' => trim((string) ($row->job_title ?? '')),
            ];
        }

        return $mapped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function agentIdsForLandlords(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('property_landlord') || ! Schema::hasTable('properties') || ! Schema::hasColumn('properties', 'agent_user_id')) {
            return [];
        }

        $rows = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->whereIn('pl.user_id', $ids)
            ->whereNotNull('p.agent_user_id')
            ->groupBy('pl.user_id', 'p.agent_user_id')
            ->select('pl.user_id', 'p.agent_user_id', DB::raw('COUNT(*) as properties'))
            ->orderByDesc('properties')
            ->get();

        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(int) $row->user_id][] = (int) $row->agent_user_id;
        }

        return $mapped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<int>>
     */
    private function agentIdsForTenants(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'user_id') || ! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return [];
        }

        $rows = DB::table('pm_tenants')
            ->whereIn('user_id', $ids)
            ->whereNotNull('agent_user_id')
            ->get(['user_id', 'agent_user_id']);

        $mapped = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $agentId = (int) $row->agent_user_id;
            if (! in_array($agentId, $mapped[$userId] ?? [], true)) {
                $mapped[$userId][] = $agentId;
            }
        }

        return $mapped;
    }

    /**
     * @param  Collection<int, int>  $agentIds
     * @return array<int, string>
     */
    private function companyNames(Collection $agentIds): array
    {
        $agentIds = $agentIds->map(fn ($id) => (int) $id)->filter(fn (int $id) => $id > 0)->unique()->values();
        if ($agentIds->isEmpty()) {
            return [];
        }

        $users = User::query()->whereIn('id', $agentIds)->pluck('name', 'id');
        $branded = [];
        if (Schema::hasTable('property_portal_settings') && Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
            $branded = DB::table('property_portal_settings')
                ->where('key', 'company_name')
                ->whereIn('agent_user_id', $agentIds)
                ->pluck('value', 'agent_user_id')
                ->all();
        }

        $names = [];
        foreach ($agentIds as $agentId) {
            $company = trim((string) ($branded[$agentId] ?? ''));
            $person = trim((string) ($users[$agentId] ?? ''));
            $names[$agentId] = $company !== '' ? $company : ($person !== '' ? $person : 'Agent #'.$agentId);
        }

        return $names;
    }

    /**
     * @param  array<int, array{agent_user_id:int, job_title:string}>  $employees
     * @param  array<int, list<int>>  $landlords
     * @param  array<int, list<int>>  $tenants
     * @param  array<int, string>  $companyNames
     * @return array{category:string, label:string, company:string, company_id:?int, note:string}
     */
    private function placementFor(User $user, array $employees, array $landlords, array $tenants, array $companyNames): array
    {
        $id = (int) $user->id;

        if ((bool) $user->is_super_admin) {
            return [
                'category' => 'platform',
                'label' => 'Platform operator',
                'company' => 'Platform',
                'company_id' => null,
                'note' => 'Runs the platform. Not inside an agent company.',
            ];
        }

        if ((string) $user->property_portal_role === 'agent') {
            $company = $companyNames[$id] ?? (string) $user->name;

            return [
                'category' => 'agent',
                'label' => 'Agent company',
                'company' => $company,
                'company_id' => $id,
                'note' => strcasecmp($company, (string) $user->name) !== 0 ? 'Company account · '.$user->name : 'Company account',
            ];
        }

        if (isset($employees[$id])) {
            $agentId = (int) $employees[$id]['agent_user_id'];
            $title = $employees[$id]['job_title'];

            return [
                'category' => 'employee',
                'label' => 'Employee',
                'company' => $agentId > 0 ? ($companyNames[$agentId] ?? 'Agent #'.$agentId) : 'Company not set',
                'company_id' => $agentId > 0 ? $agentId : null,
                'note' => $title !== '' ? $title : 'Staff of this company',
            ];
        }

        if ((string) $user->property_portal_role === 'landlord' || isset($landlords[$id])) {
            $agentIds = $landlords[$id] ?? [];

            return $this->companyPlacement('landlord', 'Landlord', $agentIds, $companyNames, 'Owns property managed by this company');
        }

        if ((string) $user->property_portal_role === 'tenant' || isset($tenants[$id])) {
            return $this->companyPlacement('tenant', 'Tenant', $tenants[$id] ?? [], $companyNames, 'Tenant of this company');
        }

        if (filled($user->loan_role)) {
            return [
                'category' => 'loan',
                'label' => 'Loan staff',
                'company' => 'Platform',
                'company_id' => null,
                'note' => 'Loan module. Not inside an agent company.',
            ];
        }

        return [
            'category' => 'unplaced',
            'label' => 'Not placed',
            'company' => '—',
            'company_id' => null,
            'note' => 'No company or portal role yet.',
        ];
    }

    /**
     * @param  list<int>  $agentIds
     * @param  array<int, string>  $companyNames
     * @return array{category:string, label:string, company:string, company_id:?int, note:string}
     */
    private function companyPlacement(string $category, string $label, array $agentIds, array $companyNames, string $note): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if ($agentIds === []) {
            return [
                'category' => $category,
                'label' => $label,
                'company' => 'Company not linked',
                'company_id' => null,
                'note' => $note,
            ];
        }

        $primary = $agentIds[0];
        $extra = count($agentIds) - 1;

        return [
            'category' => $category,
            'label' => $label,
            'company' => $companyNames[$primary] ?? 'Agent #'.$primary,
            'company_id' => $primary,
            'note' => $extra > 0 ? $note.' · also '.number_format($extra).' other compan'.($extra === 1 ? 'y' : 'ies') : $note,
        ];
    }
}
