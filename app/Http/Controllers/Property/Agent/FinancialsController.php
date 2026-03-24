<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\PmUnitUtilityCharge;
use App\Models\PropertyPortalSetting;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinancialsController extends Controller
{
    public function incomeExpenses(Request $request): View
    {
        [$monthValue, $fyValue, $start, $end, $periodLabel] = $this->resolvePeriod($request);
0
        $income = (float) PmInvoice::query()
            ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');
        $maint = (float) PmMaintenanceJob::query()
            ->whereNotNull('quote_amount')
            ->whereBetween('created_at', [$start, $end])
            ->sum('quote_amount');
        $utilities = (float) PmUnitUtilityCharge::query()
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
        $noi = max(0.0, $income - $maint - $utilities);

        $barSeries = PropertyChartSeries::incomeExpenseWaterfall($income, $maint, $utilities);

        $rows = [
            ['Rental income (billed)', 'Revenue', '—', PropertyMoney::kes($income), '—', 'Sum of invoices'],
            ['Maintenance (quoted jobs)', 'Opex', '—', PropertyMoney::kes($maint), '—', 'Job quotes with amounts'],
            ['Utility charges', 'Opex', '—', PropertyMoney::kes($utilities), '—', 'Recurring charges'],
            ['NOI proxy', 'Derived', '—', PropertyMoney::kes($noi), '—', 'Income − opex (simplified)'],
        ];

        return view('property.agent.financials.income_expenses', [
            'stats' => [
                ['label' => 'Billed', 'value' => PropertyMoney::kes($income), 'hint' => $periodLabel],
                ['label' => 'Maint. booked', 'value' => PropertyMoney::kes($maint), 'hint' => 'Job quotes'],
                ['label' => 'Utilities', 'value' => PropertyMoney::kes($utilities), 'hint' => 'Charges'],
                ['label' => 'NOI proxy', 'value' => PropertyMoney::kes($noi), 'hint' => 'Rough'],
                ['label' => 'Margin', 'value' => $income > 0 ? round(100 * $noi / $income, 1).'%' : '—', 'hint' => ''],
            ],
            'columns' => ['Account', 'Class', 'Budget', 'Actual', 'Variance', 'Notes'],
            'tableRows' => $rows,
            'waterfallBars' => $barSeries,
            'monthValue' => $monthValue,
            'fyValue' => $fyValue,
            'periodLabel' => $periodLabel,
        ]);
    }

    public function cashFlow(Request $request): View
    {
        [$monthValue, $fyValue, $start, $end, $periodLabel] = $this->resolvePeriod($request);

        $inAll = (float) PmPayment::query()->where('status', PmPayment::STATUS_COMPLETED)->sum('amount');
        $outAll = (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->sum('quote_amount');

        $inMtd = (float) PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');
        $outMtd = (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->sum('quote_amount');

        $payments = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$start, $end])
            ->with(['tenant', 'allocations.invoice.unit.property'])
            ->orderByDesc('paid_at')
            ->limit(120)
            ->get();

        $chron = $payments->sortBy(fn (PmPayment $p) => $p->paid_at?->timestamp ?? 0);
        $running = 0.0;
        $balanceAfter = [];
        foreach ($chron as $p) {
            $running += (float) $p->amount;
            $balanceAfter[$p->id] = $running;
        }

        $rows = [];
        foreach ($payments as $p) {
            $inv = $p->allocations->first()?->invoice;
            $prop = $inv?->unit?->property?->name ?? '—';
            $rows[] = [
                $p->paid_at?->format('Y-m-d') ?? '—',
                'Collection',
                $p->tenant?->name ?? 'Tenant',
                $prop,
                PropertyMoney::kes((float) $p->amount),
                '—',
                PropertyMoney::kes($balanceAfter[$p->id] ?? (float) $p->amount),
            ];
        }

        $monthly = PropertyChartSeries::agentCashFlowMonthly(6);
        $dual = [];
        foreach ($monthly as $m) {
            $dual[] = ['label' => $m['label'], 'a' => $m['in'], 'b' => $m['out']];
        }

        return view('property.agent.financials.cash_flow', [
            'stats' => [
                ['label' => 'In (period)', 'value' => PropertyMoney::kes($inMtd), 'hint' => $periodLabel],
                ['label' => 'Out (period)', 'value' => PropertyMoney::kes($outMtd), 'hint' => $periodLabel],
                ['label' => 'Net (period)', 'value' => PropertyMoney::kes($inMtd - $outMtd), 'hint' => ''],
                ['label' => 'In (all)', 'value' => PropertyMoney::kes($inAll), 'hint' => ''],
                ['label' => 'Out (all)', 'value' => PropertyMoney::kes($outAll), 'hint' => ''],
                ['label' => 'Net (all)', 'value' => PropertyMoney::kes($inAll - $outAll), 'hint' => ''],
            ],
            'columns' => ['Date', 'Type', 'Description', 'Property', 'In', 'Out', 'Balance'],
            'tableRows' => $rows,
            'cashDual' => $dual,
            'monthValue' => $monthValue,
            'fyValue' => $fyValue,
            'periodLabel' => $periodLabel,
        ]);
    }

    public function ownerBalances(Request $request): View
    {
        [$monthValue, $fyValue, $start, $end, $periodLabel] = $this->resolvePeriod($request);

        $links = DB::table('property_landlord as pl')
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
            ->orderBy('p.name')
            ->get();

        $collectedByProperty = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
            ->pluck('total', 'property_id');

        $pendingByProperty = DB::table('pm_invoices as i')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->whereDate('i.issue_date', '<=', $end->toDateString())
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(GREATEST(i.amount - i.amount_paid, 0)),0) as total')
            ->pluck('total', 'property_id');

        $lastRemitByProperty = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$start, $end])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, MAX(pay.paid_at) as last_paid_at')
            ->pluck('last_paid_at', 'property_id');

        $rows = $links->map(function ($link) use ($collectedByProperty, $pendingByProperty, $lastRemitByProperty) {
            $pct = ((float) $link->ownership_percent) / 100;
            $available = ((float) ($collectedByProperty[$link->property_id] ?? 0)) * $pct;
            $pending = ((float) ($pendingByProperty[$link->property_id] ?? 0)) * $pct;
            $last = $lastRemitByProperty[$link->property_id] ?? null;

            return [
                $link->owner_name,
                $link->property_name,
                PropertyMoney::kes($available),
                PropertyMoney::kes($pending),
                $last ? (string) \Illuminate\Support\Carbon::parse((string) $last)->format('Y-m-d') : '—',
                now()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
                'Auto-statement',
            ];
        })->all();

        $held = 0.0;
        $pending = 0.0;
        foreach ($links as $link) {
            $pct = ((float) $link->ownership_percent) / 100;
            $held += ((float) ($collectedByProperty[$link->property_id] ?? 0)) * $pct;
            $pending += ((float) ($pendingByProperty[$link->property_id] ?? 0)) * $pct;
        }

        return view('property.agent.financials.owner_balances', [
            'stats' => [
                ['label' => 'Held in trust', 'value' => PropertyMoney::kes($held), 'hint' => 'All owners'],
                ['label' => 'Pending remit', 'value' => PropertyMoney::kes($pending), 'hint' => 'Based on receivables'],
                ['label' => 'Owners', 'value' => (string) $links->pluck('user_id')->unique()->count(), 'hint' => 'Linked'],
            ],
            'columns' => ['Owner', 'Property', 'Available', 'Pending', 'Last remittance', 'Next run', 'Statement'],
            'tableRows' => $rows,
            'monthValue' => $monthValue,
            'fyValue' => $fyValue,
            'periodLabel' => $periodLabel,
        ]);
    }

    public function commission(Request $request): View
    {
        [$monthValue, $fyValue, $monthStart, $monthEnd, $periodLabel] = $this->resolvePeriod($request);

        $defaultPct = (float) PropertyPortalSetting::getValue('commission_default_percent', '10');
        if ($defaultPct < 0) {
            $defaultPct = 0;
        }

        $links = DB::table('property_landlord as pl')
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
            ->orderBy('p.name')
            ->get();

        $collectedMtdByProperty = DB::table('pm_payment_allocations as a')
            ->join('pm_payments as pay', 'pay.id', '=', 'a.pm_payment_id')
            ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->where('pay.status', PmPayment::STATUS_COMPLETED)
            ->whereBetween('pay.paid_at', [$monthStart, $monthEnd])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(a.amount),0) as total')
            ->pluck('total', 'property_id');

        $invoicedMtdByProperty = DB::table('pm_invoices as i')
            ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
            ->whereBetween('i.issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('pu.property_id')
            ->selectRaw('pu.property_id as property_id, COALESCE(SUM(i.amount),0) as total')
            ->pluck('total', 'property_id');

        $rows = $links->map(function ($link) use ($defaultPct, $collectedMtdByProperty, $invoicedMtdByProperty) {
            $ownership = ((float) $link->ownership_percent) / 100;
            $baseRent = ((float) ($collectedMtdByProperty[$link->property_id] ?? 0)) * $ownership;
            $accrued = $baseRent * ($defaultPct / 100);

            return [
                $periodLabel,
                $link->owner_name,
                $link->property_name,
                PropertyMoney::kes($baseRent),
                number_format($defaultPct, 2).'%',
                PropertyMoney::kes($accrued),
                $accrued > 0 ? 'Accrued' : 'No activity',
                '—',
            ];
        })->all();

        $totalAccrued = 0.0;
        $totalInvoiced = 0.0;
        $totalPaid = 0.0;
        foreach ($links as $link) {
            $ownership = ((float) $link->ownership_percent) / 100;
            $baseCollected = ((float) ($collectedMtdByProperty[$link->property_id] ?? 0)) * $ownership;
            $baseInvoiced = ((float) ($invoicedMtdByProperty[$link->property_id] ?? 0)) * $ownership;
            $totalAccrued += $baseCollected * ($defaultPct / 100);
            $totalInvoiced += $baseInvoiced * ($defaultPct / 100);
            $totalPaid += $baseCollected * ($defaultPct / 100);
        }

        $openDelta = max(0.0, $totalInvoiced - $totalPaid);

        return view('property.agent.financials.commission', [
            'stats' => [
                ['label' => 'Accrued', 'value' => PropertyMoney::kes($totalAccrued), 'hint' => $periodLabel],
                ['label' => 'Invoiced', 'value' => PropertyMoney::kes($totalInvoiced), 'hint' => 'From invoice base'],
                ['label' => 'Paid', 'value' => PropertyMoney::kes($totalPaid), 'hint' => 'Collection-linked'],
                ['label' => 'Disputes', 'value' => $openDelta > 0 ? '1' : '0', 'hint' => 'Open fee delta'],
            ],
            'columns' => ['Period', 'Owner', 'Property', 'Base rent', 'Fee %', 'Accrued', 'Status', 'Actions'],
            'tableRows' => $rows,
            'monthValue' => $monthValue,
            'fyValue' => $fyValue,
            'periodLabel' => $periodLabel,
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $month = (string) $request->query('month', '');
        $fy = (int) $request->query('fy', now()->year);
        if ($fy < 2000 || $fy > 2100) {
            $fy = (int) now()->year;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $start = now()->setDate((int) substr($month, 0, 4), (int) substr($month, 5, 2), 1)->startOfDay();
            $end = $start->copy()->endOfMonth();

            return [$month, $fy, $start, $end, $start->format('M Y')];
        }

        $start = now()->setDate($fy, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();

        return ['', $fy, $start, $end, 'FY '.$fy];
    }
}
