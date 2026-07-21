<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLandlordRemittanceRequest;
use App\Models\PmLease;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LandlordPortalSnapshotService
{
    /**
     * @return array{
     *   periodLabel: string,
     *   monthValue: string,
     *   fyValue: int,
     *   commissionPct: float,
     *   totals: array<string, int|float>,
     *   propertyBreakdown: Collection<int, array<string, mixed>>,
     *   recentCollections: Collection<int, object>
     * }
     */
    public function buildSnapshot(User $landlord, string $month = '', int $fy = 0): array
    {
        if ($fy < 2000 || $fy > 2100) {
            $fy = (int) now()->year;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $periodStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $periodLabel = $periodStart->format('M Y');
            $monthValue = $month;
        } else {
            $periodStart = Carbon::create($fy, 1, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfYear();
            $periodLabel = 'FY '.$fy;
            $monthValue = '';
        }

        $propertyLinks = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->where('pl.user_id', $landlord->id)
            ->select(['pl.property_id', 'pl.ownership_percent', 'p.name as property_name'])
            ->orderBy('p.name')
            ->get();

        $propertyIds = $propertyLinks->pluck('property_id')->map(fn ($id) => (int) $id)->all();

        $collectedByProperty = collect();
        $pendingByProperty = collect();
        $lastPaidByProperty = collect();
        if ($propertyIds !== []) {
            $collectedByProperty = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
                ->pluck('total', 'property_id');

            $pendingByProperty = app(FinancialReportingFormulaService::class)
                ->outstandingByPropertyId($periodEnd, $propertyIds);

            $lastPaidByProperty = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, MAX(pay.paid_at) as last_paid_at')
                ->pluck('last_paid_at', 'property_id');
        }

        $commissionDefaultPct = $this->defaultCommissionPercent();
        $commissionOverrides = $this->commissionOverrides();

        $unitStatsByProperty = collect();
        $tenantCountByProperty = collect();
        if ($propertyIds !== []) {
            $unitStatsByProperty = PropertyUnit::query()
                ->whereIn('property_id', $propertyIds)
                ->selectRaw('property_id')
                ->selectRaw('COUNT(*) as units_total')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_occupied', [PropertyUnit::STATUS_OCCUPIED])
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as units_vacant', [PropertyUnit::STATUS_VACANT])
                ->groupBy('property_id')
                ->get()
                ->keyBy('property_id')
                ->map(fn ($row) => [
                    'units_total' => (int) $row->units_total,
                    'units_occupied' => (int) $row->units_occupied,
                    'units_vacant' => (int) $row->units_vacant,
                ]);

            $tenantCountByProperty = DB::table('pm_lease_unit as lu')
                ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
                ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('l.status', PmLease::STATUS_ACTIVE)
                ->groupBy('pu.property_id')
                ->selectRaw('pu.property_id as property_id, COUNT(DISTINCT l.pm_tenant_id) as tenant_count')
                ->pluck('tenant_count', 'property_id');
        }

        $propertyBreakdown = $propertyLinks->map(function ($link) use ($collectedByProperty, $pendingByProperty, $lastPaidByProperty, $commissionDefaultPct, $commissionOverrides, $unitStatsByProperty, $tenantCountByProperty) {
            $pid = (int) $link->property_id;
            $ownershipPct = (float) $link->ownership_percent;
            $pct = $ownershipPct / 100;
            $grossCollected = (float) ($collectedByProperty[$pid] ?? 0);
            $grossPending = (float) ($pendingByProperty[$pid] ?? 0);
            $ownerShare = $grossCollected * $pct;
            $pendingShare = $grossPending * $pct;
            $commissionPct = $commissionOverrides[$pid] ?? $commissionDefaultPct;
            $managementFee = $ownerShare * ($commissionPct / 100);
            $netToOwner = $ownerShare - $managementFee;
            $unitStats = $unitStatsByProperty[$pid] ?? ['units_total' => 0, 'units_occupied' => 0, 'units_vacant' => 0];

            return [
                'property_id' => $pid,
                'property_name' => (string) $link->property_name,
                'ownership_percent' => $ownershipPct,
                'gross_collected' => $grossCollected,
                'owner_share' => $ownerShare,
                'pending_share' => $pendingShare,
                'management_fee' => $managementFee,
                'net_to_owner' => $netToOwner,
                'commission_percent' => $commissionPct,
                'last_paid_at' => $lastPaidByProperty[$pid] ?? null,
                'units_total' => (int) ($unitStats['units_total'] ?? 0),
                'units_occupied' => (int) ($unitStats['units_occupied'] ?? 0),
                'units_vacant' => (int) ($unitStats['units_vacant'] ?? 0),
                'active_tenants' => (int) ($tenantCountByProperty[$pid] ?? 0),
            ];
        })->values();

        $recentCollections = collect();
        if ($propertyIds !== []) {
            $recentCollections = DB::table('pm_payment_allocations as a')
                ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
                ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                ->join('pm_tenants as t', 't.id', '=', 'pay.pm_tenant_id')
                ->whereIn('pu.property_id', $propertyIds)
                ->where('pay.status', PmPayment::STATUS_COMPLETED)
                ->whereBetween('pay.paid_at', [$periodStart, $periodEnd])
                ->orderByDesc('pay.paid_at')
                ->limit(25)
                ->select([
                    'pay.paid_at',
                    'pay.amount',
                    'pay.channel',
                    'pay.external_ref',
                    't.name as tenant_name',
                    'pu.label as unit_label',
                    'p.name as property_name',
                ])
                ->join('properties as p', 'p.id', '=', 'pu.property_id')
                ->get();
        }

        $totals = [
            'properties' => (int) $propertyBreakdown->count(),
            'owner_share' => (float) $propertyBreakdown->sum('owner_share'),
            'pending_share' => (float) $propertyBreakdown->sum('pending_share'),
            'management_fees' => (float) $propertyBreakdown->sum('management_fee'),
            'net_to_owner' => (float) $propertyBreakdown->sum('net_to_owner'),
            'units_total' => (int) $propertyBreakdown->sum('units_total'),
            'units_occupied' => (int) $propertyBreakdown->sum('units_occupied'),
            'active_tenants' => (int) $propertyBreakdown->sum('active_tenants'),
        ];

        return [
            'periodLabel' => $periodLabel,
            'monthValue' => $monthValue,
            'fyValue' => $fy,
            'commissionPct' => $this->displayCommissionPercent(
                $propertyBreakdown,
                (float) $totals['owner_share'],
                (float) $totals['management_fees'],
                $commissionDefaultPct
            ),
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'totals' => $totals,
            'propertyBreakdown' => $propertyBreakdown,
            'recentCollections' => $recentCollections,
        ];
    }

    /**
     * @return array{stats: list<array{label: string, value: string, hint?: string}>, columns: list<string>, tableRows: list<list<string>>}
     */
    public function rentRoll(User $landlord): array
    {
        $unitIds = LandlordPortalAccess::unitIds($landlord)->all();
        if ($unitIds === []) {
            return [
                'stats' => [],
                'columns' => ['Property / Unit', 'Tenant', 'Monthly rent', 'Arrears', 'Lease end', 'Status'],
                'tableRows' => [],
            ];
        }

        $leases = PmLease::query()
            ->with(['pmTenant', 'units.property'])
            ->where('status', PmLease::STATUS_ACTIVE)
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->orderBy('end_date')
            ->get();

        $formulas = app(FinancialReportingFormulaService::class);
        $leaseIds = $leases->pluck('id')->all();
        $arrearsMap = $formulas->leaseArrearsMap($leaseIds, now()->startOfMonth());
        $balanceMap = $formulas->leaseOutstandingMap($leaseIds);

        $rows = $leases->map(function (PmLease $lease) use ($arrearsMap, $balanceMap) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.($u->label ?? '—'))->implode(', ');

            return [
                $units !== '' ? $units : '—',
                (string) ($lease->pmTenant?->name ?? '—'),
                PropertyMoney::kes((float) ($lease->monthly_rent ?? 0)),
                PropertyMoney::kes((float) ($arrearsMap[$lease->id] ?? 0)),
                $lease->end_date?->format('Y-m-d') ?? '—',
                PropertyMoney::kes((float) ($balanceMap[$lease->id] ?? 0)),
            ];
        })->all();

        return [
            'stats' => [
                ['label' => 'Active leases', 'value' => (string) count($rows)],
                ['label' => 'Monthly rent', 'value' => PropertyMoney::kes((float) $leases->sum('monthly_rent'))],
                ['label' => 'Total arrears', 'value' => PropertyMoney::kes((float) collect($arrearsMap)->sum())],
            ],
            'columns' => ['Property / Unit', 'Tenant', 'Monthly rent', 'Arrears (MTD)', 'Lease end', 'Outstanding'],
            'tableRows' => $rows,
        ];
    }

    /**
     * @return array{stats: list<array{label: string, value: string}>, columns: list<string>, tableRows: list<list<string>>}
     */
    public function arrearsAging(User $landlord): array
    {
        $unitIds = LandlordPortalAccess::unitIds($landlord)->all();
        if ($unitIds === []) {
            return [
                'stats' => [],
                'columns' => ['Invoice', 'Property / Unit', 'Due date', 'Days overdue', 'Outstanding'],
                'tableRows' => [],
            ];
        }

        $invoices = PmInvoice::query()
            ->with(['unit.property'])
            ->whereIn('property_unit_id', $unitIds)
            ->whereRaw('(amount - amount_paid) > 0.009')
            ->orderBy('due_date')
            ->limit(500)
            ->get();

        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];

        $rows = $invoices->map(function (PmInvoice $inv) use (&$buckets) {
            $outstanding = max(0.0, (float) $inv->amount - (float) $inv->amount_paid);
            $due = $inv->due_date ? Carbon::parse($inv->due_date)->startOfDay() : now()->startOfDay();
            $days = now()->startOfDay()->diffInDays($due, false);
            $daysOverdue = $days < 0 ? abs($days) : 0;

            if ($daysOverdue === 0) {
                $buckets['current'] += $outstanding;
            } elseif ($daysOverdue <= 30) {
                $buckets['1_30'] += $outstanding;
            } elseif ($daysOverdue <= 60) {
                $buckets['31_60'] += $outstanding;
            } elseif ($daysOverdue <= 90) {
                $buckets['61_90'] += $outstanding;
            } else {
                $buckets['90_plus'] += $outstanding;
            }

            return [
                $inv->invoice_no,
                ($inv->unit?->property?->name ?? '—').' / '.($inv->unit?->label ?? '—'),
                $inv->due_date?->format('Y-m-d') ?? '—',
                $daysOverdue > 0 ? (string) $daysOverdue : '—',
                PropertyMoney::kes($outstanding),
            ];
        })->all();

        return [
            'stats' => [
                ['label' => 'Current', 'value' => PropertyMoney::kes($buckets['current'])],
                ['label' => '1–30 days', 'value' => PropertyMoney::kes($buckets['1_30'])],
                ['label' => '31–60 days', 'value' => PropertyMoney::kes($buckets['31_60'])],
                ['label' => '61–90 days', 'value' => PropertyMoney::kes($buckets['61_90'])],
                ['label' => '90+ days', 'value' => PropertyMoney::kes($buckets['90_plus'])],
            ],
            'columns' => ['Invoice', 'Property / Unit', 'Due date', 'Days overdue', 'Outstanding'],
            'tableRows' => $rows,
        ];
    }

    /**
     * @return array{stats: list<array{label: string, value: string}>, columns: list<string>, tableRows: list<list<string>>}
     */
    public function leasesIndex(User $landlord): array
    {
        $unitIds = LandlordPortalAccess::unitIds($landlord)->all();
        if ($unitIds === []) {
            return [
                'stats' => [],
                'columns' => ['Property / Unit', 'Tenant', 'Start', 'End', 'Rent', 'Status'],
                'tableRows' => [],
            ];
        }

        $leases = PmLease::query()
            ->with(['pmTenant', 'units.property'])
            ->whereHas('units', fn ($q) => $q->whereIn('property_units.id', $unitIds))
            ->orderByDesc('start_date')
            ->limit(300)
            ->get();

        $rows = $leases->map(function (PmLease $lease) {
            $units = $lease->units->map(fn ($u) => ($u->property->name ?? '—').' / '.($u->label ?? '—'))->implode(', ');

            return [
                $units !== '' ? $units : '—',
                (string) ($lease->pmTenant?->name ?? '—'),
                $lease->start_date?->format('Y-m-d') ?? '—',
                $lease->end_date?->format('Y-m-d') ?? '—',
                PropertyMoney::kes((float) ($lease->monthly_rent ?? 0)),
                ucfirst((string) $lease->status),
            ];
        })->all();

        return [
            'stats' => [
                ['label' => 'Leases', 'value' => (string) count($rows)],
                ['label' => 'Active', 'value' => (string) $leases->where('status', PmLease::STATUS_ACTIVE)->count()],
            ],
            'columns' => ['Property / Unit', 'Tenant', 'Start', 'End', 'Rent', 'Status'],
            'tableRows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vacantUnits(User $landlord): array
    {
        $propertyIds = LandlordPortalAccess::propertyIds($landlord);
        if ($propertyIds->isEmpty()) {
            return [];
        }

        return PropertyUnit::query()
            ->with('property')
            ->whereIn('property_id', $propertyIds)
            ->whereIn('status', [PropertyUnit::STATUS_VACANT, PropertyUnit::STATUS_NOTICE])
            ->orderBy('property_id')
            ->orderBy('label')
            ->get()
            ->map(fn (PropertyUnit $u) => [
                'property' => $u->property?->name ?? '—',
                'unit' => $u->label,
                'status' => ucfirst((string) $u->status),
                'rent' => PropertyMoney::kes((float) ($u->rent_amount ?? 0)),
                'property_id' => (int) $u->property_id,
            ])
            ->all();
    }

    public function ledgerDocumentLink(PmLandlordLedgerEntry $entry): ?array
    {
        $type = (string) ($entry->reference_type ?? '');
        $id = (int) ($entry->reference_id ?? 0);
        if ($type === '' || $id <= 0) {
            return null;
        }

        return match ($type) {
            'pm_invoice' => ['label' => 'Invoice', 'route' => 'property.landlord.documents.invoice', 'params' => ['invoice' => $id]],
            'pm_landlord_remittance_request' => ['label' => 'Remittance', 'route' => 'property.landlord.earnings.remittances', 'params' => []],
            default => null,
        };
    }

    private function defaultCommissionPercent(): float
    {
        $raw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));

        return max(0.0, is_numeric($raw) ? (float) $raw : 10.0);
    }

    /** @return array<int, float> */
    private function commissionOverrides(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $decoded = json_decode($raw, true);
        $out = [];
        if (! is_array($decoded)) {
            return $out;
        }
        foreach ($decoded as $propertyId => $pct) {
            $pid = (int) $propertyId;
            if ($pid > 0 && is_numeric($pct)) {
                $out[$pid] = max(0.0, (float) $pct);
            }
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $propertyBreakdown
     */
    private function displayCommissionPercent($propertyBreakdown, float $ownerShare, float $agentEarning, float $defaultPct): float
    {
        if ($ownerShare > 0) {
            return round(max(0.0, ($agentEarning / $ownerShare) * 100), 2);
        }

        $rates = $propertyBreakdown
            ->pluck('commission_percent')
            ->filter(static fn ($rate) => is_numeric($rate))
            ->map(static fn ($rate) => round((float) $rate, 2))
            ->unique()
            ->values();

        if ($rates->count() === 1) {
            return (float) $rates->first();
        }

        return max(0.0, $defaultPct);
    }
}
