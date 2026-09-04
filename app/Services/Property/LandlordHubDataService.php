<?php

namespace App\Services\Property;

use App\Models\PmLandlordLedgerEntry;
use App\Models\PmLease;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Support\Property\WorkspaceRowAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LandlordHubDataService
{
    public function __construct(
        private readonly AgentCommissionService $commission,
        private readonly LandlordPaymentFeesService $paymentFees,
    ) {}

    /**
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    public function forTab(string $tab, User $landlord, array $propertyIds, Carbon $periodStart, Carbon $periodEnd, string $periodMonth): array
    {
        return match ($tab) {
            'units' => ['unitRows' => $this->unitRows($propertyIds)],
            'commission' => $this->commissionTab($landlord->id, $periodStart, $periodEnd),
            'settlements' => $this->settlementsTab($landlord->id, $periodMonth),
            'ledger' => ['ledgerRows' => $this->ledgerRows($landlord->id, $propertyIds)],
            default => [],
        };
    }

    /**
     * @param  list<int>  $propertyIds
     * @return list<array<string, mixed>>
     */
    public function unitRows(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        $units = PropertyUnit::query()
            ->with(['property'])
            ->whereIn('property_id', $propertyIds)
            ->orderBy('property_id')
            ->orderBy('label')
            ->get();

        $activeLeases = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->join('pm_tenants as t', 't.id', '=', 'l.pm_tenant_id')
            ->whereIn('lu.property_unit_id', $units->pluck('id'))
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->select([
                'lu.property_unit_id',
                't.name as tenant_name',
                'l.monthly_rent',
                'l.end_date',
            ])
            ->get()
            ->keyBy('property_unit_id');

        return $units->map(function (PropertyUnit $unit) use ($activeLeases) {
            $lease = $activeLeases->get($unit->id);

            return [
                'property_id' => (int) $unit->property_id,
                'property_name' => (string) ($unit->property?->name ?? '—'),
                'unit_id' => (int) $unit->id,
                'unit_label' => (string) $unit->label,
                'status' => (string) $unit->status,
                'status_label' => PropertyUnit::statusOptions()[$unit->status] ?? ucfirst((string) $unit->status),
                'tenant_name' => $lease ? (string) $lease->tenant_name : '—',
                'monthly_rent' => $lease ? (float) $lease->monthly_rent : null,
                'lease_end' => $lease && $lease->end_date ? (string) $lease->end_date : null,
                'row_tone' => WorkspaceRowAlert::forSnapshot(
                    (string) $unit->status,
                    $lease !== null,
                ),
            ];
        })->values()->all();
    }

    /**
     * @return array{commissionRows: list<array<string, mixed>>, commissionTotals: array<string, float>}
     */
    public function commissionTab(int $landlordId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $aggregate = $this->commission->aggregate($periodStart, $periodEnd, $landlordId);

        return [
            'commissionRows' => $aggregate['rows'],
            'commissionTotals' => $aggregate['totals'],
        ];
    }

    /**
     * @return array{settlementRows: list<array<string, mixed>>}
     */
    public function settlementsTab(int $landlordId, string $periodMonth): array
    {
        $grid = $this->paymentFees->buildGrid([
            'landlord_id' => $landlordId,
            'month' => $periodMonth,
            'show_zero' => true,
        ]);

        return ['settlementRows' => $grid['rows']];
    }

    /**
     * @param  list<int>  $propertyIds
     * @return Collection<int, PmLandlordLedgerEntry>
     */
    public function ledgerRows(int $landlordId, array $propertyIds): Collection
    {
        if ($propertyIds === []) {
            return collect();
        }

        return PmLandlordLedgerEntry::query()
            ->with('property')
            ->where('user_id', $landlordId)
            ->whereIn('property_id', $propertyIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get();
    }
}
