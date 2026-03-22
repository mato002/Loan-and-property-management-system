<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Models\PmUnitUtilityCharge;
use App\Services\Property\PropertyChartSeries;
use App\Services\Property\PropertyMoney;
use Illuminate\View\View;

class FinancialsController extends Controller
{
    public function incomeExpenses(): View
    {
        $income = (float) PmInvoice::query()->sum('amount');
        $maint = (float) PmMaintenanceJob::query()->whereNotNull('quote_amount')->sum('quote_amount');
        $utilities = (float) PmUnitUtilityCharge::query()->sum('amount');
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
                ['label' => 'Billed (all)', 'value' => PropertyMoney::kes($income), 'hint' => 'Invoices'],
                ['label' => 'Maint. booked', 'value' => PropertyMoney::kes($maint), 'hint' => 'Job quotes'],
                ['label' => 'Utilities', 'value' => PropertyMoney::kes($utilities), 'hint' => 'Charges'],
                ['label' => 'NOI proxy', 'value' => PropertyMoney::kes($noi), 'hint' => 'Rough'],
                ['label' => 'Margin', 'value' => $income > 0 ? round(100 * $noi / $income, 1).'%' : '—', 'hint' => ''],
            ],
            'columns' => ['Account', 'Class', 'Budget', 'Actual', 'Variance', 'Notes'],
            'tableRows' => $rows,
            'waterfallBars' => $barSeries,
        ]);
    }

    public function cashFlow(): View
    {
        $inAll = (float) PmPayment::query()->where('status', PmPayment::STATUS_COMPLETED)->sum('amount');
        $outAll = (float) PmMaintenanceJob::query()
            ->whereNotNull('completed_at')
            ->sum('quote_amount');

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
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
                ['label' => 'In (MTD)', 'value' => PropertyMoney::kes($inMtd), 'hint' => 'Completed payments'],
                ['label' => 'Out (MTD)', 'value' => PropertyMoney::kes($outMtd), 'hint' => 'Maint. completed'],
                ['label' => 'Net (MTD)', 'value' => PropertyMoney::kes($inMtd - $outMtd), 'hint' => ''],
                ['label' => 'In (all)', 'value' => PropertyMoney::kes($inAll), 'hint' => ''],
                ['label' => 'Out (all)', 'value' => PropertyMoney::kes($outAll), 'hint' => ''],
                ['label' => 'Net (all)', 'value' => PropertyMoney::kes($inAll - $outAll), 'hint' => ''],
            ],
            'columns' => ['Date', 'Type', 'Description', 'Property', 'In', 'Out', 'Balance'],
            'tableRows' => $rows,
            'cashDual' => $dual,
        ]);
    }

    public function ownerBalances(): View
    {
        return view('property.agent.financials.owner_balances', [
            'stats' => [
                ['label' => 'Outstanding AR', 'value' => PropertyMoney::kes((float) PmInvoice::query()->whereColumn('amount_paid', '<', 'amount')->selectRaw('COALESCE(SUM(amount-amount_paid),0) as t')->value('t')), 'hint' => ''],
            ],
            'columns' => ['Owner', 'Property', 'Available', 'Pending', 'Last remittance', 'Next run', 'Statement'],
            'tableRows' => [],
        ]);
    }

    public function commission(): View
    {
        return view('property.agent.financials.commission', [
            'stats' => [
                ['label' => 'Accrued (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => 'Configure fee rules'],
            ],
            'columns' => ['Period', 'Owner', 'Property', 'Base rent', 'Fee %', 'Accrued', 'Status', 'Actions'],
            'tableRows' => [],
        ]);
    }
}
