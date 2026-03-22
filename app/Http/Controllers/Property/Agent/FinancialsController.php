<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceJob;
use App\Models\PmPayment;
use App\Services\Property\PropertyMoney;
use Illuminate\View\View;

class FinancialsController extends Controller
{
    public function incomeExpenses(): View
    {
        $income = (float) PmInvoice::query()->sum('amount');
        $maint = (float) PmMaintenanceJob::query()->whereNotNull('quote_amount')->sum('quote_amount');

        return view('property.agent.financials.income_expenses', [
            'stats' => [
                ['label' => 'Billed (all)', 'value' => PropertyMoney::kes($income), 'hint' => 'Invoices'],
                ['label' => 'Maint. booked', 'value' => PropertyMoney::kes($maint), 'hint' => 'Job quotes'],
                ['label' => 'NOI proxy', 'value' => PropertyMoney::kes(max(0, $income - $maint)), 'hint' => 'Rough'],
                ['label' => 'Margin', 'value' => $income > 0 ? round(100 * max(0, $income - $maint) / $income, 1).'%' : '—', 'hint' => ''],
            ],
            'columns' => ['Account', 'Class', 'Budget', 'Actual', 'Variance', 'Notes'],
            'tableRows' => [],
        ]);
    }

    public function cashFlow(): View
    {
        $in = (float) PmPayment::query()->where('status', PmPayment::STATUS_COMPLETED)->sum('amount');

        return view('property.agent.financials.cash_flow', [
            'stats' => [
                ['label' => 'In (all)', 'value' => PropertyMoney::kes($in), 'hint' => 'Completed payments'],
                ['label' => 'Out (all)', 'value' => PropertyMoney::kes(0), 'hint' => 'Wire payouts next'],
                ['label' => 'Net', 'value' => PropertyMoney::kes($in), 'hint' => ''],
            ],
            'columns' => ['Date', 'Type', 'Description', 'Property', 'In', 'Out', 'Balance'],
            'tableRows' => [],
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
