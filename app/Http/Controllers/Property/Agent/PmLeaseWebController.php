<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\DepositDefinition;
use App\Models\ExpenseDefinition;
use App\Models\LeaseDepositLine;
use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmTenant;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmUnitMovement;
use App\Models\Property;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Property\CarryForwardConsolidationService;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\PropertyActivityLogger;
use App\Services\Property\PropertyDashboardCache;
use App\Services\Property\PropertyMoney;
use App\Support\Property\PropertyFilterCascadeCatalog;
use App\Support\TabularExport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Property\Concerns\RespondsWithPropertyFormModal;
use Illuminate\View\View;

class PmLeaseWebController extends Controller
{
    use RespondsWithPropertyFormModal;

    private const AUTO_ARREARS_PREFIX = '[Lease Opening Arrears]';

    private const AUTO_UTILITY_PREFIX = '[Lease Utility Expense]';

    private const AUTO_DEPOSIT_PREFIX = '[Lease Deposit Charge]';

    /**
     * @return array{
     *   stats: array<int,array{label:string,value:string,hint:string}>,
     *   columns: array<int,string>,
     *   tableRows: array<int,array<int,mixed>>,
     *   expiryFilterTexts: array<int,string>
     * }
     */
    /**
     * @return array<string, mixed>
     */
    private function leaseListFiltersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'window' => trim((string) $request->query('window', '')),
            'pm_tenant_id' => max(0, (int) $request->query('pm_tenant_id', 0)),
            'property_id' => max(0, (int) $request->query('property_id', 0)),
            'unit_id' => max(0, (int) $request->query('unit_id', 0)),
            'term' => trim((string) $request->query('term', '')),
            'expiring' => trim((string) $request->query('expiring', '')),
            'carry_forward' => trim((string) $request->query('carry_forward', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'sort' => trim((string) $request->query('sort', 'start_date')),
            'dir' => strtolower(trim((string) $request->query('dir', 'desc'))) === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function applyLeaseSearchFilter(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            if (ctype_digit($search)) {
                $builder->where('pm_leases.id', (int) $search);
            }
            $builder->orWhereHas('pmTenant', function (Builder $tenantQuery) use ($search): void {
                $tenantQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })->orWhereHas('units', function (Builder $unitQuery) use ($search): void {
                $unitQuery->where('label', 'like', '%'.$search.'%')
                    ->orWhereHas('property', function (Builder $propertyQuery) use ($search): void {
                        $propertyQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLeaseListFilters(Builder $query, array $filters, bool $applyStatus = true, bool $filterEndDate = false): void
    {
        $this->applyLeaseSearchFilter($query, (string) ($filters['q'] ?? ''));

        if ($applyStatus && ($filters['status'] ?? '') !== '' && in_array($filters['status'], [
            PmLease::STATUS_DRAFT,
            PmLease::STATUS_ACTIVE,
            PmLease::STATUS_EXPIRED,
            PmLease::STATUS_TERMINATED,
        ], true)) {
            $query->where('pm_leases.status', $filters['status']);
        }

        if (($filters['pm_tenant_id'] ?? 0) > 0) {
            $query->where('pm_leases.pm_tenant_id', (int) $filters['pm_tenant_id']);
        }

        app(PropertyFilterCascadeCatalog::class)->applyToLeaseQuery($query, $filters);

        if (($filters['term'] ?? '') === 'open_ended') {
            $query->whereNull('pm_leases.end_date');
        } elseif (($filters['term'] ?? '') === 'fixed') {
            $query->whereNotNull('pm_leases.end_date');
        }

        $expiring = (string) ($filters['expiring'] ?? '');
        if (in_array($expiring, ['within30', 'within60', 'within90'], true)) {
            $days = match ($expiring) {
                'within30' => 30,
                'within60' => 60,
                default => 90,
            };
            $query->where('pm_leases.status', PmLease::STATUS_ACTIVE)
                ->whereNotNull('pm_leases.end_date')
                ->whereDate('pm_leases.end_date', '>=', now()->toDateString())
                ->whereDate('pm_leases.end_date', '<=', now()->addDays($days)->toDateString());
        }

        $dateColumn = $filterEndDate ? 'pm_leases.end_date' : 'pm_leases.start_date';
        if (($filters['from'] ?? '') !== '') {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (($filters['to'] ?? '') !== '') {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLeaseListSort(Builder $query, array $filters): void
    {
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sort = (string) ($filters['sort'] ?? 'start_date');

        match ($sort) {
            'end_date' => $query->orderByRaw('pm_leases.end_date IS NULL')
                ->orderBy('pm_leases.end_date', $dir),
            'rent' => $query->orderBy('pm_leases.monthly_rent', $dir),
            'lease' => $query->orderBy('pm_leases.id', $dir),
            'tenant' => $query->leftJoin('pm_tenants', 'pm_tenants.id', '=', 'pm_leases.pm_tenant_id')
                ->orderBy('pm_tenants.name', $dir)
                ->select('pm_leases.*'),
            default => $query->orderBy('pm_leases.start_date', $dir)->orderBy('pm_leases.id', $dir),
        };
    }

    /**
     * Carry-forward list filter uses opening_arrears JSON rows only (see leaseCarryForwardTotal()).
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyLeaseCarryForwardFilter(Builder $query, array $filters): void
    {
        $carryForward = (string) ($filters['carry_forward'] ?? '');
        if (! in_array($carryForward, ['yes', 'no'], true)) {
            return;
        }

        if (! Schema::hasColumn('pm_leases', 'opening_arrears')) {
            if ($carryForward === 'yes') {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        $hasPositiveOpeningArrears = $this->leaseHasPositiveOpeningArrearsSql();

        if ($carryForward === 'yes') {
            $query->whereRaw($hasPositiveOpeningArrears);
        } else {
            $query->whereRaw('NOT ('.$hasPositiveOpeningArrears.')');
        }
    }

    private function leaseHasPositiveOpeningArrearsSql(): string
    {
        static $sql = null;

        if (is_string($sql)) {
            return $sql;
        }

        $indexUnions = collect(range(0, 49))
            ->map(fn (int $index): string => 'SELECT '.$index.' AS idx')
            ->implode(' UNION ALL ');

        $sql = <<<SQL
EXISTS (
    SELECT 1
    FROM (
        {$indexUnions}
    ) AS opening_arrears_indices
    WHERE JSON_EXTRACT(pm_leases.opening_arrears, CONCAT('\$[', opening_arrears_indices.idx, '].amount')) IS NOT NULL
      AND CAST(
          JSON_UNQUOTE(JSON_EXTRACT(pm_leases.opening_arrears, CONCAT('\$[', opening_arrears_indices.idx, '].amount')))
          AS DECIMAL(14, 2)
      ) > 0
)
SQL;

        return $sql;
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string}>
     */
    private function leaseListStatsFromQuery(Builder $query): array
    {
        $today = now()->toDateString();
        $endingBy = now()->addDays(60)->toDateString();

        $aggregates = $this->prepareLeaseListStatsQuery($query)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pm_leases.status = ? THEN 1 ELSE 0 END), 0) as active_count',
                [PmLease::STATUS_ACTIVE]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pm_leases.status = ? AND pm_leases.end_date IS NOT NULL AND DATE(pm_leases.end_date) >= ? AND DATE(pm_leases.end_date) <= ? THEN 1 ELSE 0 END), 0) as ending_soon_count',
                [PmLease::STATUS_ACTIVE, $today, $endingBy]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN pm_leases.status = ? THEN 1 ELSE 0 END), 0) as draft_count',
                [PmLease::STATUS_DRAFT]
            )
            ->first();

        return [
            ['label' => 'All leases', 'value' => (string) (int) ($aggregates->total_count ?? 0), 'hint' => ''],
            ['label' => 'Active', 'value' => (string) (int) ($aggregates->active_count ?? 0), 'hint' => ''],
            ['label' => 'Ending ≤60d', 'value' => (string) (int) ($aggregates->ending_soon_count ?? 0), 'hint' => ''],
            ['label' => 'Draft', 'value' => (string) (int) ($aggregates->draft_count ?? 0), 'hint' => ''],
        ];
    }

    private function prepareLeaseListStatsQuery(Builder $query): Builder
    {
        $statsQuery = clone $query;
        $statsQuery->setEagerLoads([]);
        $statsQuery->reorder();

        return $statsQuery;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateLeaseList(Builder $query, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $this->applyLeaseCarryForwardFilter($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Cached create-form static config only (arrays/scalars — no Eloquent collections).
     *
     * @return array{
     *   leaseTemplate: string,
     *   leaseFields: array<string, array{enabled: bool, required: bool}>,
     *   openingArrearsTypeOptions: array<string, string>
     * }
     */
    private function leaseFormStaticContext(): array
    {
        return Cache::remember(
            PropertyDashboardCache::leasesFormContextKey(),
            300,
            fn (): array => [
                'leaseTemplate' => (string) PropertyPortalSetting::getValue('template_lease_text', ''),
                'leaseFields' => $this->leaseFieldConfig(),
                'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function leaseFormEndpointUrls(): array
    {
        return [
            'tenants' => route('property.leases.form_tenants', absolute: false),
            'vacantUnits' => route('property.leases.form_vacant_units', absolute: false),
            'propertyRules' => route('property.leases.form_property_rules', absolute: false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leaseFormPageContext(Request $request): array
    {
        $selectedUnitId = max(0, (int) $request->query('unit_id', 0));

        return array_merge($this->leaseFormStaticContext(), [
            'leaseFormEndpoints' => $this->leaseFormEndpointUrls(),
            'leaseFormSelectedTenantId' => max(0, (int) $request->query('pm_tenant_id', 0)),
            'leaseFormSelectedUnitId' => $selectedUnitId,
        ]);
    }

    public function formTenants(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $selectedTenantId = max(0, (int) $request->query('selected', 0));
        $limit = min(250, max(1, (int) $request->query('limit', 100)));

        $query = $this->selectableTenantsQuery()
            ->orderBy('name')
            ->limit($limit);

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $tenants = $query->get(['id', 'name']);

        if ($selectedTenantId > 0 && ! $tenants->contains('id', $selectedTenantId)) {
            $selectedTenant = PmTenant::query()->find($selectedTenantId, ['id', 'name']);
            if ($selectedTenant) {
                $tenants->prepend($selectedTenant);
            }
        }

        return response()->json([
            'items' => $tenants
                ->map(fn (PmTenant $tenant): array => [
                    'value' => (string) $tenant->id,
                    'label' => (string) $tenant->name,
                ])
                ->values()
                ->all(),
            'has_more' => $tenants->count() >= $limit,
        ]);
    }

    public function formVacantUnits(Request $request): JsonResponse
    {
        $propertyId = max(0, (int) $request->query('property_id', 0));
        $selectedUnitId = max(0, (int) $request->query('selected_unit_id', 0));
        $limit = min(500, max(1, (int) $request->query('limit', 250)));

        $unitsQuery = $this->leaseFormVacantUnitsQuery($selectedUnitId)
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('label');

        if ($propertyId > 0) {
            $unitsQuery->where('property_units.property_id', $propertyId);
        }

        $units = $unitsQuery->limit($limit)->get(['property_units.id', 'property_units.property_id', 'property_units.label', 'property_units.rent_amount']);

        return response()->json([
            'properties' => $this->leaseFormVacantProperties(),
            'units' => $this->serializeLeaseFormUnits($units),
            'has_more' => $units->count() >= $limit,
        ]);
    }

    public function formPropertyRules(Request $request): JsonResponse
    {
        $propertyId = max(0, (int) $request->query('property_id', 0));
        if ($propertyId <= 0) {
            return response()->json([
                'utilityChargeTemplates' => [],
                'depositDefinitions' => [],
            ]);
        }

        $utilityByProperty = $this->utilityChargeTemplatesByPropertyMerged();
        $depositByProperty = $this->depositDefinitionsByProperty();

        return response()->json([
            'utilityChargeTemplates' => array_values($utilityByProperty[(string) $propertyId] ?? []),
            'depositDefinitions' => array_values($depositByProperty[(string) $propertyId] ?? []),
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function leaseFormVacantProperties(): array
    {
        $propertyIds = $this->leaseFormVacantUnitsQuery()
            ->select('property_units.property_id')
            ->distinct()
            ->pluck('property_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($propertyIds === []) {
            return [];
        }

        return Property::query()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $property): array => [
                'id' => (int) $property->id,
                'name' => (string) $property->name,
            ])
            ->values()
            ->all();
    }

    private function leaseFormVacantUnitsQuery(int $selectedUnitId = 0): Builder
    {
        return PropertyUnit::query()
            ->where(function (Builder $query) use ($selectedUnitId): void {
                $query->where(function (Builder $vacantQuery): void {
                    $vacantQuery->where('property_units.status', PropertyUnit::STATUS_VACANT)
                        ->whereDoesntHave('leases', fn (Builder $leaseQuery) => $leaseQuery->where('pm_leases.status', PmLease::STATUS_ACTIVE));
                });

                if ($selectedUnitId > 0) {
                    $query->orWhere('property_units.id', $selectedUnitId);
                }
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PropertyUnit>  $units
     * @return array<int, array{id: int, property_id: int, property_name: string, label: string, rent_amount: float}>
     */
    private function serializeLeaseFormUnits($units): array
    {
        return $units
            ->map(fn (PropertyUnit $unit): array => [
                'id' => (int) $unit->id,
                'property_id' => (int) $unit->property_id,
                'property_name' => (string) ($unit->property->name ?? ''),
                'label' => (string) $unit->label,
                'rent_amount' => (float) ($unit->rent_amount ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<PmTenant>
     */
    private function selectableTenantsQuery(?PmLease $lease = null): Builder
    {
        $busyTenantIds = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->pluck('pm_tenant_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return PmTenant::query()
            ->when($busyTenantIds !== [], function (Builder $query) use ($busyTenantIds, $lease): void {
                $query->where(function (Builder $inner) use ($busyTenantIds, $lease): void {
                    $inner->whereNotIn('pm_tenants.id', $busyTenantIds);
                    if ($lease?->pm_tenant_id) {
                        $inner->orWhere('pm_tenants.id', $lease->pm_tenant_id);
                    }
                });
            });
    }

    /**
     * @return list<mixed>
     */
    private function mapLeaseListRow(PmLease $l, bool $isLeasesTab): array
    {
        $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ');
        $tenantName = $l->pmTenant?->name ?? '—';
        $utilityExpenses = collect($l->utility_expenses ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values();
        if ($utilityExpenses->isNotEmpty()) {
            $expenseLines = $utilityExpenses
                ->filter(fn ($row) => trim((string) ($row['type'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0)
                ->map(function ($row): string {
                    $type = $this->utilityExpenseTypeLabel((string) ($row['type'] ?? ''));
                    if ($type === '') {
                        $type = ucfirst(str_replace('_', ' ', (string) ($row['type'] ?? 'Other')));
                    }

                    return $type.': '.PropertyMoney::kes((float) ($row['amount'] ?? 0));
                })
                ->values()
                ->all();
            $expenseLabel = $expenseLines !== []
                ? new HtmlString(implode('<br>', array_map(static fn ($line) => e($line), $expenseLines)))
                : '—';
        } else {
            $expenseType = $this->utilityExpenseTypeLabel($l->utility_expense_type);
            $expenseAmount = (float) ($l->utility_expense_amount ?? 0);
            $expenseLabel = ($expenseType !== '' && $expenseAmount > 0)
                ? new HtmlString(e($expenseType.': '.PropertyMoney::kes($expenseAmount)))
                : '—';
        }

        $additionalDeposits = collect($l->additional_deposits ?? [])
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0)
            ->values();
        $depositLines = [
            'Rent deposit: '.PropertyMoney::kes((float) $l->deposit_amount),
        ];
        foreach ($additionalDeposits as $row) {
            $depositLines[] = trim((string) ($row['label'] ?? 'Deposit')).': '.PropertyMoney::kes((float) ($row['amount'] ?? 0));
        }
        $depositBreakdown = new HtmlString(implode('<br>', array_map(static fn ($line) => e($line), $depositLines)));

        $actions = new HtmlString(view('property.agent.partials.lease_row_actions', [
            'lease' => $l,
            'tenantName' => $tenantName,
        ])->render());

        $row = [
            new HtmlString(
                '<a href="'.route('property.leases.edit', $l, false).'" data-turbo-frame="property-main" class="font-semibold text-indigo-700 hover:underline">#'.$l->id.'</a>'
            ),
            $tenantName,
            $units !== '' ? $units : '—',
            $l->start_date->format('Y-m-d'),
            $l->end_date?->format('Y-m-d') ?? 'Open-ended',
            number_format((float) $l->monthly_rent, 2),
            $depositBreakdown,
            $expenseLabel,
            ucfirst($l->status),
            $actions,
        ];

        if ($isLeasesTab) {
            array_unshift(
                $row,
                new HtmlString(
                    '<label class="inline-flex items-center" data-row-ignore-click>'.
                    '<label class="inline-flex items-center" data-row-ignore-click><input type="checkbox" name="lease_ids[]" value="'.(int) $l->id.'" form="property-leases-bulk-form" class="property-bulk-row-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"><span class="sr-only">Select lease</span></label>'.
                    '<span class="sr-only">Select lease #'.(int) $l->id.'</span>'.
                    '</label>'
                )
            );
        }

        return $row;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function leaseFilterPropertyOptions(): array
    {
        return Property::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $property): array => [
                'value' => (string) $property->id,
                'label' => (string) $property->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function leaseFilterTenantOptions(): array
    {
        return $this->selectableTenants()
            ->map(fn (PmTenant $tenant): array => [
                'value' => (string) $tenant->id,
                'label' => (string) $tenant->name,
            ])
            ->all();
    }

    /**
     * @return array{tenants: array<int, array{value: string, label: string}>, properties: array<int, array{value: string, label: string}>}
     */
    private function leaseListFilterOptions(): array
    {
        return [
            'tenants' => $this->leaseFilterTenantOptions(),
            'properties' => $this->leaseFilterPropertyOptions(),
        ];
    }

    /**
     * @return list<string|int|float|null>
     */
    private function mapLeaseExportRow(PmLease $lease): array
    {
        $units = $lease->units
            ->map(fn ($unit) => ($unit->property->name ?? 'Property').'/'.($unit->label ?? $unit->id))
            ->implode(', ');

        return [
            (string) $lease->id,
            (string) ($lease->pmTenant?->name ?? ''),
            $units !== '' ? $units : '—',
            $lease->start_date?->format('Y-m-d') ?? '',
            $lease->end_date?->format('Y-m-d') ?? 'Open-ended',
            number_format((float) $lease->monthly_rent, 2, '.', ''),
            number_format((float) $lease->deposit_amount, 2, '.', ''),
            ucfirst((string) $lease->status),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function leaseListExportResponse(string $activeTab, array $filters, string $export): StreamedResponse
    {
        $leaseQuery = PmLease::query()->with(['pmTenant', 'units.property']);

        if ($activeTab === 'expiry') {
            $leaseQuery
                ->where('pm_leases.status', PmLease::STATUS_ACTIVE)
                ->whereDate('pm_leases.end_date', '>=', now()->toDateString())
                ->whereDate('pm_leases.end_date', '<=', now()->addDays(90)->toDateString());
            $this->applyLeaseListFilters($leaseQuery, $filters, applyStatus: false, filterEndDate: true);
        } else {
            $this->applyLeaseListFilters($leaseQuery, $filters, applyStatus: true, filterEndDate: false);
        }

        $this->applyLeaseCarryForwardFilter($leaseQuery, $filters);
        $this->applyLeaseListSort($leaseQuery, $filters);

        $filename = $activeTab === 'expiry' ? 'lease-expiry' : 'leases';

        return TabularExport::stream(
            $filename.'-'.now()->format('Ymd_His'),
            ['Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent (KES)', 'Deposit (KES)', 'Status'],
            function () use ($leaseQuery): \Generator {
                foreach ($leaseQuery->limit(5000)->cursor() as $lease) {
                    yield $this->mapLeaseExportRow($lease);
                }
            },
            $export
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function expiryTablePayload(array $filters): array
    {
        $search = (string) ($filters['q'] ?? '');
        $window = (string) ($filters['window'] ?? '');

        $leaseQuery = PmLease::query()
            ->with(['pmTenant', 'units.property.landlords'])
            ->where('pm_leases.status', PmLease::STATUS_ACTIVE)
            ->whereDate('pm_leases.end_date', '>=', now()->toDateString())
            ->whereDate('pm_leases.end_date', '<=', now()->addDays(90)->toDateString());

        $this->applyLeaseListFilters($leaseQuery, $filters, applyStatus: false, filterEndDate: true);
        $this->applyLeaseCarryForwardFilter($leaseQuery, $filters);
        $this->applyLeaseListSort($leaseQuery, ['sort' => 'end_date', 'dir' => 'asc']);

        $leases = $leaseQuery->get();

        if ($window === 'within30') {
            $leases = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(30)))->values();
        } elseif ($window === 'within60') {
            $leases = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(60)))->values();
        } elseif ($window === 'within90') {
            $leases = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(90)))->values();
        }

        $rentAtRisk = (float) $leases
            ->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(90)))
            ->sum('monthly_rent');

        $in30 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(30)))->count();
        $in60 = $leases->filter(fn (PmLease $l) => $l->end_date->lte(now()->addDays(60)))->count();
        $in90 = $leases->count();

        $mapped = $leases->map(function (PmLease $l) {
            $units = $l->units->map(fn ($u) => $u->property->name.'/'.$u->label)->implode(', ') ?: '—';
            $daysLeft = $l->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($l->end_date);
            $tenantName = $l->pmTenant?->name ?? '—';

            $filterParts = [
                mb_strtolower($tenantName),
                mb_strtolower($units),
                (string) $daysLeft,
            ];
            if ($daysLeft <= 30) {
                $filterParts[] = 'within30';
            }
            if ($daysLeft <= 60) {
                $filterParts[] = 'within60';
            }
            if ($daysLeft <= 90) {
                $filterParts[] = 'within90';
            }

            return [
                'filter' => implode(' ', $filterParts),
                'cells' => [
                    $tenantName,
                    $units,
                    $l->end_date->format('Y-m-d'),
                    (string) max(0, $daysLeft),
                    PropertyMoney::kes((float) $l->monthly_rent),
                    $daysLeft <= 30 ? 'Urgent renewal call' : ($daysLeft <= 60 ? 'Send renewal offer' : 'Monitor'),
                    ucfirst($l->status),
                    new HtmlString(
                        '<a href="'.route('property.tenants.notices').'" class="text-indigo-600 hover:text-indigo-700 font-medium">Open notices</a>'
                    ),
                ],
            ];
        });

        return [
            'stats' => [
                ['label' => 'Expiring ≤30d', 'value' => (string) $in30, 'hint' => 'Urgent'],
                ['label' => 'Expiring ≤60d', 'value' => (string) $in60, 'hint' => 'Outreach'],
                ['label' => 'Expiring ≤90d', 'value' => (string) $in90, 'hint' => 'This list'],
                ['label' => 'Rent at risk (mo)', 'value' => PropertyMoney::kes($rentAtRisk), 'hint' => 'If not renewed'],
            ],
            'columns' => ['Tenant', 'Unit', 'End date', 'Days left', 'Current rent', 'Renewal offer', 'Status', 'Owner'],
            'tableRows' => $mapped->map(fn (array $r) => $r['cells'])->values()->all(),
            'expiryFilterTexts' => $mapped->map(fn (array $r) => $r['filter'])->values()->all(),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{label:string,amount:string}>
     */
    private function normalizeAdditionalDeposits(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amountRaw = $row['amount'] ?? null;

            if ($label === '' && ($amountRaw === null || $amountRaw === '')) {
                continue;
            }
            if ($label === '') {
                continue;
            }

            $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;
            $normalized[] = [
                'label' => $label,
                'amount' => number_format(max(0, $amount), 2, '.', ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{charge_type:string,specific_charge:string,period:?string,amount:string}>
     */
    private function normalizeOpeningArrears(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $chargeType = trim((string) ($row['charge_type'] ?? ''));
            $specificCharge = trim((string) ($row['specific_charge'] ?? ''));
            $period = trim((string) ($row['period'] ?? ''));
            $amountRaw = $row['amount'] ?? null;

            if ($chargeType === '' && $specificCharge === '' && $period === '' && ($amountRaw === null || $amountRaw === '')) {
                continue;
            }

            if (! is_numeric($amountRaw)) {
                continue;
            }

            $amount = max(0, (float) $amountRaw);
            if ($amount <= 0) {
                continue;
            }

            if ($chargeType === '') {
                $chargeType = 'other';
            }

            $normalized[] = [
                'charge_type' => mb_substr($chargeType, 0, 50),
                'specific_charge' => mb_substr($specificCharge, 0, 100),
                'period' => $period !== '' ? $period : null,
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array<string,mixed>>  $openingArrearsRows
     * @param  mixed  $rentArrearsRaw
     * @param  mixed  $rentArrearsPeriodRaw
     * @param  mixed  $rentArrearsDetailsRaw
     * @param  array<string,mixed>  $depositArrears
     * @param  array<int,int>  $unitIds
     * @return array<int,array<string,mixed>>
     */
    private function mergeOpeningArrearsWithDepositArrears(
        array $openingArrearsRows,
        mixed $rentArrearsRaw,
        mixed $rentArrearsPeriodRaw,
        mixed $rentArrearsDetailsRaw,
        array $depositArrears,
        array $unitIds
    ): array
    {
        $rentArrears = is_numeric($rentArrearsRaw) ? (float) $rentArrearsRaw : 0.0;
        if ($rentArrears > 0) {
            $rentPeriod = is_string($rentArrearsPeriodRaw) && preg_match('/^\d{4}-\d{2}$/', $rentArrearsPeriodRaw) === 1
                ? $rentArrearsPeriodRaw
                : null;
            $rentDetails = trim((string) ($rentArrearsDetailsRaw ?? ''));
            $openingArrearsRows[] = [
                'charge_type' => 'rent_arrears',
                'specific_charge' => $rentDetails !== '' ? mb_substr($rentDetails, 0, 100) : 'Rent arrears',
                'period' => $rentPeriod,
                'amount' => $rentArrears,
            ];
        }

        if ($depositArrears === []) {
            return $openingArrearsRows;
        }

        $definitions = collect();
        if ($unitIds !== []) {
            $unit = PropertyUnit::query()->select(['id', 'property_id'])->find((int) $unitIds[0]);
            if ($unit) {
                $definitions = $this->resolveDepositDefinitions((int) $unit->property_id, (int) $unit->id);
            }
        }

        $labelByKey = $definitions
            ->mapWithKeys(fn ($def) => [(string) $def->deposit_key => (string) ($def->label ?: $def->deposit_key)])
            ->all();

        foreach ($depositArrears as $key => $amountRaw) {
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : 0.0;
            if ($amount <= 0) {
                continue;
            }
            $normalizedKey = $this->normalizeDepositKey((string) $key);
            if ($normalizedKey === '') {
                continue;
            }
            $label = $labelByKey[$normalizedKey] ?? ucwords(str_replace('_', ' ', $normalizedKey));
            $openingArrearsRows[] = [
                'charge_type' => 'deposit_arrears',
                'specific_charge' => $label.' arrears',
                'period' => null,
                'amount' => $amount,
            ];
        }

        return $openingArrearsRows;
    }

    private function utilityExpenseTypeLabel(?string $value): string
    {
        $type = trim((string) $value);
        if ($type === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $type));
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{type:string,amount:string}>
     */
    private function normalizeUtilityExpenses(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $type = trim((string) ($row['type'] ?? ''));
            if ($type === '') {
                $type = 'other';
            }
            $rateRaw = $row['rate_per_unit'] ?? null;
            $fixedRaw = $row['fixed_charge'] ?? $row['fixed'] ?? null;
            $amountRaw = $row['amount'] ?? null;

            $rate = is_numeric($rateRaw) ? max(0.0, (float) $rateRaw) : 0.0;
            $fixed = is_numeric($fixedRaw) ? max(0.0, (float) $fixedRaw) : 0.0;

            $amount = 0.0;
            if (is_numeric($amountRaw) && (float) $amountRaw > 0) {
                $amount = max(0.0, (float) $amountRaw);
            } elseif ($fixed > 0) {
                $amount = $fixed;
            } elseif ($rate > 0) {
                $amount = $rate;
            }

            if ($amount <= 0) {
                continue;
            }

            $normalized[] = [
                'type' => mb_substr($type, 0, 50),
                'amount' => number_format($amount, 2, '.', ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int,array{type:string,amount:string}>  $utilityExpenses
     * @param  array<int,int>  $unitIds
     */
    private function assertUtilityExpenseTypesAllowed(array $utilityExpenses, array $unitIds): void
    {
        $allowedTypes = $this->allowedUtilityTypesForUnit($unitIds);
        if ($allowedTypes === []) {
            return;
        }

        $invalid = collect($utilityExpenses)
            ->map(fn (array $row): string => $this->normalizeUtilityType((string) ($row['type'] ?? '')))
            ->filter(fn (string $type): bool => $type !== '' && ! in_array($type, $allowedTypes, true))
            ->unique()
            ->values()
            ->all();

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'utility_expenses' => 'Only configured utility types are allowed for the selected unit/property: '.implode(', ', $invalid),
            ]);
        }
    }

    /**
     * @param  array<int,int>  $unitIds
     * @return array<int,string>
     */
    private function allowedUtilityTypesForUnit(array $unitIds): array
    {
        $unitId = (int) ($unitIds[0] ?? 0);
        if ($unitId <= 0) {
            return [];
        }

        $unit = PropertyUnit::query()->select(['id', 'property_id'])->find($unitId);
        if (! $unit) {
            return [];
        }

        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $all = json_decode($raw, true);
        $all = is_array($all) ? $all : [];
        $rows = $all[(string) $unit->property_id] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $types = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $scopeUnitId = isset($row['property_unit_id']) && $row['property_unit_id'] !== '' ? (int) $row['property_unit_id'] : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }

            $type = $this->normalizeUtilityType((string) ($row['charge_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        foreach (ExpenseDefinition::query()
            ->where('is_active', true)
            ->where('property_id', $unit->property_id)
            ->orderBy('sort_order')
            ->orderBy('charge_key')
            ->get() as $def) {
            $scopeUnitId = $def->property_unit_id ? (int) $def->property_unit_id : null;
            if ($scopeUnitId !== null && $scopeUnitId !== $unitId) {
                continue;
            }
            $type = $this->normalizeUtilityType((string) $def->charge_key);
            if ($type === '') {
                continue;
            }
            $types[$type] = $type;
        }

        return array_values($types);
    }

    /**
     * Merge legacy JSON templates with active ExpenseDefinition rows so lease UI stays aligned with Settings rules.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function utilityChargeTemplatesByPropertyMerged(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('utility_property_charge_templates_json', '{}');
        $decoded = json_decode($raw, true);
        $byProperty = is_array($decoded) ? $decoded : [];

        foreach (ExpenseDefinition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('charge_key')
            ->get() as $def) {
            $pid = (int) $def->property_id;
            if ($pid <= 0) {
                continue;
            }
            $key = trim((string) $def->charge_key);
            if ($key === '') {
                continue;
            }
            $mode = (string) $def->amount_mode;
            $amount = is_numeric($def->amount_value) ? max(0.0, (float) $def->amount_value) : 0.0;
            $row = [
                'property_unit_id' => $def->property_unit_id ? (int) $def->property_unit_id : null,
                'charge_type' => $key,
                'label' => (string) ($def->label ?? $key),
                'rate_per_unit' => $mode === ExpenseDefinition::MODE_RATE_PER_UNIT ? round($amount, 2) : 0.0,
                'fixed_charge' => $mode === ExpenseDefinition::MODE_RATE_PER_UNIT ? 0.0 : round($amount, 2),
                'notes' => '',
                'is_required' => (bool) $def->is_required,
            ];

            $list = $byProperty[(string) $pid] ?? [];
            if (! is_array($list)) {
                $list = [];
            }
            $replaceIdx = null;
            foreach ($list as $i => $existing) {
                if (! is_array($existing)) {
                    continue;
                }
                $eType = $this->normalizeUtilityType((string) ($existing['charge_type'] ?? ''));
                $eUnit = isset($existing['property_unit_id']) && $existing['property_unit_id'] !== null && $existing['property_unit_id'] !== ''
                    ? (int) $existing['property_unit_id']
                    : null;
                $dUnit = $row['property_unit_id'];
                if ($eType === $this->normalizeUtilityType($key) && $eUnit === $dUnit) {
                    $replaceIdx = $i;
                    break;
                }
            }
            if ($replaceIdx !== null) {
                $list[$replaceIdx] = array_merge($list[$replaceIdx], $row);
            } else {
                $list[] = $row;
            }
            $byProperty[(string) $pid] = array_values($list);
        }

        return $byProperty;
    }

    private function normalizeUtilityType(string $type): string
    {
        return (string) Str::of($type)
            ->trim()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    private function selectableTenants(?PmLease $lease = null)
    {
        return $this->selectableTenantsQuery($lease)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<int,int|string>  $unitIds
     * @return array<int,int>
     */
    private function normalizeSingleUnitSelection(array $unitIds): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map('intval', $unitIds),
            fn (int $id): bool => $id > 0
        )));

        return array_slice($normalized, 0, 1);
    }

    private function leaseCarryForwardTotal(PmLease|array $source): float
    {
        $rows = $source instanceof PmLease
            ? (array) ($source->opening_arrears ?? [])
            : $source;

        return round(collect($rows)
            ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
            ->sum(fn ($row) => (float) ($row['amount'] ?? 0)), 2);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $data
     */
    private function carryForwardWasPosted(Request $request): bool
    {
        if ($request->boolean('carry_forward_touched')) {
            return true;
        }

        if ($request->boolean('carry_forward_submitted')) {
            return true;
        }

        if (is_numeric($request->input('opening_rent_arrears')) && (float) $request->input('opening_rent_arrears') > 0) {
            return true;
        }

        if (filled($request->input('opening_arrears_note'))
            || filled($request->input('opening_arrears_as_of_date'))
            || is_numeric($request->input('opening_arrears_manual_total'))) {
            return true;
        }

        foreach ((array) $request->input('opening_arrears', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (is_numeric($row['amount'] ?? null) && (float) $row['amount'] > 0) {
                return true;
            }
        }

        return false;
    }

    private function applyRentDueDayToPayload(array &$payload, array $data): void
    {
        if (! Schema::hasColumn('pm_leases', 'rent_due_day') || ! array_key_exists('rent_due_day', $data)) {
            return;
        }

        $raw = $data['rent_due_day'];
        $payload['rent_due_day'] = ($raw === null || $raw === '')
            ? null
            : \App\Services\Property\RentDueDayResolver::normalizeDueDay((int) $raw);
    }

    private function applyOpeningArrearsToPayload(array &$payload, Request $request, array $data, ?PmLease $lease, array $unitIds): void
    {
        if (! Schema::hasColumn('pm_leases', 'opening_arrears')) {
            return;
        }

        if ($lease !== null && ! $this->carryForwardWasPosted($request)) {
            return;
        }

        $payload['opening_arrears'] = $this->normalizeOpeningArrears(
            $this->mergeOpeningArrearsWithDepositArrears(
                (array) ($data['opening_arrears'] ?? []),
                $data['opening_rent_arrears'] ?? null,
                $data['opening_rent_arrears_period'] ?? null,
                $data['opening_rent_arrears_details'] ?? null,
                (array) ($data['opening_deposit_arrears'] ?? []),
                $unitIds
            )
        );

        if (Schema::hasColumn('pm_leases', 'opening_arrears_manual_total')) {
            $payload['opening_arrears_manual_total'] = isset($data['opening_arrears_manual_total'])
                ? (float) $data['opening_arrears_manual_total']
                : null;
        }
        if (Schema::hasColumn('pm_leases', 'opening_arrears_as_of_date')) {
            $payload['opening_arrears_as_of_date'] = $data['opening_arrears_as_of_date'] ?? null;
        }
        if (Schema::hasColumn('pm_leases', 'opening_arrears_note')) {
            $payload['opening_arrears_note'] = $data['opening_arrears_note'] ?? null;
        }
    }

    /**
     * @param array<int,int|string> $unitIds
     *
     * @throws ValidationException
     */
    private function assertActiveLeaseRules(int $tenantId, array $unitIds, ?int $excludeLeaseId = null): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));

        $tenantHasActiveLease = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->where('pm_tenant_id', $tenantId)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->exists();
        if ($tenantHasActiveLease) {
            throw ValidationException::withMessages([
                'pm_tenant_id' => 'This tenant already has an active unit/lease. End the current lease first.',
            ]);
        }

        if ($unitIds === []) {
            return;
        }

        $busyUnits = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->with('units:id,label')
            ->get()
            ->flatMap(fn (PmLease $l) => $l->units->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($busyUnits !== []) {
            throw ValidationException::withMessages([
                'property_unit_ids' => 'One or more selected units are already assigned to an active lease.',
            ]);
        }
    }

    /**
     * Units that are truly available: status vacant and not in any active lease.
     */
    private function trulyVacantUnits()
    {
        return PropertyUnit::query()
            ->where('status', PropertyUnit::STATUS_VACANT)
            ->whereDoesntHave('leases', fn ($q) => $q->where('pm_leases.status', PmLease::STATUS_ACTIVE))
            ->with('property')
            ->orderBy('property_id');
    }

    private function leaseAssignableUnits(?PmLease $lease = null)
    {
        return PropertyUnit::query()
            ->where(function ($q) use ($lease) {
                $q->where(function ($vacant) {
                    $vacant->where('status', PropertyUnit::STATUS_VACANT)
                        ->whereDoesntHave('leases', fn ($lq) => $lq->where('pm_leases.status', PmLease::STATUS_ACTIVE));
                });

                if ($lease) {
                    $selectedIds = $lease->units->pluck('id')->all();
                    if ($selectedIds !== []) {
                        $q->orWhereIn('id', $selectedIds);
                    }
                }
            })
            ->with('property')
            ->orderBy('property_id')
            ->orderBy('label');
    }

    private function ensureMovementLogged(PmLease $lease, array $unitIds, string $movementType, ?string $date): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($unitIds === [] || !$date) {
            return;
        }

        $tenantName = $lease->pmTenant?->name ?? '—';
        $needle = 'Lease #'.$lease->id;
        $notes = 'Auto: '.$needle.' (Tenant: '.$tenantName.')';

        foreach ($unitIds as $unitId) {
            $exists = PmUnitMovement::query()
                ->where('property_unit_id', $unitId)
                ->where('movement_type', $movementType)
                ->where('notes', 'like', '%'.$needle.'%')
                ->exists();
            if ($exists) {
                continue;
            }

            PmUnitMovement::query()->create([
                'property_unit_id' => $unitId,
                'movement_type' => $movementType,
                'status' => 'done',
                'scheduled_on' => $date,
                'completed_on' => $date,
                'notes' => $notes,
                'user_id' => null,
            ]);
        }
    }

    private function vacateUnitsIfNotInAnotherActiveLease(array $unitIds, ?int $excludeLeaseId = null): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($unitIds === []) {
            return;
        }

        // Only vacate units that are NOT linked to any other active lease.
        $stillOccupiedUnitIds = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            ->when($excludeLeaseId !== null, fn ($q) => $q->where('id', '!=', $excludeLeaseId))
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->with('units:id')
            ->get()
            ->flatMap(fn (PmLease $l) => $l->units->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $toVacate = array_values(array_diff($unitIds, $stillOccupiedUnitIds));
        if ($toVacate === []) {
            return;
        }

        PropertyUnit::query()->whereIn('id', $toVacate)->update([
            'status' => PropertyUnit::STATUS_VACANT,
            'vacant_since' => now()->toDateString(),
        ]);
    }

    /**
     * @return array<int, float>
     */
    private function splitMoneyAcrossParts(float $total, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $cents = (int) round($total * 100);
        $base = intdiv($cents, $parts);
        $rem = $cents - ($base * $parts);
        $out = [];
        for ($i = 0; $i < $parts; $i++) {
            $out[] = ($base + ($i < $rem ? 1 : 0)) / 100.0;
        }

        return $out;
    }

    /**
     * Mirror lease optional arrears/deposits into revenue modules.
     *
     * Utilities are usage-based recurring charges and should be posted from
     * utility billing runs (meter readings / periodic billing), not lease save.
     */
    private function syncLeaseRevenuePostings(PmLease $lease): void
    {
        $lease->loadMissing(['units.property']);
        $units = $lease->units;
        if ($units->isEmpty()) {
            return;
        }

        $tenantId = (int) $lease->pm_tenant_id;
        $billingMonth = ($lease->start_date?->format('Y-m')) ?: now()->format('Y-m');
        $unitCount = $units->count();

        $firebreak = app(FinanceFirebreakService::class);
        $consolidation = app(CarryForwardConsolidationService::class);

        foreach ($firebreak->carryForwardWarnings($lease) as $warning) {
            $firebreak->logCarryForwardWarning($warning);
        }

        $consolidation->syncLease($lease);

        foreach ($units as $u) {
            PmUnitUtilityCharge::query()
                ->where('property_unit_id', (int) $u->id)
                ->where('notes', 'like', self::AUTO_UTILITY_PREFIX.'%')
                ->delete();
        }

        PmInvoice::query()
            ->withoutGlobalScopes()
            ->where('pm_lease_id', $lease->id)
            ->where('description', 'like', self::AUTO_DEPOSIT_PREFIX.'%')
            ->delete();

        // Carry-forward invoices are delta-synced via CarryForwardConsolidationService (no delete/recreate).

        // Intentionally no auto-utility posting from lease save.

        if (Schema::hasTable('lease_deposit_lines')) {
            $depositLines = $lease->depositLines()
                ->where('expected_amount', '>', 0)
                ->get();
            foreach ($depositLines as $line) {
                $expected = (float) $line->expected_amount;
                if ($expected <= 0) {
                    continue;
                }
                $paidTotal = (float) $line->paid_amount;
                $expectedParts = $this->splitMoneyAcrossParts($expected, $unitCount);
                $paidParts = $this->splitMoneyAcrossParts($paidTotal, $unitCount);
                foreach ($units->values() as $idx => $unit) {
                    $partExpected = (float) ($expectedParts[$idx] ?? 0.0);
                    if ($partExpected <= 0) {
                        continue;
                    }
                    $partPaid = (float) ($paidParts[$idx] ?? 0.0);
                    $partBalance = $partExpected - $partPaid;
                    $agentUserId = optional($unit->property)->agent_user_id ?? auth()->id();
                    $invoice = PmInvoice::query()->create([
                        'pm_lease_id' => $lease->id,
                        'property_unit_id' => (int) $unit->id,
                        'pm_tenant_id' => $tenantId,
                        'agent_user_id' => $agentUserId,
                        'created_by_user_id' => auth()->id(),
                        'invoice_no' => PmInvoice::nextInvoiceNumber(),
                        'issue_date' => $lease->start_date?->toDateString() ?? now()->toDateString(),
                        'due_date' => $lease->start_date?->toDateString() ?? now()->toDateString(),
                        'amount' => $partExpected,
                        'amount_paid' => $partPaid,
                        'status' => PmInvoice::STATUS_SENT,
                        'invoice_type' => PmInvoice::TYPE_MIXED,
                        'billing_period' => $billingMonth,
                        'description' => self::AUTO_DEPOSIT_PREFIX.' '.$line->label,
                    ]);
                    $invoice->syncAmountPaidFromAllocations();
                }
            }
        }
    }

    public function leases(Request $request): View|StreamedResponse
    {
        $activeTab = $request->string('tab')->toString() === 'expiry' ? 'expiry' : 'leases';
        $filters = $this->leaseListFiltersFromRequest($request);
        $filterOptions = $this->leaseListFilterOptions();
        $cascade = app(PropertyFilterCascadeCatalog::class);
        $propertyId = (int) $filters['property_id'];
        $unitId = (int) $filters['unit_id'];
        $tenantId = (int) $filters['pm_tenant_id'];
        $cascadeViewData = [
            'properties' => $cascade->properties(),
            'units' => $cascade->unitsForProperty($propertyId),
            'tenantsForFilter' => $cascade->leaseTenantsForFilter($tenantId, $propertyId, $unitId),
            'filterCascadeCatalog' => $cascade->fromLeases(),
        ];

        $export = strtolower((string) $request->query('export', ''));
        if (in_array($export, ['csv', 'xls', 'pdf'], true)) {
            return $this->leaseListExportResponse($activeTab, $filters, $export);
        }

        if ($activeTab === 'expiry') {
            $expiryPayload = $this->expiryTablePayload($filters);

            return property_view('property.agent.tenants.leases', array_merge([
                'activeTab' => $activeTab,
                'stats' => $expiryPayload['stats'],
                'columns' => $expiryPayload['columns'],
                'tableRows' => $expiryPayload['tableRows'],
                'expiryFilterTexts' => $expiryPayload['expiryFilterTexts'],
                'filters' => $filters,
                'filterOptions' => $filterOptions,
            ], $cascadeViewData));
        }

        $leaseQuery = PmLease::query()->with(['pmTenant', 'units.property']);
        $this->applyLeaseListFilters($leaseQuery, $filters, applyStatus: true, filterEndDate: false);
        $stats = $this->leaseListStatsFromQuery($leaseQuery);
        $this->applyLeaseListSort($leaseQuery, $filters);
        $leasePager = $this->paginateLeaseList($leaseQuery, $filters);
        $rows = $leasePager->getCollection()
            ->map(fn (PmLease $l) => $this->mapLeaseListRow($l, true))
            ->all();

        return property_view('property.agent.tenants.leases', array_merge([
            'activeTab' => $activeTab,
            'stats' => $stats,
            'columns' => ['', 'Lease #', 'Tenant', 'Unit(s)', 'Start', 'End', 'Rent', 'Deposit held', 'Expense paid', 'Status', 'Actions'],
            'tableRows' => $rows,
            'expiryFilterTexts' => [],
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'leasePager' => $leasePager,
            'openLeaseCreateModal' => $request->boolean('open_create'),
        ], $cascadeViewData, $this->leaseFormPageContext($request)));
    }

    public function createForm(Request $request): View
    {
        return property_view('property.agent.tenants.lease_create_form', $this->leaseCreateFormViewData());
    }

    /**
     * @return array<string, mixed>
     */
    private function leaseCreateFormViewData(?Request $request = null): array
    {
        $request ??= request();
        $selectedTenantId = max(0, (int) old('pm_tenant_id', $request->query('pm_tenant_id', 0)));
        $selectedUnitId = max(0, (int) collect(old('property_unit_ids', []))->first());
        if ($selectedUnitId <= 0) {
            $selectedUnitId = max(0, (int) $request->query('unit_id', 0));
        }

        return array_merge($this->leaseFormStaticContext(), [
            'tenants' => collect(),
            'vacantUnits' => collect(),
            'vacantProperties' => collect(),
            'utilityChargeTemplatesByProperty' => [],
            'depositDefinitionsByProperty' => [],
            'leaseFormEndpoints' => $this->leaseFormEndpointUrls(),
            'leaseFormSelectedTenantId' => $selectedTenantId,
            'leaseFormSelectedUnitId' => $selectedUnitId,
        ]);
    }

    private function renderLeaseCreateFormResponse(Request $request, int $status = 200, ?Validator $validator = null): Response
    {
        $view = property_view('property.agent.tenants.lease_create_form', $this->leaseCreateFormViewData($request))
            ->withInput($request->except('_token'));
        if ($validator !== null) {
            $view->withErrors($validator);
        }

        return response($view, $status);
    }

    private function renderLeaseCreateSuccessResponse(): Response
    {
        return response(property_view('property.agent.tenants.lease_create_success', [
            'leasesUrl' => route('property.tenants.leases', absolute: false),
            'message' => 'Lease saved.',
        ]));
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $fromCreateModal = $request->header('Turbo-Frame') === 'lease-create-modal';

        try {
            return $this->storeLease($request, $fromCreateModal);
        } catch (ValidationException $e) {
            if ($fromCreateModal) {
                return $this->renderLeaseCreateFormResponse($request, 422, $e->validator);
            }

            throw $e;
        }
    }

    private function storeLease(Request $request, bool $fromCreateModal): RedirectResponse|Response
    {
        $cfg = $this->leaseFieldConfig();
        $data = $request->validate([
            'pm_tenant_id' => [Rule::requiredIf($this->isFieldRequired($cfg, 'tenant_id')), 'nullable', 'exists:pm_tenants,id'],
            'start_date' => [Rule::requiredIf($this->isFieldRequired($cfg, 'start_date')), 'nullable', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => [Rule::requiredIf($this->isFieldRequired($cfg, 'rent_amount')), 'nullable', 'numeric', 'min:0'],
            'deposit_amount' => [Rule::requiredIf($this->isFieldRequired($cfg, 'deposit_amount')), 'nullable', 'numeric', 'min:0'],
            'utility_expense_type' => ['nullable', 'string', 'max:50'],
            'utility_expense_rate' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'utility_expenses' => ['nullable', 'array', 'max:20'],
            'utility_expenses.*.type' => ['nullable', 'string', 'max:50'],
            'utility_expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'status' => [Rule::requiredIf($this->isFieldRequired($cfg, 'status')), 'nullable', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => [Rule::requiredIf($this->isFieldRequired($cfg, 'property_unit_id')), 'nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears' => ['nullable', 'array', 'max:20'],
            'opening_arrears.*.charge_type' => ['nullable', 'string', 'max:50'],
            'opening_arrears.*.specific_charge' => ['nullable', 'string', 'max:100'],
            'opening_arrears.*.period' => ['nullable', 'date_format:Y-m'],
            'opening_arrears.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_rent_arrears' => ['nullable', 'numeric', 'min:0'],
            'opening_rent_arrears_period' => ['nullable', 'date_format:Y-m'],
            'opening_rent_arrears_details' => ['nullable', 'string', 'max:120'],
            'opening_deposit_arrears' => ['nullable', 'array', 'max:20'],
            'opening_deposit_arrears.*' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_manual_total' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_as_of_date' => ['nullable', 'date'],
            'opening_arrears_note' => ['nullable', 'string', 'max:500'],
            'carry_forward_submitted' => ['sometimes', 'boolean'],
            'carry_forward_touched' => ['sometimes', 'boolean'],
        ]);

        $data['status'] = (string) ($data['status'] ?? PmLease::STATUS_ACTIVE);
        $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));
        $property = app(\App\Services\Property\PropertyManagementGuardService::class)->propertyForUnitIds($unitIds);
        if ($property) {
            app(\App\Services\Property\PropertyManagementGuardService::class)->assertCanCreateLease($property);
        }
        $depositLines = $this->prepareLeaseDepositLines($data, $unitIds);
        $utilityExpenses = $this->normalizeUtilityExpenses((array) ($data['utility_expenses'] ?? []));
        $legacyType = trim((string) ($data['utility_expense_type'] ?? ''));
        $legacyAmountRaw = $data['utility_expense_rate'] ?? ($data['utility_expense_amount'] ?? null);
        if ($legacyType !== '' && is_numeric($legacyAmountRaw) && (float) $legacyAmountRaw > 0) {
            array_unshift($utilityExpenses, [
                'type' => mb_substr($legacyType, 0, 50),
                'amount' => number_format((float) $legacyAmountRaw, 2, '.', ''),
            ]);
        }
        $this->assertUtilityExpenseTypesAllowed($utilityExpenses, $unitIds);
        $firstUtility = $utilityExpenses[0] ?? null;

        DB::transaction(function () use ($request, $data, $utilityExpenses, $firstUtility, $unitIds, $depositLines) {
            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, null);
            }

            $payload = [
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $firstUtility['type'] ?? ($data['utility_expense_type'] ?? null),
                'utility_expense_amount' => isset($firstUtility['amount']) ? (float) $firstUtility['amount'] : ($data['utility_expense_rate'] ?? null),
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ];
            $this->applyRentDueDayToPayload($payload, $data);

            if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
                $payload['additional_deposits'] = $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []));
            }
            $this->applyOpeningArrearsToPayload($payload, $request, $data, null, $unitIds);
            if (Schema::hasColumn('pm_leases', 'utility_expenses')) {
                $payload['utility_expenses'] = $utilityExpenses;
            }

            $lease = PmLease::query()->create($payload);

            $lease->load('pmTenant');
            if ($unitIds !== []) {
                $lease->units()->sync($unitIds);
                if ($data['status'] === PmLease::STATUS_ACTIVE) {
                    PropertyUnit::query()->whereIn('id', $unitIds)->update([
                        'status' => PropertyUnit::STATUS_OCCUPIED,
                        'vacant_since' => null,
                    ]);
                    $this->ensureMovementLogged($lease, $unitIds, 'move_in', $data['start_date'] ?? null);
                } elseif (in_array($data['status'], [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED], true)) {
                    $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                    $this->ensureMovementLogged($lease, $unitIds, 'move_out', $data['end_date'] ?? null);
                }
            }
            $this->syncLeaseDepositLines($lease, $depositLines);

            $this->syncLeaseRevenuePostings($lease);
        });

        if ($fromCreateModal) {
            return $this->renderLeaseCreateSuccessResponse();
        }

        return redirect()
            ->route('property.tenants.leases', absolute: false)
            ->with('success', 'Lease saved.');
    }

    /**
     * @return array<string,array{enabled:bool,required:bool}>
     */
    private function leaseFieldConfig(): array
    {
        $defaults = [
            'tenant_id' => ['enabled' => true, 'required' => true],
            'property_unit_id' => ['enabled' => true, 'required' => true],
            'start_date' => ['enabled' => true, 'required' => true],
            'end_date' => ['enabled' => true, 'required' => false],
            'rent_amount' => ['enabled' => true, 'required' => true],
            'deposit_amount' => ['enabled' => true, 'required' => false],
            'status' => ['enabled' => true, 'required' => true],
        ];
        $raw = PropertyPortalSetting::getValue('system_setup_lease_fields_json', '');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! array_key_exists($key, $defaults)) {
                continue;
            }
            $defaults[$key]['enabled'] = ! array_key_exists('enabled', $row) || (bool) $row['enabled'];
            $defaults[$key]['required'] = (bool) ($row['required'] ?? false);
        }

        return $defaults;
    }

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $config
     */
    private function isFieldRequired(array $config, string $field): bool
    {
        return (bool) (($config[$field]['enabled'] ?? false) && ($config[$field]['required'] ?? false));
    }

    /**
     * @return array<string,string>
     */
    private function openingArrearsTypeOptions(): array
    {
        return [
            'rent' => 'Rent',
            'water' => 'Water',
            'electricity' => 'Electricity',
            'service_charge' => 'Service charge',
            'garbage' => 'Garbage',
            'internet' => 'Internet',
            'parking' => 'Parking',
            'utility_other' => 'Other utility',
            'penalty' => 'Penalty',
            'other' => 'Other charge',
            'custom_charge' => 'Custom charge',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildOpeningArrearsPayload(array $data): array
    {
        $items = collect((array) ($data['opening_arrears_items'] ?? []))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): array {
                return [
                    'type' => (string) ($item['type'] ?? ''),
                    'period' => (string) ($item['period'] ?? ''),
                    'amount' => round((float) ($item['amount'] ?? 0), 2),
                    'label' => trim((string) ($item['label'] ?? '')),
                    'reference' => trim((string) ($item['reference'] ?? '')),
                ];
            })
            ->filter(fn (array $item): bool => $item['type'] !== '' && $item['period'] !== '' && $item['amount'] > 0)
            ->values();

        $categories = [
            'opening_arrears_rent' => 0.0,
            'opening_arrears_utilities' => 0.0,
            'opening_arrears_penalties' => 0.0,
            'opening_arrears_other' => 0.0,
        ];
        $utilityTypes = ['water', 'electricity', 'service_charge', 'garbage', 'internet', 'parking', 'utility_other'];
        foreach ($items as $item) {
            $type = (string) $item['type'];
            $amount = (float) $item['amount'];
            if ($type === 'rent') {
                $categories['opening_arrears_rent'] += $amount;
            } elseif ($type === 'penalty') {
                $categories['opening_arrears_penalties'] += $amount;
            } elseif (in_array($type, $utilityTypes, true)) {
                $categories['opening_arrears_utilities'] += $amount;
            } else {
                $categories['opening_arrears_other'] += $amount;
            }
        }

        $computedTotal = array_sum($categories);
        $manualTotal = (float) ($data['opening_arrears_amount'] ?? 0);
        $total = $computedTotal > 0 ? $computedTotal : $manualTotal;
        $asOf = $total > 0 ? ($data['opening_arrears_as_of'] ?? now()->toDateString()) : null;

        return [
            'opening_arrears_rent' => (float) $categories['opening_arrears_rent'],
            'opening_arrears_utilities' => (float) $categories['opening_arrears_utilities'],
            'opening_arrears_penalties' => (float) $categories['opening_arrears_penalties'],
            'opening_arrears_other' => (float) $categories['opening_arrears_other'],
            'opening_arrears_amount' => $total,
            'opening_arrears_as_of' => $asOf,
            'opening_arrears_notes' => $data['opening_arrears_notes'] ?? null,
            'opening_arrears_items' => $items->all(),
        ];
    }

    public function show(PmLease $lease): View
    {
        $lease->load([
            'pmTenant',
            'units.property',
        ]);

        $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.$u->label)->implode(', ');
        $daysLeft = $lease->end_date
            ? ($lease->end_date->isBefore(today()) ? 0 : (int) today()->diffInDays($lease->end_date))
            : null;
        $isEndingSoon = $lease->status === PmLease::STATUS_ACTIVE
            && $lease->end_date
            && $lease->end_date->lte(now()->addDays(60));

        $carryForwardTotal = $this->leaseCarryForwardTotal($lease);

        return property_view('property.agent.tenants.lease_show', [
            'lease' => $lease,
            'unitsLabel' => $units !== '' ? $units : '—',
            'daysLeft' => $daysLeft,
            'isEndingSoon' => $isEndingSoon,
            'carryForwardTotal' => $carryForwardTotal,
        ]);
    }

    public function edit(Request $request, PmLease $lease): View
    {
        $lease->load(['pmTenant', 'units.property']);
        $utilityChargeTemplatesByProperty = $this->utilityChargeTemplatesByPropertyMerged();

        $carryForwardTotal = $this->leaseCarryForwardTotal($lease);

        return property_view('property.agent.tenants.lease_edit', array_merge([
            'lease' => $lease,
            'carryForwardTotal' => $carryForwardTotal,
            'tenants' => $this->selectableTenants($lease),
            'units' => $this->leaseAssignableUnits($lease)->get(),
            'vacantProperties' => $this->leaseAssignableUnits($lease)->get()
                ->pluck('property')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'utilityChargeTemplatesByProperty' => $utilityChargeTemplatesByProperty,
            'depositDefinitionsByProperty' => $this->depositDefinitionsByProperty(),
            'leaseTemplate' => PropertyPortalSetting::getValue('template_lease_text', ''),
            'openingArrearsTypeOptions' => $this->openingArrearsTypeOptions(),
        ], $this->propertyFormModalViewData($request)));
    }

    public function update(Request $request, PmLease $lease): RedirectResponse|Response
    {
        $lease->load(['units:id', 'pmTenant']);

        $data = $request->validate([
            'pm_tenant_id' => ['required', 'exists:pm_tenants,id'],
            'start_date' => ['required', 'date'],
            'rent_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expense_type' => ['nullable', 'string', 'max:50'],
            'utility_expense_rate' => ['nullable', 'numeric', 'min:0', 'required_with:utility_expense_type'],
            'utility_expenses' => ['nullable', 'array', 'max:20'],
            'utility_expenses.*.type' => ['nullable', 'string', 'max:50'],
            'utility_expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.rate_per_unit' => ['nullable', 'numeric', 'min:0'],
            'utility_expenses.*.fixed_charge' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,active,expired,terminated'],
            'terms_summary' => ['nullable', 'string', 'max:5000'],
            'property_unit_ids' => ['nullable', 'array', 'max:1'],
            'property_unit_ids.*' => ['integer', 'exists:property_units,id'],
            'additional_deposits' => ['nullable', 'array', 'max:20'],
            'additional_deposits.*.label' => ['nullable', 'string', 'max:100'],
            'additional_deposits.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears' => ['nullable', 'array', 'max:20'],
            'opening_arrears.*.charge_type' => ['nullable', 'string', 'max:50'],
            'opening_arrears.*.specific_charge' => ['nullable', 'string', 'max:100'],
            'opening_arrears.*.period' => ['nullable', 'date_format:Y-m'],
            'opening_arrears.*.amount' => ['nullable', 'numeric', 'min:0'],
            'opening_rent_arrears' => ['nullable', 'numeric', 'min:0'],
            'opening_rent_arrears_period' => ['nullable', 'date_format:Y-m'],
            'opening_rent_arrears_details' => ['nullable', 'string', 'max:120'],
            'opening_deposit_arrears' => ['nullable', 'array', 'max:20'],
            'opening_deposit_arrears.*' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_manual_total' => ['nullable', 'numeric', 'min:0'],
            'opening_arrears_as_of_date' => ['nullable', 'date'],
            'opening_arrears_note' => ['nullable', 'string', 'max:500'],
            'carry_forward_submitted' => ['sometimes', 'boolean'],
            'carry_forward_touched' => ['sometimes', 'boolean'],
        ]);

        $unitIds = $this->normalizeSingleUnitSelection((array) ($data['property_unit_ids'] ?? []));
        $depositLines = $this->prepareLeaseDepositLines($data, $unitIds);
        $utilityExpenses = $this->normalizeUtilityExpenses((array) ($data['utility_expenses'] ?? []));
        $legacyType = trim((string) ($data['utility_expense_type'] ?? ''));
        $legacyAmountRaw = $data['utility_expense_rate'] ?? ($data['utility_expense_amount'] ?? null);
        if ($legacyType !== '' && is_numeric($legacyAmountRaw) && (float) $legacyAmountRaw > 0) {
            array_unshift($utilityExpenses, [
                'type' => mb_substr($legacyType, 0, 50),
                'amount' => number_format((float) $legacyAmountRaw, 2, '.', ''),
            ]);
        }
        $this->assertUtilityExpenseTypesAllowed($utilityExpenses, $unitIds);
        $firstUtility = $utilityExpenses[0] ?? null;

        $beforeSnapshot = [
            'monthly_rent' => round((float) $lease->monthly_rent, 2),
            'status' => (string) $lease->status,
            'start_date' => optional($lease->start_date)->toDateString(),
            'end_date' => optional($lease->end_date)->toDateString(),
            'pm_tenant_id' => (int) $lease->pm_tenant_id,
            'deposit_amount' => round((float) $lease->deposit_amount, 2),
            'rent_due_day' => (int) ($lease->rent_due_day ?? 0),
        ];

        DB::transaction(function () use ($request, $data, $lease, $utilityExpenses, $firstUtility, $unitIds, $depositLines) {
            $prevStatus = $lease->status;
            $prevUnitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            if ($data['status'] === PmLease::STATUS_ACTIVE) {
                $this->assertActiveLeaseRules((int) $data['pm_tenant_id'], $unitIds, (int) $lease->id);
            }

            $payload = [
                'pm_tenant_id' => $data['pm_tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'],
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'utility_expense_type' => $firstUtility['type'] ?? ($data['utility_expense_type'] ?? null),
                'utility_expense_amount' => isset($firstUtility['amount']) ? (float) $firstUtility['amount'] : ($data['utility_expense_rate'] ?? null),
                'status' => $data['status'],
                'terms_summary' => ($data['terms_summary'] ?? '') !== ''
                    ? $data['terms_summary']
                    : PropertyPortalSetting::getValue('template_lease_text', null),
            ];
            $this->applyRentDueDayToPayload($payload, $data);
            if (Schema::hasColumn('pm_leases', 'additional_deposits')) {
                $payload['additional_deposits'] = $this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? []));
            }
            $this->applyOpeningArrearsToPayload($payload, $request, $data, $lease, $unitIds);
            if (Schema::hasColumn('pm_leases', 'utility_expenses')) {
                $payload['utility_expenses'] = $utilityExpenses;
            }

            $lease->update($payload);

            if ($unitIds !== []) {
                $lease->units()->sync($unitIds);
            }
            $lease->load('units.property');

            // If lease is active, mark linked units occupied.
            if ($data['status'] === PmLease::STATUS_ACTIVE && $unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
                // Log move-in if we just activated or attached units.
                $this->ensureMovementLogged($lease, $unitIds, 'move_in', $data['start_date'] ?? null);
            }

            // If lease is ended, vacate its units (unless another active lease also owns them).
            if (in_array($data['status'], [PmLease::STATUS_EXPIRED, PmLease::STATUS_TERMINATED], true) && $unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                $this->ensureMovementLogged($lease, $unitIds, 'move_out', $data['end_date'] ?? null);
            }

            // If an active lease had units removed, vacate those removed units (unless another active lease owns them).
            if ($prevStatus === PmLease::STATUS_ACTIVE) {
                $removed = array_values(array_diff($prevUnitIds, array_map('intval', $unitIds)));
                if ($removed !== []) {
                    $this->vacateUnitsIfNotInAnotherActiveLease($removed, excludeLeaseId: $lease->id);
                    $this->ensureMovementLogged($lease, $removed, 'move_out', now()->toDateString());
                }
            }
            $this->syncLeaseDepositLines($lease, $depositLines);

            $this->syncLeaseRevenuePostings($lease->fresh(['units.property']));
        });

        $lease->refresh();
        $afterSnapshot = [
            'monthly_rent' => round((float) $lease->monthly_rent, 2),
            'status' => (string) $lease->status,
            'start_date' => optional($lease->start_date)->toDateString(),
            'end_date' => optional($lease->end_date)->toDateString(),
            'pm_tenant_id' => (int) $lease->pm_tenant_id,
            'deposit_amount' => round((float) $lease->deposit_amount, 2),
            'rent_due_day' => (int) ($lease->rent_due_day ?? 0),
        ];
        $leaseChanges = [];
        foreach ($beforeSnapshot as $field => $oldValue) {
            $newValue = $afterSnapshot[$field] ?? null;
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }
            $leaseChanges[$field] = ['from' => $oldValue, 'to' => $newValue];
        }
        PropertyActivityLogger::leaseUpdated($lease, $leaseChanges, $request->user());

        $carryForwardTotal = $this->leaseCarryForwardTotal($lease);
        $message = 'Lease updated.';
        if (! Schema::hasColumn('pm_leases', 'opening_arrears')) {
            $message .= ' Warning: carry-forward columns are missing on this server — run database migrations.';
        } elseif ($this->carryForwardWasPosted($request)) {
            if ($carryForwardTotal > 0) {
                $message .= ' Carry-forward saved: '.PropertyMoney::kes($carryForwardTotal).'.';
            } else {
                $message .= ' Carry-forward cleared.';
            }
        }

        return $this->redirectOrPropertyFormModalSuccess(
            $request,
            redirect()
                ->route('property.leases.show', $lease, absolute: false)
                ->with('success', $message),
            $message,
        );
    }

    public function bulk(Request $request): RedirectResponse
    {
        $action = strtolower((string) $request->input('action', ''));

        if ($action === '' || ! in_array($action, ['activate', 'terminate', 'restore', 'delete'], true)) {
            return back()->withErrors(['bulk' => 'Choose a bulk action.']);
        }

        $ids = collect($request->input('lease_ids', []))->map(fn ($v) => (int) $v)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return back()->withErrors(['bulk' => 'Select at least one lease.']);
        }

        $leases = PmLease::query()
            ->with(['units:id', 'pmTenant'])
            ->whereIn('id', $ids)
            ->get();

        $applied = 0;
        $skipped = 0;
        $messages = [];

        foreach ($leases as $lease) {
            try {
                match ($action) {
                    'activate' => $this->activateLease($lease),
                    'terminate' => $this->terminateLease($lease),
                    'restore' => $this->restoreLease($lease),
                    'delete' => $this->deleteLease($lease, draftOnly: true),
                };
                $applied++;
            } catch (ValidationException $e) {
                $skipped++;
                $first = collect($e->errors())->flatten()->first();
                $messages[] = 'Lease #'.$lease->id.': '.(is_string($first) ? $first : 'Could not apply action.');
            }
        }

        $label = match ($action) {
            'activate' => 'activated',
            'terminate' => 'terminated',
            'restore' => 'restored',
            'delete' => 'deleted',
        };

        $summary = ucfirst($label).' '.$applied.' lease(s)';
        if ($skipped > 0) {
            $summary .= ", skipped {$skipped}";
        }
        $summary .= '.';

        if ($messages !== []) {
            return back()
                ->with('warning', $summary)
                ->with('bulk_lease_errors', array_slice($messages, 0, 8));
        }

        return back()->with('success', $summary);
    }

    public function terminate(PmLease $lease): RedirectResponse
    {
        $this->terminateLease($lease);

        return back()->with('success', 'Lease terminated.');
    }

    public function restore(PmLease $lease): RedirectResponse
    {
        $this->restoreLease($lease);

        return back()->with('success', 'Lease restored to active.');
    }

    public function destroy(PmLease $lease): RedirectResponse
    {
        $this->deleteLease($lease, draftOnly: false);

        return back()->with('success', 'Lease deleted.');
    }

    private function activateLease(PmLease $lease): void
    {
        if ($lease->status === PmLease::STATUS_ACTIVE) {
            return;
        }

        $lease->loadMissing(['units:id', 'pmTenant']);
        $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();

        $property = app(\App\Services\Property\PropertyManagementGuardService::class)->propertyForUnitIds($unitIds);
        if ($property) {
            app(\App\Services\Property\PropertyManagementGuardService::class)->assertCanCreateLease($property);
        }

        DB::transaction(function () use ($lease, $unitIds): void {
            $this->assertActiveLeaseRules((int) $lease->pm_tenant_id, $unitIds, (int) $lease->id);

            $lease->update(['status' => PmLease::STATUS_ACTIVE]);

            if ($unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
                $this->ensureMovementLogged($lease, $unitIds, 'move_in', $lease->start_date?->toDateString());
            }

            $this->syncLeaseRevenuePostings($lease);
        });
    }

    private function terminateLease(PmLease $lease): void
    {
        if ($lease->status === PmLease::STATUS_TERMINATED) {
            return;
        }

        $lease->loadMissing(['units:id', 'pmTenant']);

        DB::transaction(function () use ($lease): void {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();
            $today = now()->toDateString();

            $lease->update([
                'status' => PmLease::STATUS_TERMINATED,
                'end_date' => $lease->end_date ?? $today,
            ]);

            if ($unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
                $this->ensureMovementLogged($lease, $unitIds, 'move_out', $today);
            }

            $property = app(\App\Services\Property\PropertyManagementGuardService::class)->propertyForUnitIds($unitIds);
            if ($property && $property->isOffboarding()) {
                app(\App\Services\Property\PropertyOffboardingService::class)
                    ->logLeaseTerminatedDuringOffboarding($lease, $property);
            }
        });
    }

    private function restoreLease(PmLease $lease): void
    {
        if ($lease->status !== PmLease::STATUS_TERMINATED) {
            throw ValidationException::withMessages([
                'lease' => 'Only terminated leases can be restored.',
            ]);
        }

        $lease->loadMissing(['units:id', 'pmTenant']);
        $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();

        $property = app(\App\Services\Property\PropertyManagementGuardService::class)->propertyForUnitIds($unitIds);
        if ($property) {
            app(\App\Services\Property\PropertyManagementGuardService::class)->assertCanCreateLease($property);
        }

        DB::transaction(function () use ($lease, $unitIds): void {
            $this->assertActiveLeaseRules((int) $lease->pm_tenant_id, $unitIds, (int) $lease->id);

            $startDate = $lease->start_date?->toDateString() ?? now()->toDateString();

            $lease->update([
                'status' => PmLease::STATUS_ACTIVE,
                'end_date' => null,
            ]);

            if ($unitIds !== []) {
                PropertyUnit::query()->whereIn('id', $unitIds)->update([
                    'status' => PropertyUnit::STATUS_OCCUPIED,
                    'vacant_since' => null,
                ]);
                $this->ensureMovementLogged($lease, $unitIds, 'move_in', $startDate);
            }
        });
    }

    private function deleteLease(PmLease $lease, bool $draftOnly = false): void
    {
        if ($draftOnly && $lease->status !== PmLease::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'lease' => 'Only draft leases can be deleted in bulk. Terminate active leases instead.',
            ]);
        }

        $lease->loadMissing(['units:id', 'pmTenant']);

        DB::transaction(function () use ($lease): void {
            $unitIds = $lease->units->pluck('id')->map(fn ($v) => (int) $v)->all();

            if ($unitIds !== []) {
                $this->vacateUnitsIfNotInAnotherActiveLease($unitIds, excludeLeaseId: $lease->id);
            }

            app(CarryForwardConsolidationService::class)->purgeLeaseOnDelete($lease);

            $lease->delete();
        });
    }

    public function expiry(): RedirectResponse
    {
        return redirect()->route('property.tenants.leases', ['tab' => 'expiry']);
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,int>  $unitIds
     * @return array<int,array<string,mixed>>
     */
    private function prepareLeaseDepositLines(array $data, array $unitIds): array
    {
        if (! Schema::hasTable('lease_deposit_lines')) {
            return [];
        }

        $unit = null;
        if ($unitIds !== []) {
            $unit = PropertyUnit::query()->select(['id', 'property_id'])->find($unitIds[0]);
            if (! $unit) {
                throw ValidationException::withMessages([
                    'property_unit_ids' => ['Selected unit is invalid.'],
                ]);
            }
        }

        $monthlyRent = (float) ($data['monthly_rent'] ?? 0);
        $submitted = $this->submittedDepositPayload($data);
        if (! $unit) {
            return array_values($submitted);
        }

        $definitions = $this->resolveDepositDefinitions((int) $unit->property_id, (int) $unit->id);
        if ($definitions->isEmpty()) {
            return array_values($submitted);
        }

        $allowedKeys = $definitions->pluck('deposit_key')->map(fn ($v) => (string) $v)->all();
        $allowCustom = (bool) (auth()->user()?->is_super_admin)
            || PropertyPortalSetting::getValue('lease_deposit_allow_custom_types', '0') === '1';

        $unknownKeys = array_values(array_diff(array_keys($submitted), $allowedKeys));
        if ($unknownKeys !== [] && ! $allowCustom) {
            throw ValidationException::withMessages([
                'additional_deposits' => ['One or more deposit types are not allowed for the selected property/unit.'],
            ]);
        }

        $lines = [];
        foreach ($definitions as $definition) {
            $key = (string) $definition->deposit_key;
            $submittedLine = $submitted[$key] ?? null;
            $expected = $submittedLine['expected_amount'] ?? $this->definitionDefaultAmount($definition, $monthlyRent);

            if ((bool) $definition->is_required && $expected <= 0) {
                throw ValidationException::withMessages([
                    'deposit_amount' => ["Required deposit \"{$definition->label}\" is missing."],
                ]);
            }

            if ($expected <= 0 && ! $submittedLine) {
                continue;
            }

            $lines[] = [
                'deposit_definition_id' => (int) $definition->id,
                'deposit_key' => $key,
                'label' => (string) ($submittedLine['label'] ?? $definition->label),
                'expected_amount' => $expected,
                'paid_amount' => 0.0,
                'balance_amount' => $expected,
                'is_refundable' => (bool) $definition->is_refundable,
                'refund_status' => 'not_refunded',
                'meta' => [
                    'is_required' => (bool) $definition->is_required,
                    'ledger_account' => $definition->ledger_account,
                    'amount_mode' => $definition->amount_mode,
                    'amount_value' => (float) $definition->amount_value,
                ],
            ];
        }

        if ($allowCustom) {
            foreach ($unknownKeys as $key) {
                $line = $submitted[$key] ?? null;
                if (! $line) {
                    continue;
                }
                $lines[] = $line + [
                    'deposit_definition_id' => null,
                    'is_refundable' => true,
                    'refund_status' => 'not_refunded',
                    'meta' => ['custom' => true],
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,array<string,mixed>>
     */
    private function submittedDepositPayload(array $data): array
    {
        $lines = [];

        $rentDeposit = (float) ($data['deposit_amount'] ?? 0);
        if ($rentDeposit > 0) {
            $lines['rent_deposit'] = [
                'deposit_definition_id' => null,
                'deposit_key' => 'rent_deposit',
                'label' => 'Rent deposit',
                'expected_amount' => $rentDeposit,
                'paid_amount' => 0.0,
                'balance_amount' => $rentDeposit,
                'is_refundable' => true,
                'refund_status' => 'not_refunded',
                'meta' => ['source' => 'rent_deposit_input'],
            ];
        }

        foreach ($this->normalizeAdditionalDeposits((array) ($data['additional_deposits'] ?? [])) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($label === '' || $amount <= 0) {
                continue;
            }

            $key = $this->normalizeDepositKey($label);
            if ($key === '') {
                continue;
            }

            if (isset($lines[$key])) {
                $lines[$key]['expected_amount'] += $amount;
                $lines[$key]['balance_amount'] += $amount;
                continue;
            }

            $lines[$key] = [
                'deposit_definition_id' => null,
                'deposit_key' => $key,
                'label' => $label,
                'expected_amount' => $amount,
                'paid_amount' => 0.0,
                'balance_amount' => $amount,
                'is_refundable' => true,
                'refund_status' => 'not_refunded',
                'meta' => ['source' => 'additional_deposits'],
            ];
        }

        return $lines;
    }

    private function normalizeDepositKey(string $label): string
    {
        return (string) Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/i', '_')->trim('_');
    }

    /**
     * @return Collection<int,DepositDefinition>
     */
    private function resolveDepositDefinitions(int $propertyId, ?int $unitId = null): Collection
    {
        $query = DepositDefinition::query()
            ->where('property_id', $propertyId)
            ->where('is_active', true)
            ->where(function ($scope) use ($unitId): void {
                $scope->whereNull('property_unit_id');
                if ($unitId) {
                    $scope->orWhere('property_unit_id', $unitId);
                }
            })
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end desc')
            ->orderBy('sort_order')
            ->orderBy('id');

        /** @var Collection<int,DepositDefinition> $rows */
        $rows = $query->get();

        return $rows
            ->groupBy(fn (DepositDefinition $definition) => (string) $definition->deposit_key)
            ->map(fn (Collection $bucket) => $bucket->first())
            ->values();
    }

    private function definitionDefaultAmount(DepositDefinition $definition, float $monthlyRent): float
    {
        $value = (float) $definition->amount_value;
        if ($value <= 0) {
            return 0.0;
        }

        if ($definition->amount_mode === DepositDefinition::MODE_PERCENT_RENT) {
            return round(($monthlyRent * $value) / 100, 2);
        }

        return round($value, 2);
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function depositDefinitionsByProperty(): array
    {
        if (! Schema::hasTable('deposit_definitions')) {
            return [];
        }

        $grouped = [];
        DepositDefinition::query()
            ->where('is_active', true)
            ->orderBy('property_id')
            ->orderByRaw('case when property_unit_id is null then 0 else 1 end desc')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (DepositDefinition $definition) use (&$grouped): void {
                $propertyId = (string) $definition->property_id;
                $grouped[$propertyId] ??= [];
                $grouped[$propertyId][] = [
                    'id' => (int) $definition->id,
                    'property_unit_id' => $definition->property_unit_id ? (int) $definition->property_unit_id : null,
                    'deposit_key' => (string) $definition->deposit_key,
                    'label' => (string) $definition->label,
                    'is_required' => (bool) $definition->is_required,
                    'amount_mode' => (string) $definition->amount_mode,
                    'amount_value' => (float) $definition->amount_value,
                    'is_refundable' => (bool) $definition->is_refundable,
                    'ledger_account' => $definition->ledger_account,
                    'sort_order' => (int) $definition->sort_order,
                ];
            });

        return $grouped;
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines
     */
    private function syncLeaseDepositLines(PmLease $lease, array $lines): void
    {
        if (! Schema::hasTable('lease_deposit_lines')) {
            return;
        }

        LeaseDepositLine::query()->where('pm_lease_id', $lease->id)->delete();
        if ($lines === []) {
            return;
        }

        $now = now();
        $payload = array_map(function (array $line) use ($lease, $now): array {
            return [
                'pm_lease_id' => (int) $lease->id,
                'deposit_definition_id' => $line['deposit_definition_id'] ?? null,
                'deposit_key' => (string) ($line['deposit_key'] ?? ''),
                'label' => (string) ($line['label'] ?? 'Deposit'),
                'expected_amount' => (float) ($line['expected_amount'] ?? 0),
                'paid_amount' => (float) ($line['paid_amount'] ?? 0),
                'balance_amount' => (float) ($line['balance_amount'] ?? 0),
                'is_refundable' => (bool) ($line['is_refundable'] ?? true),
                'refund_status' => (string) ($line['refund_status'] ?? 'not_refunded'),
                'meta' => $line['meta'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $lines);

        LeaseDepositLine::query()->insert($payload);
    }
}
