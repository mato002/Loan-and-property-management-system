<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use App\Services\Property\RentRollQuery;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function rentRoll(): View
    {
        $rows = RentRollQuery::tableRows();

        $stats = [
            ['label' => 'Billed (MTD)', 'value' => PropertyMoney::kes((float) PmInvoice::query()->whereMonth('issue_date', now()->month)->sum('amount')), 'hint' => 'Issued'],
            ['label' => 'Collected (MTD)', 'value' => PropertyMoney::kes(PropertyDashboardStats::mtdCollected()), 'hint' => 'Payments'],
            ['label' => 'Outstanding', 'value' => PropertyMoney::kes(PropertyDashboardStats::outstandingBalance()), 'hint' => 'Open'],
            ['label' => 'Units on roll', 'value' => (string) count($rows), 'hint' => 'Listed'],
        ];

        return view('property.agent.revenue.rent_roll', [
            'stats' => $stats,
            'columns' => ['Unit', 'Tenant', 'Period', 'Rent due', 'Other charges', 'Paid', 'Balance', 'Status'],
            'tableRows' => $rows,
        ]);
    }

    public function arrears(): View
    {
        $invoices = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->whereColumn('amount_paid', '<', 'amount')
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->limit(300)
            ->get();

        $stats = [
            ['label' => '7 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(7, 14)), 'hint' => 'Early'],
            ['label' => '14 days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(14, 30)), 'hint' => ''],
            ['label' => '30+ days', 'value' => PropertyMoney::kes(PropertyDashboardStats::arrearsBucket(30)), 'hint' => ''],
            ['label' => 'Accounts', 'value' => (string) $invoices->unique('pm_tenant_id')->count(), 'hint' => 'Distinct tenants'],
        ];

        $rows = $invoices->map(function (PmInvoice $i) {
            $bal = max(0, (float) $i->amount - (float) $i->amount_paid);
            $days = now()->diffInDays($i->due_date);

            return [
                $i->tenant->name,
                $i->unit->property->name.'/'.$i->unit->label,
                $i->due_date->format('Y-m-d'),
                (string) $days,
                PropertyMoney::kes($bal),
                '—',
                '—',
                '—',
            ];
        })->all();

        return view('property.agent.revenue.arrears', [
            'stats' => $stats,
            'columns' => ['Tenant', 'Unit', 'Oldest due', 'Days late', 'Balance', 'Last contact', 'Workflow', 'Owner'],
            'tableRows' => $rows,
        ]);
    }

    public function penalties(): View
    {
        return view('property.agent.revenue.penalties', [
            'stats' => [
                ['label' => 'Active rules', 'value' => '0', 'hint' => 'Table `pm_penalty_rules` not added yet'],
                ['label' => 'Applied (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => ''],
                ['label' => 'Waived (MTD)', 'value' => PropertyMoney::kes(0), 'hint' => ''],
                ['label' => 'Pending review', 'value' => '0', 'hint' => ''],
            ],
            'columns' => ['Rule name', 'Scope', 'Trigger', 'Formula', 'Cap', 'Effective', 'Status'],
            'tableRows' => [],
        ]);
    }

    public function receipts(): View
    {
        $invoices = PmInvoice::query()
            ->with(['tenant', 'unit.property'])
            ->where('status', PmInvoice::STATUS_PAID)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $stats = [
            ['label' => 'Paid invoices', 'value' => (string) $invoices->count(), 'hint' => 'Receipt stubs'],
            ['label' => 'eTIMS linked', 'value' => '0', 'hint' => 'Integration pending'],
            ['label' => 'Failed', 'value' => '0', 'hint' => ''],
        ];

        $rows = $invoices->map(fn (PmInvoice $i) => [
            'RCP-'.$i->id,
            $i->invoice_no,
            $i->tenant->name,
            PropertyMoney::kes((float) $i->amount),
            '—',
            $i->updated_at->format('Y-m-d'),
            'stub',
            '—',
        ])->all();

        return view('property.agent.revenue.receipts', [
            'stats' => $stats,
            'columns' => ['Receipt #', 'Invoice', 'Tenant', 'Amount', 'Tax', 'Submitted', 'eTIMS status', 'Actions'],
            'tableRows' => $rows,
        ]);
    }
}
