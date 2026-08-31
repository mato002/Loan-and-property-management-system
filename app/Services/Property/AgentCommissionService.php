<?php

namespace App\Services\Property;

use App\Models\Concerns\AgentWorkspaceScope;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PropertyPortalSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AgentCommissionService
{
    public function defaultPercent(): float
    {
        $defaultRaw = trim((string) PropertyPortalSetting::getValue('commission_default_percent', '10'));

        return is_numeric($defaultRaw) ? max(0.0, (float) $defaultRaw) : 10.0;
    }

    /**
     * @return array<int, float> property_id => percent
     */
    public function propertyOverrides(): array
    {
        $raw = (string) PropertyPortalSetting::getValue('commission_property_overrides_json', '[]');
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $propertyId => $pct) {
            $pid = (int) $propertyId;
            if ($pid <= 0 || ! is_numeric($pct)) {
                continue;
            }
            $out[$pid] = max(0.0, (float) $pct);
        }

        return $out;
    }

    public function commissionPercentForProperty(int $propertyId): float
    {
        return $this->propertyOverrides()[$propertyId] ?? $this->defaultPercent();
    }

    /**
     * @return Collection<int, object>
     */
    public function landlordPropertyLinks(?int $landlordId = null, ?int $propertyId = null, ?string $search = null): Collection
    {
        $query = DB::table('property_landlord as pl')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->select([
                'pl.user_id',
                'pl.property_id',
                'pl.ownership_percent',
                'u.name as owner_name',
                'p.name as property_name',
            ])
            ->orderBy('u.name')
            ->orderBy('p.name');

        if (AgentWorkspaceScope::shouldApply()) {
            $query->where('p.agent_user_id', (int) auth()->id());
        }
        if ($landlordId !== null && $landlordId > 0) {
            $query->where('pl.user_id', $landlordId);
        }
        if ($propertyId !== null && $propertyId > 0) {
            $query->where('pl.property_id', $propertyId);
        }

        $links = $query->get();

        if ($search !== null && trim($search) !== '') {
            $needle = mb_strtolower(trim($search));
            $links = $links->filter(function ($link) use ($needle) {
                $hay = mb_strtolower((string) ($link->owner_name.' '.$link->property_name));

                return str_contains($hay, $needle);
            })->values();
        }

        return $links;
    }

    /**
     * @return array<int, float> property_id => collected
     */
    public function collectedByProperty(Carbon $start, Carbon $end): array
    {
        $fromAllocations = $this->collectedByPropertyViaAllocations($start, $end);
        if (array_sum($fromAllocations) > 0) {
            return $fromAllocations;
        }

        return $this->collectedByPropertyViaLeases($start, $end);
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function collectionsChartByProperty(Carbon $start, Carbon $end, int $limit = 5): array
    {
        $collected = $this->collectedByProperty($start, $end);
        arsort($collected);
        $top = array_slice($collected, 0, $limit, true);
        $names = DB::table('properties')->whereIn('id', array_keys($top))->pluck('name', 'id');

        $labels = [];
        $values = [];
        foreach ($top as $pid => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $labels[] = (string) ($names[$pid] ?? 'Property '.$pid);
            $values[] = round($amount, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array<int, float> property_id => collected
     */
    private function collectedByPropertyViaAllocations(Carbon $start, Carbon $end): array
    {
        return DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
            ->pluck('total', 'property_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Fallback when payments are not allocated to invoices yet.
     *
     * @return array<int, float> property_id => collected
     */
    private function collectedByPropertyViaLeases(Carbon $start, Carbon $end): array
    {
        return DB::table('pm_payments as pay')
            ->join('pm_leases as l', function ($join) {
                $join->on('l.pm_tenant_id', '=', 'pay.pm_tenant_id')
                    ->where('l.status', '=', 'active');
            })
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(pay.amount),0) as total')
            ->pluck('total', 'property_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<int, float> property_id => invoiced
     */
    public function invoicedByProperty(Carbon $start, Carbon $end): array
    {
        return DB::table('pm_invoices as i')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->whereBetween('i.issue_date', [$start->toDateString(), $end->toDateString()])
            ->tap(fn ($q) => PmInvoice::applyLiveBalanceConstraints($q, 'i'))
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(i.amount),0) as total')
            ->pluck('total', 'property_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     totals: array{collected: float, landlord_share: float, commission: float, landlord_net: float, invoiced: float}
     * }
     */
    public function aggregate(
        Carbon $start,
        Carbon $end,
        ?int $landlordId = null,
        ?int $propertyId = null,
        ?string $search = null,
    ): array {
        $defaultPct = $this->defaultPercent();
        $overrides = $this->propertyOverrides();
        $links = $this->landlordPropertyLinks($landlordId, $propertyId, $search);
        $collectedByProperty = $this->collectedByProperty($start, $end);
        $invoicedByProperty = $this->invoicedByProperty($start, $end);

        $totals = [
            'collected' => 0.0,
            'landlord_share' => 0.0,
            'commission' => 0.0,
            'landlord_net' => 0.0,
            'invoiced' => 0.0,
        ];

        $rows = $links->map(function ($link) use ($defaultPct, $overrides, $collectedByProperty, $invoicedByProperty, &$totals) {
            $ownership = ((float) $link->ownership_percent) / 100;
            $propertyCollected = (float) ($collectedByProperty[$link->property_id] ?? 0);
            $propertyInvoiced = (float) ($invoicedByProperty[$link->property_id] ?? 0);
            $ownerShare = $propertyCollected * $ownership;
            $ownerInvoiced = $propertyInvoiced * $ownership;
            $ratePct = $overrides[(int) $link->property_id] ?? $defaultPct;
            $commission = $ownerShare * ($ratePct / 100);
            $landlordNet = max(0.0, $ownerShare - $commission);

            $totals['collected'] += $propertyCollected;
            $totals['landlord_share'] += $ownerShare;
            $totals['commission'] += $commission;
            $totals['landlord_net'] += $landlordNet;
            $totals['invoiced'] += $ownerInvoiced;

            return [
                'landlord_id' => (int) $link->user_id,
                'property_id' => (int) $link->property_id,
                'owner_name' => (string) $link->owner_name,
                'property_name' => (string) $link->property_name,
                'ownership_percent' => (float) $link->ownership_percent,
                'collected' => $propertyCollected,
                'owner_share' => $ownerShare,
                'owner_invoiced' => $ownerInvoiced,
                'rate_pct' => $ratePct,
                'commission' => $commission,
                'landlord_net' => $landlordNet,
            ];
        })->values()->all();

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return array{labels: list<string>, values: list<float>}
     */
    public function chartByProperty(
        Carbon $start,
        Carbon $end,
        int $limit = 5,
        ?int $landlordId = null,
        ?int $propertyId = null,
        ?string $search = null,
    ): array {
        $defaultPct = $this->defaultPercent();
        $overrides = $this->propertyOverrides();
        $collectedByProperty = $this->collectedByProperty($start, $end);

        $byProperty = [];
        foreach ($this->landlordPropertyLinks($landlordId, $propertyId, $search) as $link) {
            $pid = (int) $link->property_id;
            $ownership = ((float) $link->ownership_percent) / 100;
            $ownerShare = ((float) ($collectedByProperty[$pid] ?? 0)) * $ownership;
            $ratePct = $overrides[$pid] ?? $defaultPct;
            $commission = $ownerShare * ($ratePct / 100);
            $byProperty[$pid] = ($byProperty[$pid] ?? 0) + $commission;
        }

        arsort($byProperty);
        $top = array_slice($byProperty, 0, $limit, true);
        $names = DB::table('properties')->whereIn('id', array_keys($top))->pluck('name', 'id');

        $labels = [];
        $values = [];
        foreach ($top as $pid => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $labels[] = (string) ($names[$pid] ?? 'Property '.$pid);
            $values[] = round($amount, 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, commission: float, landlord_net: float}
     */
    public function chartSplit(
        Carbon $start,
        Carbon $end,
        ?int $landlordId = null,
        ?int $propertyId = null,
        ?string $search = null,
    ): array {
        $agg = $this->aggregate($start, $end, $landlordId, $propertyId, $search);

        return [
            'labels' => ['Your commission', 'Landlord net'],
            'values' => [
                round($agg['totals']['commission'], 2),
                round($agg['totals']['landlord_net'], 2),
            ],
            'commission' => $agg['totals']['commission'],
            'landlord_net' => $agg['totals']['landlord_net'],
        ];
    }
}
