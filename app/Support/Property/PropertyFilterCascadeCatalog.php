<?php

namespace App\Support\Property;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PropertyFilterCascadeCatalog
{
    /**
     * @return array{units: list<array{id: string, label: string, property_id: string, property_name: string}>, tenants: list<array{id: string, name: string, unit_ids: list<string>, property_ids: list<string>}>}
     */
    public function fromInvoices(): array
    {
        return Cache::remember($this->catalogCacheKey('invoices'), 300, function () {
            $units = PropertyUnit::query()
                ->with('property:id,name')
                ->orderBy('property_id')
                ->orderBy('label')
                ->get(['id', 'label', 'property_id']);

            $tenantScopes = [];
            $scopedInvoiceIds = PmInvoice::query()->select('id');
            DB::table('pm_invoices as i')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('i.id', $scopedInvoiceIds)
                ->whereNotNull('i.pm_tenant_id')
                ->whereNotNull('i.property_unit_id')
                ->whereNull('i.deleted_at')
                ->selectRaw('DISTINCT i.pm_tenant_id, i.property_unit_id, pu.property_id')
                ->orderBy('i.pm_tenant_id')
                ->lazy()
                ->each(function ($row) use (&$tenantScopes): void {
                    $this->rememberTenantScope(
                        $tenantScopes,
                        (int) $row->pm_tenant_id,
                        (int) $row->property_unit_id,
                        (int) $row->property_id,
                    );
                });

            return $this->buildCatalog($units, $tenantScopes);
        });
    }

    /**
     * @return array{units: list<array{id: string, label: string, property_id: string, property_name: string}>, tenants: list<array{id: string, name: string, unit_ids: list<string>, property_ids: list<string>}>}
     */
    public function fromLeases(): array
    {
        return Cache::remember($this->catalogCacheKey('leases'), 300, function () {
            $units = PropertyUnit::query()
                ->with('property:id,name')
                ->orderBy('property_id')
                ->orderBy('label')
                ->get(['id', 'label', 'property_id']);

            $tenantScopes = [];
            $scopedLeaseIds = PmLease::query()->select('id');
            DB::table('pm_lease_unit as lu')
                ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                ->whereIn('l.id', $scopedLeaseIds)
                ->whereNotNull('l.pm_tenant_id')
                ->selectRaw('DISTINCT l.pm_tenant_id, lu.property_unit_id, pu.property_id')
                ->orderBy('l.pm_tenant_id')
                ->lazy()
                ->each(function ($row) use (&$tenantScopes): void {
                    $this->rememberTenantScope(
                        $tenantScopes,
                        (int) $row->pm_tenant_id,
                        (int) $row->property_unit_id,
                        (int) $row->property_id,
                    );
                });

            return $this->buildCatalog($units, $tenantScopes);
        });
    }

    /**
     * @return array{units: list<array{id: string, label: string, property_id: string, property_name: string}>, tenants: list<array{id: string, name: string, unit_ids: list<string>, property_ids: list<string>}>}
     */
    public function fromPayments(): array
    {
        return Cache::remember($this->catalogCacheKey('payments'), 300, function () {
            $units = PropertyUnit::query()
                ->with('property:id,name')
                ->orderBy('property_id')
                ->orderBy('label')
                ->get(['id', 'label', 'property_id']);

            $tenantScopes = [];
            $scopedPaymentIds = PmPayment::query()->select('id');
            DB::table('pm_payment_allocations as pa')
                ->join('pm_payments as p', 'p.id', '=', 'pa.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'pa.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('p.id', $scopedPaymentIds)
                ->whereNotNull('p.pm_tenant_id')
                ->whereNotNull('i.property_unit_id')
                ->selectRaw('DISTINCT p.pm_tenant_id, i.property_unit_id, pu.property_id')
                ->orderBy('p.pm_tenant_id')
                ->lazy()
                ->each(function ($row) use (&$tenantScopes): void {
                    $this->rememberTenantScope(
                        $tenantScopes,
                        (int) $row->pm_tenant_id,
                        (int) $row->property_unit_id,
                        (int) $row->property_id,
                    );
                });

            PmPayment::query()
                ->whereNotNull('pm_tenant_id')
                ->whereDoesntHave('allocations')
                ->select('pm_tenant_id')
                ->distinct()
                ->pluck('pm_tenant_id')
                ->each(function ($tenantId) use (&$tenantScopes): void {
                    $tenantScopes[(int) $tenantId] ??= ['unit_ids' => [], 'property_ids' => []];
                });

            return $this->buildCatalog($units, $tenantScopes);
        });
    }

    /**
     * @return array{units: list<array{id: string, label: string, property_id: string, property_name: string}>, tenants: list<array{id: string, name: string, unit_ids: list<string>, property_ids: list<string>}>}
     */
    public function fromUtilityCharges(): array
    {
        $units = PropertyUnit::query()
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('label')
            ->get(['id', 'label', 'property_id']);

        return $this->buildCatalog($units, []);
    }

    /**
     * @return Collection<int, Property>
     */
    public function properties(): Collection
    {
        return Property::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  Builder<PmInvoice>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<PmInvoice>
     */
    public function applyToInvoiceQuery(Builder $query, array $filters): Builder
    {
        if ((int) ($filters['tenant_id'] ?? 0) > 0) {
            $query->where('pm_tenant_id', (int) $filters['tenant_id']);
        }

        if ((int) ($filters['unit_id'] ?? 0) > 0) {
            $query->where('property_unit_id', (int) $filters['unit_id']);
        } elseif ((int) ($filters['property_id'] ?? 0) > 0) {
            $query->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', (int) $filters['property_id']));
        }

        return $query;
    }

    /**
     * @param  Builder<PmPayment>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<PmPayment>
     */
    public function applyToPaymentQuery(Builder $query, array $filters): Builder
    {
        if ((int) ($filters['tenant_id'] ?? 0) > 0) {
            $query->where('pm_tenant_id', (int) $filters['tenant_id']);
        }

        if ((int) ($filters['unit_id'] ?? 0) > 0) {
            $unitId = (int) $filters['unit_id'];
            $query->where(function (Builder $inner) use ($unitId) {
                $inner->whereHas('allocations.invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('property_unit_id', $unitId))
                    ->orWhereHas('tenant.leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_units.id', $unitId));
            });
        } elseif ((int) ($filters['property_id'] ?? 0) > 0) {
            $propertyId = (int) $filters['property_id'];
            $query->where(function (Builder $inner) use ($propertyId) {
                $inner->whereHas('allocations.invoice.unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId))
                    ->orWhereHas('tenant.leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<PmLease>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyToLeaseQuery(Builder $query, array $filters): void
    {
        if ((int) ($filters['pm_tenant_id'] ?? 0) > 0) {
            $query->where('pm_leases.pm_tenant_id', (int) $filters['pm_tenant_id']);
        }

        if ((int) ($filters['unit_id'] ?? 0) > 0) {
            $query->whereHas('units', fn (Builder $unitQuery) => $unitQuery->where('property_units.id', (int) $filters['unit_id']));
        } elseif ((int) ($filters['property_id'] ?? 0) > 0) {
            $query->whereHas('units', fn (Builder $unitQuery) => $unitQuery->where('property_id', (int) $filters['property_id']));
        }
    }

    /**
     * @return Collection<int, PmTenant>
     */
    public function invoiceTenantsForFilter(int $ensureTenantId = 0, int $propertyId = 0, int $unitId = 0): Collection
    {
        return $this->tenantsForScopeQuery(
            PmInvoice::query()->whereNotNull('pm_tenant_id'),
            $ensureTenantId,
            $propertyId,
            $unitId,
            'property_unit_id',
        );
    }

    /**
     * @return Collection<int, PmTenant>
     */
    public function leaseTenantsForFilter(int $ensureTenantId = 0, int $propertyId = 0, int $unitId = 0): Collection
    {
        $query = PmLease::query()->whereNotNull('pm_tenant_id');

        if ($unitId > 0) {
            $query->whereHas('units', fn (Builder $unitQuery) => $unitQuery->where('property_units.id', $unitId));
        } elseif ($propertyId > 0) {
            $query->whereHas('units', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        $tenantIds = $query->distinct()->pluck('pm_tenant_id');

        return $this->resolveTenants($tenantIds, $ensureTenantId);
    }

    /**
     * @return Collection<int, PmTenant>
     */
    public function paymentTenantsForFilter(int $ensureTenantId = 0, int $propertyId = 0, int $unitId = 0): Collection
    {
        $query = PmPayment::query()->whereNotNull('pm_tenant_id');

        if ($unitId > 0) {
            $query->where(function (Builder $inner) use ($unitId) {
                $inner->whereHas('allocations.invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('property_unit_id', $unitId))
                    ->orWhereHas('tenant.leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_units.id', $unitId));
            });
        } elseif ($propertyId > 0) {
            $query->where(function (Builder $inner) use ($propertyId) {
                $inner->whereHas('allocations.invoice.unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId))
                    ->orWhereHas('tenant.leases.units', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
            });
        }

        $tenantIds = $query->distinct()->pluck('pm_tenant_id');

        return $this->resolveTenants($tenantIds, $ensureTenantId);
    }

    /**
     * @return Collection<int, PropertyUnit>
     */
    public function unitsForProperty(int $propertyId = 0): Collection
    {
        $query = PropertyUnit::query()->with('property')->orderBy('property_id')->orderBy('label');
        if ($propertyId > 0) {
            $query->where('property_id', $propertyId);
        }

        return $query->get();
    }

    /**
     * @param  Builder<PmUnitUtilityCharge>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyToUtilityChargeQuery(Builder $query, array $filters): Builder
    {
        if ((int) ($filters['unit_id'] ?? 0) > 0) {
            $query->where('property_unit_id', (int) $filters['unit_id']);
        } elseif ((int) ($filters['property_id'] ?? 0) > 0) {
            $propertyId = (int) $filters['property_id'];
            $query->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function matchesLeaseScope(array $row, array $filters): bool
    {
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $tenantId = (int) ($filters['tenant_id'] ?? $filters['pm_tenant_id'] ?? 0);

        if ($tenantId > 0 && (int) ($row['tenant_id'] ?? 0) !== $tenantId) {
            return false;
        }

        if ($unitId > 0 && (int) ($row['unit_id'] ?? 0) !== $unitId) {
            return false;
        }

        if ($propertyId > 0 && (int) ($row['property_id'] ?? 0) !== $propertyId) {
            return false;
        }

        return true;
    }

    private function catalogCacheKey(string $suffix): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'property.filter_cascade.'.$suffix.'.user.'.$userId;
    }

    /**
     * @param  array<string, array{unit_ids: array<int, bool>, property_ids: array<int, bool>}>  $tenantScopes
     */
    private function rememberTenantScope(array &$tenantScopes, int $tenantId, int $unitId, int $propertyId): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $tenantScopes[$tenantId] ??= ['unit_ids' => [], 'property_ids' => []];

        if ($unitId > 0) {
            $tenantScopes[$tenantId]['unit_ids'][$unitId] = true;
        }

        if ($propertyId > 0) {
            $tenantScopes[$tenantId]['property_ids'][$propertyId] = true;
        }
    }

    /**
     * @param  Collection<int, PropertyUnit>  $units
     * @param  array<string, array{unit_ids: array<int, bool>, property_ids: array<int, bool>}>  $tenantScopes
     * @return array{units: list<array{id: string, label: string, property_id: string, property_name: string}>, tenants: list<array{id: string, name: string, unit_ids: list<string>, property_ids: list<string>}>}
     */
    private function buildCatalog(Collection $units, array $tenantScopes): array
    {
        $tenants = PmTenant::query()
            ->whereIn('id', array_keys($tenantScopes))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'units' => $units->map(static fn (PropertyUnit $unit) => [
                'id' => (string) $unit->id,
                'label' => (string) $unit->label,
                'property_id' => (string) $unit->property_id,
                'property_name' => (string) ($unit->property?->name ?? ''),
            ])->values()->all(),
            'tenants' => $tenants->map(static function (PmTenant $tenant) use ($tenantScopes) {
                $scope = $tenantScopes[(int) $tenant->id] ?? ['unit_ids' => [], 'property_ids' => []];

                return [
                    'id' => (string) $tenant->id,
                    'name' => (string) $tenant->name,
                    'unit_ids' => array_map('strval', array_keys($scope['unit_ids'])),
                    'property_ids' => array_map('strval', array_keys($scope['property_ids'])),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Builder<PmInvoice>  $scopeQuery
     * @return Collection<int, PmTenant>
     */
    private function tenantsForScopeQuery(
        Builder $scopeQuery,
        int $ensureTenantId,
        int $propertyId,
        int $unitId,
        string $unitColumn,
    ): Collection {
        if ($unitId > 0) {
            $scopeQuery->where($unitColumn, $unitId);
        } elseif ($propertyId > 0) {
            $scopeQuery->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        $tenantIds = $scopeQuery->distinct()->pluck('pm_tenant_id');

        return $this->resolveTenants($tenantIds, $ensureTenantId);
    }

    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $tenantIds
     * @return Collection<int, PmTenant>
     */
    private function resolveTenants(Collection|array $tenantIds, int $ensureTenantId): Collection
    {
        $ids = collect($tenantIds);

        if ($ensureTenantId > 0 && ! $ids->contains($ensureTenantId)) {
            $ids->push($ensureTenantId);
        }

        if ($ids->isEmpty()) {
            return PmTenant::query()->whereRaw('1 = 0')->get(['id', 'name']);
        }

        return PmTenant::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
