<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPaymentAllocation;
use App\Models\PmUnitUtilityCharge;
use App\Models\PmWaterReading;
use Illuminate\Support\Facades\DB;

class UtilityBillingReportService
{
    /**
     * @return array{stats: array<int, array{label: string, value: string, hint: string}>, columns: array<int, string>, tableRows: array<int, array<int, string>>}
     */
    public function billingSummary(?string $monthFrom = null, ?string $monthTo = null): array
    {
        $readings = PmWaterReading::query()
            ->with('unit.property')
            ->when($monthFrom, fn ($q) => $q->where('billing_month', '>=', $monthFrom))
            ->when($monthTo, fn ($q) => $q->where('billing_month', '<=', $monthTo))
            ->orderByDesc('billing_month')
            ->limit(300)
            ->get();

        $invoiced = $readings->where('status', 'invoiced')->count();
        $uninvoiced = $readings->whereNull('pm_invoice_id')->count();
        $billedTotal = (float) $readings->sum('amount');

        $collected = (float) PmPaymentAllocation::query()
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', PmInvoice::TYPE_WATER))
            ->when($monthFrom, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('billing_period', '>=', $monthFrom)))
            ->when($monthTo, fn ($q) => $q->whereHas('invoice', fn ($iq) => $iq->where('billing_period', '<=', $monthTo)))
            ->sum('amount');

        $recoveryRatio = $billedTotal > 0 ? round(($collected / $billedTotal) * 100, 1) : 0.0;

        return [
            'stats' => [
                ['label' => 'Readings', 'value' => (string) $readings->count(), 'hint' => 'Captured'],
                ['label' => 'Invoiced', 'value' => (string) $invoiced, 'hint' => 'Converted'],
                ['label' => 'Uninvoiced', 'value' => (string) $uninvoiced, 'hint' => 'Pending'],
                ['label' => 'Recovery', 'value' => $recoveryRatio.'%', 'hint' => 'Collected / billed'],
            ],
            'columns' => ['Month', 'Property / Unit', 'Usage', 'Amount', 'Status', 'Invoice'],
            'tableRows' => $readings->map(fn (PmWaterReading $r) => [
                (string) $r->billing_month,
                trim(($r->unit?->property?->name ?? '—').' / '.($r->unit?->label ?? '—')),
                number_format((float) $r->units_used, 3),
                PropertyMoney::kes((float) $r->amount),
                ucfirst((string) $r->status),
                $r->pm_invoice_id ? '#'.$r->pm_invoice_id : '—',
            ])->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function waterArrearsAging(): array
    {
        return app(UtilityReconciliationService::class)->agingSummary()['rows'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function consumptionTrends(int $months = 6): array
    {
        $from = now()->subMonths($months - 1)->format('Y-m');

        return PmWaterReading::query()
            ->select([
                'billing_month',
                DB::raw('SUM(units_used) as total_units'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as reading_count'),
            ])
            ->where('billing_month', '>=', $from)
            ->groupBy('billing_month')
            ->orderBy('billing_month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->billing_month,
                'total_units' => round((float) $row->total_units, 3),
                'total_amount' => round((float) $row->total_amount, 2),
                'readings' => (int) $row->reading_count,
            ])
            ->all();
    }

    /**
     * Legacy utility charge lines report (non-meter charges).
     */
    public function manualChargeLines(?string $monthFrom = null, ?string $monthTo = null): array
    {
        $query = PmUnitUtilityCharge::query()->with('unit.property');
        if ($monthFrom) {
            $query->where('billing_month', '>=', $monthFrom);
        }
        if ($monthTo) {
            $query->where('billing_month', '<=', $monthTo);
        }
        $charges = $query->latest('id')->limit(250)->get();

        return [
            'stats' => [
                ['label' => 'Charge lines', 'value' => (string) $charges->count(), 'hint' => 'Manual'],
                ['label' => 'Amount', 'value' => PropertyMoney::kes((float) $charges->sum('amount')), 'hint' => 'Billed'],
                ['label' => 'Invoiced', 'value' => (string) $charges->where('is_invoiced', true)->count(), 'hint' => 'Converted'],
            ],
            'columns' => ['Month', 'Type', 'Property / Unit', 'Label', 'Amount', 'Invoiced'],
            'tableRows' => $charges->map(fn (PmUnitUtilityCharge $charge) => [
                (string) ($charge->billing_month ?? '—'),
                ucfirst((string) ($charge->charge_type ?? '—')),
                trim(($charge->unit?->property?->name ?? '—').' / '.($charge->unit?->label ?? '—')),
                (string) ($charge->label ?? '—'),
                PropertyMoney::kes((float) ($charge->amount ?? 0)),
                $charge->is_invoiced ? 'Yes' : 'No',
            ])->all(),
        ];
    }
}
