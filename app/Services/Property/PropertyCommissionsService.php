<?php

namespace App\Services\Property;

use App\Models\PmLandlordPayoutItem;
use App\Models\PropertyPortalSetting;
use App\Support\Property\WorkspaceRowAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PropertyCommissionsService
{
    public function __construct(
        private readonly AgentCommissionService $commission,
    ) {}

    /**
     * EZEN-style property commission register: one row per property × landlord × period.
     *
     * @param  array{year?: int, month?: int|string, property_id?: int, landlord_id?: int, city?: string, on?: string, search?: string, show_zero?: bool}  $filters
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     stats: list<array{label: string, value: string, hint?: string}>,
     *     period_label: string,
     *     year: int,
     *     month: int,
     *     vat_percent: float
     * }
     */
    public function buildRegister(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = (int) now()->year;
        }

        $monthRaw = $filters['month'] ?? now()->month;
        $month = is_numeric($monthRaw) ? (int) $monthRaw : 0;
        if ($month < 0 || $month > 12) {
            $month = (int) now()->month;
        }

        $propertyId = (int) ($filters['property_id'] ?? 0);
        $landlordId = (int) ($filters['landlord_id'] ?? 0);
        $city = trim((string) ($filters['city'] ?? ''));
        $on = strtolower(trim((string) ($filters['on'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));
        $showZero = (bool) ($filters['show_zero'] ?? false);
        $vatPercent = $this->vatPercent();

        $links = $this->commission->landlordPropertyLinks(
            $landlordId > 0 ? $landlordId : null,
            $propertyId > 0 ? $propertyId : null,
            $search !== '' ? $search : null,
        );

        $propertyIds = $links->pluck('property_id')->unique()->values()->all();
        $propertyMeta = $propertyIds === []
            ? collect()
            : DB::table('properties')->whereIn('id', $propertyIds)->get(['id', 'code', 'city', 'name'])->keyBy('id');

        if ($city !== '') {
            $needle = mb_strtolower($city);
            $links = $links->filter(function ($link) use ($propertyMeta, $needle) {
                $rowCity = mb_strtolower(trim((string) ($propertyMeta[(int) $link->property_id]->city ?? '')));

                return $rowCity !== '' && str_contains($rowCity, $needle);
            })->values();
            $propertyIds = $links->pluck('property_id')->unique()->values()->all();
        }

        $periods = $this->periods($year, $month);
        $periodMonths = array_map(static fn (Carbon $start) => $start->format('Y-m'), $periods);
        $payouts = $this->payoutsByPeriod($propertyIds, $periodMonths);

        $rows = [];
        foreach ($periods as $periodStart) {
            $periodEnd = $periodStart->copy()->endOfMonth();
            $periodMonth = $periodStart->format('Y-m');
            $collectedByProperty = $links->isEmpty()
                ? []
                : $this->commission->collectedByProperty($periodStart, $periodEnd);

            foreach ($links as $link) {
                $pid = (int) $link->property_id;
                $lid = (int) $link->user_id;
                $ownershipFactor = ((float) $link->ownership_percent) / 100;
                $collected = round(((float) ($collectedByProperty[$pid] ?? 0)) * $ownershipFactor, 2);
                $ratePct = $this->commission->commissionPercentForProperty($pid);
                $commissionAmt = round($collected * ($ratePct / 100), 2);
                $vatAmt = round($commissionAmt * ($vatPercent / 100), 2);
                $total = round($commissionAmt + $vatAmt, 2);

                if ($on === 'rent' && $collected <= 0) {
                    continue;
                }

                $payoutItem = $payouts[$pid.'|'.$lid.'|'.$periodMonth] ?? null;
                $payout = $payoutItem?->payout;
                $hasActivity = $collected > 0.009 || $commissionAmt > 0.009 || $payout !== null;

                if (! $showZero && ! $hasActivity) {
                    continue;
                }

                $status = $this->rowStatus($commissionAmt, $payout?->status);
                $meta = $propertyMeta[$pid] ?? null;

                $rows[] = [
                    'property_id' => $pid,
                    'landlord_id' => $lid,
                    'property_code' => trim((string) ($meta->code ?? '')),
                    'property_name' => (string) ($meta->name ?? $link->property_name),
                    'city' => trim((string) ($meta->city ?? '')),
                    'landlord_name' => (string) $link->owner_name,
                    'on' => $collected > 0.009 ? 'RENT' : '—',
                    'date_prepared' => $payout?->created_at?->format('d/m/Y') ?? $periodEnd->format('d/m/Y'),
                    'period' => $periodStart->format('F/Y'),
                    'period_month' => $periodMonth,
                    'collected' => $collected,
                    'rate_pct' => $ratePct,
                    'commission_amount' => $commissionAmt,
                    'commission_vat' => $vatAmt,
                    'total_commission' => $total,
                    'invoice_no' => $payout ? 'PAY-'.$payout->id : '',
                    'invoice_date' => $payout?->created_at?->format('d/m/Y') ?? '',
                    'payout_id' => $payout?->id,
                    'payout_status' => $payout?->status,
                    'status' => $status,
                    'tone' => $this->toneForStatus($status),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $period = strcmp((string) $b['period_month'], (string) $a['period_month']);
            if ($period !== 0) {
                return $period;
            }

            return strcmp((string) $a['property_name'], (string) $b['property_name']);
        });

        $periodLabel = $month > 0
            ? Carbon::create($year, $month, 1)->format('F Y')
            : 'All months '.$year;

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($rows, $periodLabel, $vatPercent),
            'period_label' => $periodLabel,
            'year' => $year,
            'month' => $month,
            'vat_percent' => $vatPercent,
        ];
    }

    /**
     * @return list<string>
     */
    public function cities(): array
    {
        return DB::table('properties')
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->orderBy('city')
            ->distinct()
            ->pluck('city')
            ->map(static fn ($city) => trim((string) $city))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function vatPercent(): float
    {
        $raw = trim((string) PropertyPortalSetting::getValue('commission_vat_percent', '0'));

        return is_numeric($raw) ? max(0.0, (float) $raw) : 0.0;
    }

    /**
     * @return list<Carbon>
     */
    private function periods(int $year, int $month): array
    {
        if ($month >= 1 && $month <= 12) {
            return [Carbon::create($year, $month, 1)->startOfMonth()];
        }

        $out = [];
        for ($i = 1; $i <= 12; $i++) {
            $out[] = Carbon::create($year, $i, 1)->startOfMonth();
        }

        return $out;
    }

    /**
     * @param  list<int>  $propertyIds
     * @param  list<string>  $periodMonths
     * @return array<string, PmLandlordPayoutItem>
     */
    private function payoutsByPeriod(array $propertyIds, array $periodMonths): array
    {
        if ($propertyIds === [] || $periodMonths === []) {
            return [];
        }

        /** @var Collection<int, PmLandlordPayoutItem> $items */
        $items = PmLandlordPayoutItem::query()
            ->with('payout')
            ->whereIn('property_id', $propertyIds)
            ->whereIn('period_month', $periodMonths)
            ->orderByDesc('id')
            ->get();

        $map = [];
        foreach ($items as $item) {
            $key = ((int) $item->property_id).'|'.((int) $item->landlord_id).'|'.(string) $item->period_month;
            if (! isset($map[$key])) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    private function rowStatus(float $commissionAmt, ?string $payoutStatus): string
    {
        if ($payoutStatus === 'paid') {
            return 'posted';
        }
        if (in_array($payoutStatus, ['approved', 'draft'], true)) {
            return $payoutStatus;
        }
        if ($commissionAmt > 0.009) {
            return 'accrued';
        }

        return 'none';
    }

    private function toneForStatus(string $status): string
    {
        return match ($status) {
            'posted' => WorkspaceRowAlert::TONE_OCCUPIED,
            'accrued', 'draft' => WorkspaceRowAlert::TONE_VACANT,
            'approved' => WorkspaceRowAlert::TONE_NOTICE,
            default => '',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: string, hint?: string}>
     */
    private function buildStats(array $rows, string $periodLabel, float $vatPercent): array
    {
        $commission = (float) collect($rows)->sum('commission_amount');
        $vat = (float) collect($rows)->sum('commission_vat');
        $collected = (float) collect($rows)->sum('collected');
        $accrued = collect($rows)->where('status', 'accrued')->count();

        return [
            ['label' => 'Rows', 'value' => (string) count($rows), 'hint' => $periodLabel],
            ['label' => 'Rent collected', 'value' => PropertyMoney::kes($collected), 'hint' => 'Ownership-adjusted'],
            ['label' => 'Commission', 'value' => PropertyMoney::kes($commission), 'hint' => 'Agency management fee'],
            ['label' => 'VAT on commission', 'value' => PropertyMoney::kes($vat), 'hint' => number_format($vatPercent, 2).'%'],
            ['label' => 'Total commission', 'value' => PropertyMoney::kes($commission + $vat), 'hint' => $accrued.' still accrued'],
        ];
    }
}
