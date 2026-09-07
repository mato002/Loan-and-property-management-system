<?php

namespace App\Modules\Reporting\Support;

use App\Models\Property;
use App\Models\User;
use App\Support\Property\PropertyFilterCascadeCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ReportScope
{
    /**
     * @return array{
     *     q: string,
     *     from: ?string,
     *     to: ?string,
     *     property_id: string,
     *     unit_id: string,
     *     tenant_id: string,
     *     pm_tenant_id: string,
     *     landlord_id: string,
     *     per_page: string
     * }
     */
    public static function fromRequest(): array
    {
        $tenantId = max(
            0,
            (int) request()->query('tenant_id', 0),
            (int) request()->query('pm_tenant_id', 0),
        );

        return [
            'q' => mb_substr(trim((string) request()->query('q', '')), 0, 120),
            'from' => self::dateQuery('from'),
            'to' => self::dateQuery('to'),
            'property_id' => (string) max(0, (int) request()->query('property_id', 0)),
            'unit_id' => (string) max(0, (int) request()->query('unit_id', 0)),
            'tenant_id' => (string) $tenantId,
            'pm_tenant_id' => (string) $tenantId,
            'landlord_id' => (string) max(0, (int) request()->query('landlord_id', 0)),
            'per_page' => (string) min(200, max(10, (int) request()->integer('per_page', 30))),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function toolbar(array $filters): array
    {
        $cascade = app(PropertyFilterCascadeCatalog::class);
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $tenantId = (int) ($filters['tenant_id'] ?? $filters['pm_tenant_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        $propertiesQuery = Property::query()->orderBy('name');
        if ($landlordId > 0) {
            $propertiesQuery->whereHas('landlords', function ($query) use ($landlordId): void {
                $query->where('users.id', $landlordId);
            });
        }

        return [
            'properties' => $propertiesQuery->get(['id', 'name']),
            'units' => $cascade->unitsForProperty($propertyId),
            'tenantsForFilter' => $cascade->leaseTenantsForFilter($tenantId, $propertyId, $unitId),
            'landlords' => User::query()
                ->where('property_portal_role', 'landlord')
                ->orderBy('name')
                ->get(['id', 'name']),
            'filterCascadeCatalog' => $cascade->fromLeases(),
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToLease(Builder $query, array $filters): void
    {
        app(PropertyFilterCascadeCatalog::class)->applyToLeaseQuery($query, $filters);
        self::applyLandlordViaRelation($query, $filters, 'units.property.landlords');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToInvoice(Builder $query, array $filters): void
    {
        app(PropertyFilterCascadeCatalog::class)->applyToInvoiceQuery($query, $filters);
        self::applyLandlordViaRelation($query, $filters, 'unit.property.landlords');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToPayment(Builder $query, array $filters): void
    {
        app(PropertyFilterCascadeCatalog::class)->applyToPaymentQuery($query, $filters);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        if ($landlordId <= 0) {
            return;
        }

        $query->whereExists(function ($sub) use ($landlordId): void {
            $sub->selectRaw('1')
                ->from('pm_payment_allocations as a')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as u', 'u.id', '=', 'i.property_unit_id')
                ->join('property_landlord as pl', 'pl.property_id', '=', 'u.property_id')
                ->whereColumn('a.pm_payment_id', 'pm_payments.id')
                ->where('pl.user_id', $landlordId);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToUnitModel(Builder $query, array $filters, string $unitIdColumn = 'property_unit_id'): void
    {
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $tenantId = (int) ($filters['tenant_id'] ?? $filters['pm_tenant_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        if ($unitId > 0) {
            $query->where($unitIdColumn, $unitId);
        } elseif ($propertyId > 0) {
            $query->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        if ($tenantId > 0) {
            $query->whereHas('unit.leases', fn (Builder $leaseQuery) => $leaseQuery->where('pm_tenant_id', $tenantId));
        }

        if ($landlordId > 0) {
            self::applyLandlordViaRelation($query, $filters, 'unit.property.landlords');
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToMaintenanceRequest(Builder $query, array $filters): void
    {
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        if ($unitId > 0) {
            $query->where('property_unit_id', $unitId);
        } elseif ($propertyId > 0) {
            $query->whereHas('unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        if ($landlordId > 0) {
            self::applyLandlordViaRelation($query, $filters, 'unit.property.landlords');
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToMaintenanceJob(Builder $query, array $filters): void
    {
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        if ($unitId > 0) {
            $query->whereHas('request', fn (Builder $requestQuery) => $requestQuery->where('property_unit_id', $unitId));
        } elseif ($propertyId > 0) {
            $query->whereHas('request.unit', fn (Builder $unitQuery) => $unitQuery->where('property_id', $propertyId));
        }

        if ($landlordId > 0) {
            self::applyLandlordViaRelation($query, $filters, 'request.unit.property.landlords');
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToPropertyId($query, array $filters, string $column = 'property_id'): void
    {
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        if ($propertyId > 0) {
            $query->where($column, $propertyId);
        }

        if ($landlordId > 0) {
            $query->whereIn($column, function ($sub) use ($landlordId): void {
                $sub->select('property_id')
                    ->from('property_landlord')
                    ->where('user_id', $landlordId);
            });
        }
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyToJoinedPropertyUnit($query, array $filters, string $propertyCol = 'p.id', string $unitCol = 'u.id', ?string $tenantCol = null): void
    {
        $propertyId = (int) ($filters['property_id'] ?? 0);
        $unitId = (int) ($filters['unit_id'] ?? 0);
        $tenantId = (int) ($filters['tenant_id'] ?? $filters['pm_tenant_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);

        if ($unitId > 0) {
            $query->where($unitCol, $unitId);
        } elseif ($propertyId > 0) {
            $query->where($propertyCol, $propertyId);
        }

        if ($tenantId > 0 && $tenantCol !== null) {
            $query->where($tenantCol, $tenantId);
        }

        if ($landlordId > 0) {
            $query->whereExists(function ($sub) use ($landlordId, $propertyCol): void {
                $sub->selectRaw('1')
                    ->from('property_landlord as pl_scope')
                    ->whereColumn('pl_scope.property_id', $propertyCol)
                    ->where('pl_scope.user_id', $landlordId);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function presetFromGroup(string $group, string $reportKey = ''): string
    {
        if (in_array($reportKey, ['maintenance_audit_trail', 'maintenance_email_logs', 'maintenance_login_logs', 'expense_vendor_expense_work'], true)) {
            return 'logs';
        }

        return match (true) {
            str_contains($group, 'Tenant') => 'tenant',
            str_contains($group, 'Landlord') => 'landlord',
            str_contains($group, 'Expense') => 'expense',
            str_contains($group, 'Maintenance') => 'maintenance',
            str_contains($group, 'Financial') => 'financial',
            default => 'tenant',
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    private static function applyLandlordViaRelation(Builder $query, array $filters, string $relation): void
    {
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        if ($landlordId <= 0) {
            return;
        }

        $query->whereHas($relation, function ($landlordQuery) use ($landlordId): void {
            $landlordQuery->where('users.id', $landlordId);
        });
    }

    private static function dateQuery(string $key): ?string
    {
        $value = request()->query($key);
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
